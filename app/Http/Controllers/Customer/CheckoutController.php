<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
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

        return view('customer.checkout', compact(
            'product', 'checkout', 'defaultAddr', 'customer',
            'sizes', 'deliveryZones', 'shop', 'shopSettings', 'pricing',
            'addonCategories', 'addonsByCategory'
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

    public function placeOrder(Request $request)
    {
        $uid      = session('user')['id'];
        $checkout = $request->session()->get('checkout');
        if (!$checkout) return redirect()->route('customer.catalog');

        $pid  = (int) $checkout['product_id'];
        $qty  = (int) $checkout['quantity'];
        $note = $checkout['custom_note'];

        $product = DB::table('products')->where('id', $pid)->first();
        if (!$product) return redirect()->route('customer.catalog');

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
            $shopZones = DB::table('delivery_zones')
                ->where('shop_id', $product->shop_id)
                ->where('is_active', true)
                ->whereNotNull('lat')->whereNotNull('lng')
                ->get();

            if ($shopZones->isNotEmpty()) {
                $inCoverage = false;
                foreach ($shopZones as $z) {
                    if ($this->haversine($lat, $lng, (float)$z->lat, (float)$z->lng) <= 3000) {
                        $inCoverage = true;
                        break;
                    }
                }
                if (!$inCoverage) {
                    return back()->with('error', 'Sorry, your delivery address is outside our delivery coverage area. Please contact the shop for assistance.');
                }
            }

            // ── Recalculate fee server-side (prevent tampering) ─
            $settings = DB::table('site_settings')->where('shop_id', $product->shop_id)->first();
            if ($settings && $settings->shop_lat && $settings->shop_lng) {
                $dist       = $this->haversine($lat, $lng, (float)$settings->shop_lat, (float)$settings->shop_lng);
                $freeRadius = (int)($settings->free_delivery_radius ?? 0);
                if ($freeRadius > 0 && $dist <= $freeRadius) {
                    $deliveryFee = 0;
                } else {
                    $km = $dist / 1000;
                    $deliveryFee = ceil(
                        ((float)($settings->fee_per_meter ?? 0.05)) * $dist
                        + (((float)($settings->maintenance_per_km ?? 5)) + ((float)($settings->fuel_per_km ?? 8))) * $km
                    );
                }
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
                $totalOrdered = (int) DB::table('orders')
                    ->where('schedule_date', $sdate)->whereNotIn('status', ['Cancelled'])->sum('quantity');
                try {
                    $totalOrdered += (int) DB::table('custom_orders')
                        ->where('schedule_date', $sdate)->whereNotIn('status', ['Rejected','Cancelled'])->sum('quantity');
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

        // Add-ons
        $selectedAddonIds = array_filter(array_map('intval', $request->input('addons', [])));
        $addonTotal = 0;
        $validAddons = [];
        if (!empty($selectedAddonIds)) {
            $addons = DB::table('cake_addons')->whereIn('id', $selectedAddonIds)->where('is_active', true)->get();
            foreach ($addons as $addon) {
                $addonTotal += (float) $addon->price;
                $validAddons[] = $addon;
            }
        }

        $baseTotal = $pricing['final_unit_price'] * $qty;
        $total     = $baseTotal + $addonTotal + ($fulfillment === 'Delivery' ? $deliveryFee : 0);
        $oid       = CakeshopHelper::generateId('orders');

        $needsDeposit  = ($payment === 'COD');
        $depositAmount = $needsDeposit ? round($total * 0.5, 2) : null;

        DB::table('orders')->insert([
            'id'               => $oid,
            'shop_id'          => $product->shop_id ?? null,
            'user_id'          => $uid,
            'product_id'       => $pid,
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
            'final_unit_price' => $pricing['final_unit_price'],
            'delivery_address' => $address ?? '',
            'schedule_date'    => $sdate,
            'schedule_time'    => $stime,
            'payment_method'   => $payment,
            'payment_status'   => 'Unpaid',
            'created_at'       => now(),
        ]);

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

        DB::table('messages')->insert([
            'order_id'    => $oid,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => "New order placed. Order #{$oid}." . ($note ? "\nNote: {$note}" : ''),
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'New Order #' . $oid,
            'message'          => 'New order from ' . (session('user')['fullname'] ?? 'Customer') . '.',
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        $request->session()->forget('checkout');

        if ($payment === 'GCash') {
            $request->session()->put('last_order_id', $oid);
            return redirect()->route('customer.pay_gcash', ['id' => $oid]);
        }

        // COD / Pickup — require deposit before seller sees the order
        return redirect()->route('customer.pay_deposit', $oid);
    }
}
