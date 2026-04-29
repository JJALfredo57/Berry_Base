<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $checkout = $request->session()->get('guest_checkout');
        if (!$checkout) return redirect()->route('catalog');

        $product = DB::table('products')->where('id', $checkout['product_id'])->first();
        if (!$product) return redirect()->route('catalog');

        $sizes = collect();
        try {
            $sizes = DB::table('product_sizes')
                ->where('product_id', $checkout['product_id'])
                ->where('is_active', true)->orderBy('sort_order')->get();
        } catch (\Exception $e) {}

        $deliveryZones = collect();
        try {
            $deliveryZones = DB::table('delivery_zones')
                ->where('is_active', true)->orderBy('sort_order')->get();
        } catch (\Exception $e) {}

        $defaultAddr = null;
        $shopSettings = \Illuminate\Support\Facades\DB::table('site_settings')->first();
        $shopLat = $shopSettings->shop_lat ?? 15.8107127;
        $shopLng = $shopSettings->shop_lng ?? 120.4716710;
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

        return view('guest.checkout', compact('product','checkout','sizes','deliveryZones','defaultAddr','shopLat','shopLng','pricing','addonCategories','addonsByCategory'));
    }

    public function sendOtp(Request $request)
    {
        try {
            $phone = trim($request->input('phone',''));
            if (!$phone) return response()->json(['ok'=>false,'error'=>'Please enter your phone number.']);

            $phone = preg_replace('/\D/','',$phone);
            if (strlen($phone) === 10) $phone = '0'.$phone;
            if (strlen($phone) === 11 && substr($phone,0,1)==='0') $phone = '+63'.substr($phone,1);
            elseif (strlen($phone) === 12 && substr($phone,0,2)==='63') $phone = '+'.$phone;

            $otp          = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
            $expires      = now()->addMinutes(10)->format('Y-m-d H:i:s');
            $siteName     = config('app.name','Cake Shop');
            $preTrackCode = $this->generateTrackCode();
            $preTrackUrl  = url('/track/' . $preTrackCode);

            $request->session()->put('guest_otp',          $otp);
            $request->session()->put('guest_otp_exp',      $expires);
            $request->session()->put('guest_phone',        $phone);
            $request->session()->put('guest_pre_track',    $preTrackCode);

            // Get shop name from session checkout product
            $checkout = $request->session()->get('guest_checkout');
            $shopName = '';
            if (!empty($checkout['product_id'])) {
                $pid  = $checkout['product_id'];
                $shopId = DB::table('products')->where('id', $pid)->value('shop_id');
                $shopName = SmsHelper::getShopName($shopId);
            }

            SmsHelper::sendOtp($phone, $otp, $siteName, '', $shopName, $preTrackCode, $preTrackUrl);

            // Dev mode: return OTP preview in JSON for AJAX display
            $devPayload = null;
            try {
                $p = \Illuminate\Support\Facades\DB::table('platform_settings')->first();
                if (!empty($p->dev_mode)) {
                    $clean = preg_replace('/\D/', '', $phone);
                    if (str_starts_with($clean, '0'))   $clean = '63' . substr($clean, 1);
                    if (!str_starts_with($clean, '63')) $clean = '63' . $clean;
                    $devPayload = [
                        'otp'     => $otp,
                        'phone'   => $clean,
                        'time'    => now()->format('h:i A'),
                        'message' => "{$siteName}: Your OTP verification code is {$otp}. Valid for 10 minutes. Do not share this code.",
                    ];
                }
            } catch (\Throwable $e) {}

            return response()->json(['ok' => true, 'dev' => $devPayload]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('sendOtp error: ' . $e->getMessage());
            return response()->json(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
        }
    }

    public function placeOrder(Request $request)
    {
        $checkout = $request->session()->get('guest_checkout');
        if (!$checkout) return redirect()->route('catalog');

        $otp       = trim($request->input('otp_code',''));
        $storedOtp = $request->session()->get('guest_otp');
        $otpExp    = $request->session()->get('guest_otp_exp');
        $phone     = $request->session()->get('guest_phone');

        if (!$otp || !$storedOtp || $otp !== $storedOtp)
            return back()->with('error','Invalid verification code.')->withInput();
        if (now()->format('Y-m-d H:i:s') > $otpExp)
            return back()->with('error','Verification code expired. Please request a new one.')->withInput();

        $guestName = trim($request->input('guest_name',''));
        if (!$guestName) return back()->with('error','Please enter your name.')->withInput();

        $pid  = $checkout['product_id'];
        $qty  = (int)($checkout['quantity'] ?? 1);
        $note = $checkout['custom_note'] ?? '';

        $product = DB::table('products')->where('id', $pid)->first();
        if (!$product) return redirect()->route('catalog');

        $fulfillment   = $request->input('fulfillment_type','Pickup');
        $zone          = $request->input('delivery_zone','');
        $deliveryFee   = (float)$request->input('delivery_fee',0);
        $serviceCharge = (float)$request->input('service_charge',0);
        $address       = trim($request->input('address',''));
        $lat           = $request->input('latitude') !== '' ? (float)$request->input('latitude') : null;
        $lng           = $request->input('longitude') !== '' ? (float)$request->input('longitude') : null;
        $sdate         = $request->input('schedule_date') ?: null;
        $stime         = $request->input('schedule_time') ?: null;
        $payment       = $request->input('payment_method','COD');
        $selectedSize  = trim($request->input('selected_size',''));

        if ($fulfillment === 'Delivery' && !$zone)
            return back()->with('error','Please select your barangay.')->withInput();
        if ($fulfillment === 'Delivery' && ($address === '' || $lat === null || $lng === null))
            return back()->with('error','Please pin your location on the map.')->withInput();

        if ($fulfillment === 'Delivery' && $zone) {
            try {
                $zoneRow = DB::table('delivery_zones')->where('barangay',$zone)->where('is_active', true)->first();
                if ($zoneRow) $deliveryFee = (float)$zoneRow->fee;
            } catch (\Exception $e) {}
        }

        $sizePrice = CakeshopHelper::resolveProductUnitPrice($product->id, (float) $product->price, $selectedSize);
        $discount = CakeshopHelper::getActiveProductDiscount($product->id);
        $pricing = CakeshopHelper::calculateDiscountSnapshot($sizePrice, $discount);

        // ── SHOP-WIDE DAILY CAPACITY CHECK ───────────────────────────────────
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
                    ->where('schedule_date', $sdate)
                    ->whereNotIn('status', ['Cancelled'])
                    ->sum('quantity');
                try {
                    $totalOrdered += (int) DB::table('custom_orders')
                        ->where('schedule_date', $sdate)
                        ->whereNotIn('status', ['Rejected','Cancelled'])
                        ->sum('quantity');
                } catch (\Exception $e) {}
                if (($totalOrdered + $qty) > $effectiveMax) {
                    $remaining = max(0, $effectiveMax - $totalOrdered);
                    $msg = $remaining === 0
                        ? "Sorry, {$sdate} is fully booked ({$effectiveMax} pcs max). Please choose another date."
                        : "Only {$remaining} pcs available on {$sdate}. Please reduce your quantity or choose another date.";
                    return back()->with('error', $msg)->withInput();
                }
            }
        }

        // Add-ons
        $selectedAddonIds = array_filter(array_map('intval', $request->input('addons', [])));
        $addonTotal  = 0;
        $validAddons = [];
        if (!empty($selectedAddonIds)) {
            $addons = DB::table('cake_addons')->whereIn('id', $selectedAddonIds)->where('is_active', true)->get();
            foreach ($addons as $addon) {
                $addonTotal += (float) $addon->price;
                $validAddons[] = $addon;
            }
        }

        $total     = ($pricing['final_unit_price'] * $qty) + $addonTotal + ($fulfillment === 'Delivery' ? $deliveryFee + $serviceCharge : 0);
        $oid       = CakeshopHelper::generateId('orders');
        $trackCode = $request->session()->get('guest_pre_track') ?: $this->generateTrackCode();

        $needsDeposit  = ($payment === 'COD');
        $depositAmount = $needsDeposit ? round($total * 0.5, 2) : null;

        DB::table('orders')->insert([
            'id'                  => $oid,
            'shop_id'             => $product->shop_id ?? null,
            'guest_name'          => $guestName,
            'guest_phone'         => $phone,
            'track_code'          => $trackCode,
            'user_id'             => null,
            'product_id'          => $pid,
            'quantity'            => $qty,
            'custom_note'         => $note ?: null,
            'total_price'         => $total,
            'status'              => $needsDeposit ? 'Awaiting Deposit' : 'Pending',
            'deposit_required'    => $needsDeposit ? 1 : 0,
            'deposit_amount'      => $depositAmount,
            'deposit_status'      => $needsDeposit ? 'pending' : null,
            'fulfillment_type'    => $fulfillment,
            'delivery_zone'       => $zone ?? '',
            'delivery_fee'        => $deliveryFee,
            'service_charge'      => $serviceCharge,
            'selected_size'       => $selectedSize ?: null,
            'selected_size_price' => $sizePrice,
            'original_unit_price' => $pricing['original_unit_price'],
            'discount_label'      => $pricing['discount_label'],
            'discount_type'       => $pricing['discount_type'],
            'discount_value'      => $pricing['discount_value'],
            'discount_amount'     => $pricing['discount_amount'],
            'final_unit_price'    => $pricing['final_unit_price'],
            'delivery_address'    => $address ?? '',
            'schedule_date'       => $sdate,
            'schedule_time'       => $stime,
            'payment_method'      => $payment,
            'payment_status'      => 'Unpaid',
            'created_at'          => now(),
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
                ? 'Order placed. Awaiting 50% deposit payment via GCash.'
                : 'Guest order placed.',
            'created_at' => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin', 'receiver_user_id' => null,
            'title'            => '🛍️ New Order from '.$guestName,
            'message'          => "{$guestName} ({$phone}) placed Order #{$oid}.",
            'is_read'          => 0, 'created_at' => now(),
        ]);

        $request->session()->forget(['guest_checkout','guest_otp','guest_otp_exp','guest_phone','guest_pre_track']);

        // SMS confirmation
        $siteName = config('app.name','Cake Shop');
        $shopName = SmsHelper::getShopName($product->shop_id ?? null);
        $header   = SmsHelper::header($siteName, $shopName);
        $shopLine = $shopName ? "\nShop: {$shopName}" : '';
        if ($needsDeposit) {
            SmsHelper::send($phone,
                "{$header}\n"
                . "Hi {$guestName}! Your order has been received.\n\n"
                . "Order No.: #{$oid}{$shopLine}\n"
                . "Action Required: Pay ₱" . number_format($depositAmount, 2) . " deposit via GCash to confirm your order.\n\n"
                . "Your Tracking Code: {$trackCode}\n"
                . "Track your order and pay the deposit on our website."
            );
        } else {
            SmsHelper::send($phone,
                "{$header}\n"
                . "Hi {$guestName}! Thank you for your order!\n\n"
                . "Order No.: #{$oid}{$shopLine}\n"
                . "Status: Pending Confirmation\n\n"
                . "We'll review and confirm your order shortly.\n\n"
                . "Your Tracking Code: {$trackCode}\n"
                . "Use this code to track your order on our website.\n\n"
                . "For concerns, contact us through our shop page."
            );
        }

        if ($needsDeposit) {
            return redirect()->route('guest.pay_deposit', $trackCode);
        }

        return redirect()->route('track.order', $trackCode)
            ->with('msg','Order placed! We\'ll contact you soon to confirm. 🎂');
    }
}
