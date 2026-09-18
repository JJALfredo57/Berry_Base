<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Services\DailyCapacityService;
use App\Services\MobileNotificationService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckoutController extends Controller
{
    private function generateTrackCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
        } while (DB::table('orders')->where('track_code', $code)->exists());
        return $code;
    }

    public function show(Request $request)
    {
        $checkout = $request->session()->get('checkout');
        if (!$checkout) return redirect()->route('customer.catalog');

        $product = DB::table('products')->where('id', $checkout['product_id'])->first();
        if (!$product) return redirect()->route('customer.catalog');

        $uid         = session('user')['id'];
        $customer    = DB::table('users')->where('id', $uid)->first();
        $defaultAddr = DB::table('user_addresses')
            ->where('user_id', $uid)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        $shop = null;
        try {
            if ($product->shop_id ?? null)
                $shop = DB::table('shops')->where('id', $product->shop_id)->first();
        } catch (\Exception $e) {}

        $sizes = collect();
        try {
            $sizes = DB::table('product_sizes')
                ->where('product_id', $checkout['product_id'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        } catch (\Exception $e) {}

        // Shop settings (fee formula + location)
        $shopSettings = null;
        if ($shop) {
            $shopSettings = DB::table('site_settings')->where('shop_id', $shop->id)->first();
        }

        // Coverage zones for this shop (lat/lng pinned only)
        $deliveryZones = collect();
        if ($shop) {
            $deliveryZones = DB::table('delivery_zones')
                ->where('shop_id', $shop->id)
                ->where('is_active', true)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->get();
        }

        $checkoutItems = $this->checkoutItems($checkout);
        $selectedSize = trim((string) ($checkout['selected_size'] ?? ''));
        $originalUnitPrice = CakeshopHelper::resolveProductUnitPrice($product->id, (float) $product->price, $selectedSize);
        $discount = CakeshopHelper::getActiveProductDiscount($product->id);
        $pricing = CakeshopHelper::calculateDiscountSnapshot($originalUnitPrice, $discount);

        $addonCategories  = collect();
        $addonsByCategory = collect();
        try {
            $addonCategories = DB::table('cake_addon_categories')
                ->where('is_active', true)->orderBy('sort_order')->get();
            $addonsByCategory = DB::table('cake_addons as a')
                ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
                ->where('a.is_active', true)->where('c.is_active', true)
                ->select('a.*', 'c.name as category_name', 'c.icon as category_icon')
                ->orderBy('a.category_id')->orderBy('a.sort_order')
                ->get()->groupBy('category_id');
        } catch (\Exception $e) {}

        $loyalty = app(\App\Services\LoyaltyService::class)->account($uid);
        $verificationStatus = app(\App\Services\CustomerVerificationService::class)->status($uid);
        $isGroupCheckout = $checkoutItems->isNotEmpty();
        $checkoutSubtotal = $isGroupCheckout
            ? (float) $checkoutItems->sum(fn ($item) => (float) $item->final_unit_price_snapshot * (int) $item->quantity)
            : (float) $pricing['final_unit_price'] * (int) $checkout['quantity'];
        $availableVouchers = app(VoucherService::class)->availableForCustomer($uid, $product->shop_id ?? null, $checkoutSubtotal);
        $loyaltyQuote = app(\App\Services\LoyaltyService::class)->redemptionQuote(
            $uid,
            $checkoutSubtotal,
            0,
            $verificationStatus === 'approved'
        );

        return view('customer.checkout_regular', compact(
            'product', 'checkout', 'defaultAddr', 'customer',
            'sizes', 'deliveryZones', 'shop', 'shopSettings', 'pricing',
            'addonCategories', 'addonsByCategory', 'loyalty', 'verificationStatus',
            'checkoutItems', 'availableVouchers', 'loyaltyQuote'
        ));
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function coverageRadiusMeters(?string $shopId): int
    {
        $settings = $shopId ? DB::table('site_settings')->where('shop_id', $shopId)->first() : null;
        return max(1000, (int)($settings->delivery_coverage_radius ?? 5000));
    }

    private function nearestCoverageZone(float $lat, float $lng, ?string $shopId): ?object
    {
        $zones = DB::table('delivery_zones')
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        $radius = $this->coverageRadiusMeters($shopId);
        $nearest = null;
        foreach ($zones as $zone) {
            $distance = $this->haversine($lat, $lng, (float)$zone->lat, (float)$zone->lng);
            if ($distance <= $radius && (!$nearest || $distance < $nearest->distance_m)) {
                $zone->distance_m = $distance;
                $nearest = $zone;
            }
        }

        return $nearest;
    }

    private function submissionKey(Request $request, string $scope): ?string
    {
        $token = trim((string) $request->input('_submit_token', ''));
        if ($token === '') return null;
        return 'submit:' . $scope . ':' . $request->session()->getId() . ':' . sha1($token);
    }

    private function checkoutItems(array $checkout)
    {
        $ids = collect($checkout['cart_item_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) return collect();

        return DB::table('customer_cart_items as ci')
            ->join('products as p', 'p.id', '=', 'ci.product_id')
            ->whereIn('ci.id', $ids)
            ->where('ci.cart_id', $checkout['cart_id'] ?? 0)
            ->select('ci.*', 'p.name as product_name', 'p.image_path', 'p.classification')
            ->orderBy('ci.id')
            ->get();
    }

    public function placeOrder(Request $request)
    {
        $uid      = session('user')['id'];
        $checkout = $request->session()->get('checkout');
        if (!$checkout) return redirect()->route('customer.catalog');

        $pid  = (string) $checkout['product_id'];
        $qty  = (int) $checkout['quantity'];
        $note = trim((string) $request->input('custom_note', $checkout['custom_note'] ?? ''));
        $note = substr(preg_replace('/\s+/', ' ', $note), 0, 160);

        $product = DB::table('products')->where('id', $pid)->first();
        if (!$product) return redirect()->route('customer.catalog');
        $checkoutItems = $this->checkoutItems($checkout);
        $isGroupCheckout = $checkoutItems->isNotEmpty();
        if (!empty($checkout['cart_item_ids']) && !$isGroupCheckout) {
            return redirect()->route('customer.cart')->with('error', 'Those cart items are no longer available.');
        }
        if ($isGroupCheckout) {
            $qty = max(1, (int) $checkoutItems->sum('quantity'));
        }
        $itemNotes = collect($request->input('item_notes', []))
            ->mapWithKeys(fn ($value, $key) => [(int) $key => substr(preg_replace('/\s+/', ' ', trim((string) $value)), 0, 160)]);
        if ($isGroupCheckout) {
            $noteCount = $itemNotes->filter(fn ($value) => $value !== '')->count();
            $note = $noteCount > 0 ? "Grouped order: {$noteCount} item note" . ($noteCount > 1 ? 's' : '') : null;
        }

        // ── DUPLICATE PREVENTION ──────────────────────────────
        $recentDuplicate = DB::table('orders')
            ->where('user_id', $uid)->where('product_id', $pid)
            ->whereIn('status', ['Pending', 'Awaiting Deposit'])
            ->where('created_at', '>=', now()->subSeconds(30)->format('Y-m-d H:i:s'))
            ->first();
        if ($recentDuplicate) {
            $request->session()->forget('checkout');
            return redirect()->route('customer.orders')
                ->with('warn', "Order #{$recentDuplicate->id} was already placed! Check your orders.");
        }

        $fulfillment   = $request->input('fulfillment_type', 'Pickup');
        $zone          = $request->input('delivery_zone', '');
        $deliveryFee   = (float) $request->input('delivery_fee', 0);
        $address       = trim($request->input('address', ''));
        $lat           = $request->input('latitude') !== '' ? (float) $request->input('latitude') : null;
        $lng           = $request->input('longitude') !== '' ? (float) $request->input('longitude') : null;
        $sdate         = $request->input('schedule_date') ?: null;
        $stime         = $request->input('schedule_time') ?: null;
        $payment       = $request->input('payment_method', 'COD');
        $selectedSize  = trim($request->input('selected_size', $checkout['selected_size'] ?? ''));

        if ($fulfillment === 'Delivery' && ($address === '' || $lat === null || $lng === null)) {
            return back()->with('error', 'Please pin your location on the map and enter your address.');
        }

        if ($fulfillment === 'Delivery' && $lat !== null && $lng !== null) {
            // ── Coverage validation ────────────────────────────
            $hasPinnedZones = DB::table('delivery_zones')
                ->where('shop_id', $product->shop_id)
                ->where('is_active', true)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->exists();

            if ($hasPinnedZones) {
                $nearestZone = $this->nearestCoverageZone($lat, $lng, $product->shop_id ?? null);
                if (!$nearestZone) {
                    return back()->with('error', 'Sorry, your delivery address is outside our delivery coverage area. Please contact the shop for assistance.');
                }
                $zone = $nearestZone->barangay ?? $zone;
            }

            // ── Recalculate fee server-side (prevent tampering) ─
            $settings = DB::table('site_settings')->where('shop_id', $product->shop_id)->first();
            if ($settings && $settings->shop_lat && $settings->shop_lng) {
                $dist        = $this->haversine($lat, $lng, (float)$settings->shop_lat, (float)$settings->shop_lng);
                $km          = $dist / 1000;
                $baseFee     = (float)($settings->base_fee   ?? 30);
                $feePerKm    = (float)($settings->fee_per_km ?? 15);
                $freeKm      = max(0, (int)($settings->free_delivery_radius ?? 0)) / 1000;
                $chargeKm    = max(0, $km - $freeKm);
                $deliveryFee = $chargeKm <= 0 ? 0 : (int) ceil($baseFee + ($feePerKm * $chargeKm));
            }
        }

        // Save default address if requested
        if ($fulfillment === 'Delivery' && $request->has('save_default_address')) {
            DB::table('user_addresses')->where('user_id', $uid)->update(['is_default' => 0]);
            DB::table('user_addresses')->insert([
                'user_id'      => $uid,
                'label_name'   => 'Default',
                'full_address' => $address,
                'latitude'     => $lat,
                'longitude'    => $lng,
                'is_default'   => 1,
                'created_at'   => now(),
            ]);
        }

        // Size pricing
        $sizePrice = CakeshopHelper::resolveProductUnitPrice($product->id, (float) $product->price, $selectedSize);
        $discount = CakeshopHelper::getActiveProductDiscount($product->id);
        $pricing = CakeshopHelper::calculateDiscountSnapshot($sizePrice, $discount);

        $capacity = app(DailyCapacityService::class)->validate($product->shop_id ?? null, $sdate, $qty);
        if (!$capacity['allowed']) {
            return back()->with('error', $capacity['message']);
        }

        // ── Daily capacity check ──────────────────────────────
        if ($sdate) {
            $shopId   = $product->shop_id ?? null;
            $settings = $shopId ? DB::table('site_settings')->where('shop_id', $shopId)->first() : null;
            if (!$settings) $settings = DB::table('site_settings')->whereNull('shop_id')->first() ?? DB::table('site_settings')->first();
            $dailyMax = (int)($settings->daily_max_cakes ?? 0);
            if ($dailyMax > 0) {
                $today    = date('Y-m-d');
                $leadDays = (int)ceil((strtotime($sdate) - strtotime($today)) / 86400);
                $effectiveMax = $dailyMax;
                if ($leadDays === 1 && ($settings->lead_1day_max ?? 0) > 0) $effectiveMax = (int)$settings->lead_1day_max;
                elseif ($leadDays === 2 && ($settings->lead_2day_max ?? 0) > 0) $effectiveMax = (int)$settings->lead_2day_max;
                elseif ($leadDays >= 3 && ($settings->lead_3day_plus_max ?? 0) > 0) $effectiveMax = (int)$settings->lead_3day_plus_max;
                $ordersQuery = DB::table('orders')
                    ->where('schedule_date', $sdate)
                    ->whereNotIn('status', ['Cancelled']);
                if ($shopId) $ordersQuery->where('shop_id', $shopId);
                $totalOrdered = (int) $ordersQuery->sum('quantity');
                try {
                    $customQuery = DB::table('custom_orders')
                        ->where('schedule_date', $sdate)
                        ->whereNotIn('status', ['Rejected','Cancelled']);
                    if ($shopId) $customQuery->where('shop_id', $shopId);
                    $totalOrdered += (int) $customQuery->sum('quantity');
                } catch (\Exception $e) {}
                if (($totalOrdered + $qty) > $effectiveMax) {
                    $remaining = max(0, $effectiveMax - $totalOrdered);
                    $msg = $remaining === 0
                        ? "Sorry, {$sdate} is fully booked ({$effectiveMax} pcs max). Please choose another date."
                        : "Only {$remaining} pcs available on {$sdate}. Please reduce quantity or choose another date.";
                    return back()->with('error', $msg);
                }
            }
        }

        $addonTotal = 0;
        $validAddons = [];

        $baseTotal = $isGroupCheckout
            ? (float) $checkoutItems->sum(fn ($item) => (float) $item->final_unit_price_snapshot * (int) $item->quantity)
            : $pricing['final_unit_price'] * $qty;
        $voucherCode = strtoupper(trim((string) $request->input('voucher_code', '')));
        $voucherResult = app(VoucherService::class)->validate($voucherCode, $baseTotal + $addonTotal, $product->shop_id ?? null, $uid);
        if (!$voucherResult['ok']) {
            return back()->with('error', $voucherResult['message'])->withInput();
        }
        $voucherDiscount = (float) ($voucherResult['discount'] ?? 0);
        $loyaltyQuote = app(\App\Services\LoyaltyService::class)->redemptionQuote(
            $uid,
            max(0, $baseTotal + $addonTotal - $voucherDiscount),
            max(0, (int) $request->input('points_to_redeem', 0)),
            app(\App\Services\CustomerVerificationService::class)->isVerified($uid)
        );
        if (!$loyaltyQuote['ok']) {
            return back()->with('error', $loyaltyQuote['message'])->withInput();
        }
        $pointsRedeemed = (int) ($loyaltyQuote['points'] ?? 0);
        $loyaltyDiscount = (float) ($loyaltyQuote['discount'] ?? 0);
        $total     = max(0, $baseTotal + $addonTotal - $voucherDiscount - $loyaltyDiscount) + ($fulfillment === 'Delivery' ? $deliveryFee : 0);
        $oid       = CakeshopHelper::generateId('orders');
        $trackCode = $this->generateTrackCode();

        $needsDeposit  = ($payment === 'COD');
        $depositAmount = $needsDeposit ? round($total * 0.5, 2) : null;

        $submitKey = $this->submissionKey($request, 'customer_checkout_place');
        if ($submitKey && !Cache::add($submitKey . ':lock', true, now()->addMinutes(10))) {
            $existing = Cache::get($submitKey . ':result');
            if (!empty($existing['route'])) {
                return redirect()->route($existing['route'], $existing['params'] ?? [])
                    ->with('warn', $existing['message'] ?? 'Order already placed.');
            }
            return back()->with('error', 'This order is already being processed. Please wait.');
        }

        DB::table('orders')->insert([
            'id'               => $oid,
            'cart_id'           => $checkout['cart_id'] ?? null,
            'shop_id'          => $product->shop_id ?? null,
            'user_id'          => $uid,
            'product_id'       => $pid,
            'track_code'       => $trackCode,
            'quantity'         => $qty,
            'custom_note'      => $note,
            'total_price'      => $total,
            'status'           => $needsDeposit ? 'Awaiting Deposit' : 'Pending',
            'deposit_required' => $needsDeposit ? 1 : 0,
            'deposit_amount'   => $depositAmount,
            'deposit_status'   => $needsDeposit ? 'pending' : null,
            'fulfillment_type' => $fulfillment,
            'delivery_zone'    => $zone ?: ($address ? substr($address, 0, 80) : ''),
            'delivery_fee'     => $deliveryFee,
            'service_charge'   => 0,
            'selected_size'    => $selectedSize ?: null,
            'selected_size_price' => $sizePrice,
            'original_unit_price' => $pricing['original_unit_price'],
            'discount_label'   => $pricing['discount_label'],
            'discount_type'    => $pricing['discount_type'],
            'discount_value'   => $pricing['discount_value'],
            'discount_amount'  => $pricing['discount_amount'],
            'voucher_code'      => $voucherResult['voucher']->code ?? null,
            'voucher_discount_amount' => $voucherDiscount,
            'loyalty_discount_amount' => $loyaltyDiscount,
            'points_redeemed' => $pointsRedeemed,
            'final_unit_price' => $pricing['final_unit_price'],
            'delivery_address' => $address ?? '',
            'schedule_date'    => $sdate,
            'schedule_time'    => $stime,
            'payment_method'   => $payment,
            'payment_status'   => 'Unpaid',
            'created_at'       => now(),
        ]);

        if (Schema::hasTable('order_items')) {
            $rows = $isGroupCheckout
                ? $checkoutItems->map(fn ($item) => [
                    'order_id' => $oid,
                    'shop_id' => $item->shop_id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'image_path' => $item->image_path,
                    'quantity' => max(1, (int) $item->quantity),
                    'selected_size' => $item->selected_size,
                    'unit_price_snapshot' => $item->unit_price_snapshot,
                    'final_unit_price_snapshot' => $item->final_unit_price_snapshot,
                    'discount_amount_snapshot' => $item->discount_amount_snapshot,
                    'discount_label_snapshot' => $item->discount_label_snapshot,
                    'custom_note' => ($itemNotes[(int) $item->id] ?? '') !== '' ? $itemNotes[(int) $item->id] : $item->custom_note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
                : [[
                    'order_id' => $oid,
                    'shop_id' => $product->shop_id ?? null,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'image_path' => $product->image_path ?? null,
                    'quantity' => max(1, (int) $qty),
                    'selected_size' => $selectedSize ?: null,
                    'unit_price_snapshot' => $pricing['original_unit_price'],
                    'final_unit_price_snapshot' => $pricing['final_unit_price'],
                    'discount_amount_snapshot' => $pricing['discount_amount'],
                    'discount_label_snapshot' => $pricing['discount_label'],
                    'custom_note' => $note ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]];
            DB::table('order_items')->insert($rows);
        }

        $createdOrder = DB::table('orders')->where('id', $oid)->first();
        if (!empty($voucherResult['voucher']) && $voucherDiscount > 0 && $createdOrder) {
            app(VoucherService::class)->recordRedemption($voucherResult['voucher'], $createdOrder, $voucherDiscount);
            DB::table('order_discounts')->insert([
                'order_id' => $oid,
                'source_type' => 'voucher',
                'source_id' => $voucherResult['voucher']->id,
                'label' => $voucherResult['voucher']->name,
                'code' => $voucherResult['voucher']->code,
                'amount' => $voucherDiscount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($pointsRedeemed > 0 && $loyaltyDiscount > 0 && $createdOrder) {
            try {
                app(\App\Services\LoyaltyService::class)->redeemForOrder($uid, $oid, $pointsRedeemed, $loyaltyDiscount);
                DB::table('order_discounts')->insert([
                    'order_id' => $oid,
                    'source_type' => 'loyalty',
                    'source_id' => null,
                    'label' => 'Rewards points',
                    'code' => null,
                    'amount' => $loyaltyDiscount,
                    'meta' => json_encode(['points' => $pointsRedeemed, 'rate' => '1 point = PHP 1']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                DB::table('orders')->where('id', $oid)->update([
                    'loyalty_discount_amount' => 0,
                    'points_redeemed' => 0,
                    'total_price' => $total + $loyaltyDiscount,
                    'deposit_amount' => $needsDeposit ? round(($total + $loyaltyDiscount) * 0.5, 2) : null,
                    'updated_at' => now(),
                ]);
                $total += $loyaltyDiscount;
            }
        }

        foreach ($validAddons as $addon) {
            DB::table('order_addons')->insert([
                'order_id'    => $oid,
                'addon_id'    => $addon->id,
                'addon_name'  => $addon->name,
                'addon_price' => $addon->price,
                'created_at'  => now(),
            ]);
        }

        DB::table('order_tracking')->insert([
            'order_id'   => $oid,
            'status'     => $needsDeposit ? 'Awaiting Deposit' : 'Pending',
            'notes'      => $needsDeposit
                ? 'Order placed. Awaiting 50% deposit payment via GCash before confirmation.'
                : 'Order placed successfully.',
            'created_at' => now(),
        ]);

        $sellerMessage = "New order placed. Order #{$oid}.";
        if ($isGroupCheckout) {
            $lines = $checkoutItems->map(function ($item) use ($itemNotes) {
                $line = "- {$item->product_name} x{$item->quantity}";
                if (!empty($item->selected_size)) $line .= " ({$item->selected_size})";
                $itemNote = $itemNotes[(int) $item->id] ?? ($item->custom_note ?? '');
                if ($itemNote !== '') $line .= "\n  Note: {$itemNote}";
                return $line;
            })->implode("\n");
            $sellerMessage .= "\nItems:\n{$lines}";
        } elseif ($note) {
            $sellerMessage .= "\nNote: {$note}";
        }

        DB::table('messages')->insert([
            'order_id'    => $oid,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => $sellerMessage,
            'is_read' => false,
            'created_at'  => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'New Order #' . $oid,
            'message'          => 'New order from ' . (session('user')['fullname'] ?? 'Customer') . '.',
            'is_read' => false,
            'created_at'       => now(),
        ]);

        try {
            $pushOrder = DB::table('orders')->where('id', $oid)->first();
            if ($pushOrder) {
                app(MobileNotificationService::class)->notifyOrderSeller(
                    $pushOrder,
                    'New Order #' . $oid,
                    (session('user')['fullname'] ?? 'Customer') . ' placed a new order.',
                    ['event' => 'new_order']
                );
            }
        } catch (\Throwable $e) {}

        if (!empty($checkout['cart_item_ids'])) {
            DB::table('customer_cart_items')
                ->where('cart_id', $checkout['cart_id'] ?? 0)
                ->whereIn('id', $checkout['cart_item_ids'])
                ->delete();
            if (!empty($checkout['cart_id'])) {
                app(\App\Services\CartService::class)->removeEmptyShop((int) $checkout['cart_id']);
            }
        } elseif (!empty($checkout['cart_item_id'])) {
            DB::table('customer_cart_items')->where('id', $checkout['cart_item_id'])->delete();
            if (!empty($checkout['cart_id'])) {
                app(\App\Services\CartService::class)->removeEmptyShop((int) $checkout['cart_id']);
            }
        }

        $request->session()->forget('checkout');

        if ($payment === 'GCash') {
            $request->session()->put('last_order_id', $oid);
            if ($submitKey) {
                Cache::put($submitKey . ':result', [
                    'route' => 'customer.pay_gcash',
                    'params' => ['id' => $oid],
                    'message' => "Order #{$oid} was already placed.",
                ], now()->addMinutes(10));
            }
            return redirect()->route('customer.pay_gcash', ['id' => $oid]);
        }

        // COD / Pickup — require deposit before seller sees the order
        if ($submitKey) {
            Cache::put($submitKey . ':result', [
                'route' => 'customer.pay_deposit',
                'params' => [$oid],
                'message' => "Order #{$oid} was already placed.",
            ], now()->addMinutes(10));
        }
        return redirect()->route('customer.pay_deposit', $oid);
    }
}
