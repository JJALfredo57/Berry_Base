<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use App\Services\CustomerRiskService;
use App\Services\DailyCapacityService;
use App\Services\MobileNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    private function submissionKey(Request $request, string $scope): ?string
    {
        $token = trim((string) $request->input('_submit_token', ''));
        if ($token === '') return null;
        return 'submit:' . $scope . ':' . $request->session()->getId() . ':' . sha1($token);
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
        $settings = $shopId
            ? DB::table('site_settings')->where('shop_id', $shopId)->first()
            : DB::table('site_settings')->whereNull('shop_id')->first();
        if (!$settings) $settings = DB::table('site_settings')->first();
        return max(1000, (int)($settings->delivery_coverage_radius ?? 5000));
    }

    private function nearestCoverageZone(float $lat, float $lng, ?string $shopId): ?object
    {
        $zonesQuery = DB::table('delivery_zones')
            ->where('is_active', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng');
        if ($shopId) $zonesQuery->where('shop_id', $shopId);
        else $zonesQuery->whereNull('shop_id');

        $radius = $this->coverageRadiusMeters($shopId);
        $nearest = null;
        foreach ($zonesQuery->get() as $zone) {
            $distance = $this->haversine($lat, $lng, (float)$zone->lat, (float)$zone->lng);
            if ($distance <= $radius && (!$nearest || $distance < $nearest->distance_m)) {
                $zone->distance_m = $distance;
                $nearest = $zone;
            }
        }

        return $nearest;
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
            $deliveryZonesQuery = DB::table('delivery_zones')->where('is_active', true);
            if (!empty($product->shop_id)) {
                $deliveryZonesQuery->where('shop_id', $product->shop_id);
            } else {
                $deliveryZonesQuery->whereNull('shop_id');
            }
            $deliveryZones = $deliveryZonesQuery->orderBy('sort_order')->orderBy('barangay')->get();
        } catch (\Exception $e) {}

        $defaultAddr = null;
        $shop = null;
        try {
            if (!empty($product->shop_id)) {
                $shop = DB::table('shops')->where('id', $product->shop_id)->first();
            }
        } catch (\Exception $e) {}

        $shopSettings = $shop
            ? \Illuminate\Support\Facades\DB::table('site_settings')->where('shop_id', $shop->id)->first()
            : \Illuminate\Support\Facades\DB::table('site_settings')->first();
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

        return view('guest.checkout_regular', compact('product','checkout','sizes','deliveryZones','defaultAddr','shopLat','shopLng','pricing','addonCategories','addonsByCategory','shop','shopSettings'));
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

            $smsResult = SmsHelper::sendOtpWithResult($phone, $otp, $siteName, '', $shopName, $preTrackCode, $preTrackUrl);
            if (!$smsResult['ok']) {
                return response()->json([
                    'ok' => false,
                    'error' => $smsResult['error'] ?? 'OTP was not sent. Please check SMS settings or try again.',
                ], 422);
            }

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
        $note = trim((string) $request->input('custom_note', $checkout['custom_note'] ?? ''));
        $note = substr(preg_replace('/\s+/', ' ', $note), 0, 160);

        $product = DB::table('products')->where('id', $pid)->first();
        if (!$product) return redirect()->route('catalog');

        $risk = app(CustomerRiskService::class)->evaluateOrder($phone, $product->shop_id ?? null, 'regular');
        if (!$risk['allowed']) {
            return back()->with('error', $risk['message'])->withInput();
        }

        $fulfillment   = $request->input('fulfillment_type','Pickup');
        $zone          = $request->input('delivery_zone','');
        $deliveryFee   = (float)$request->input('delivery_fee',0);
        $serviceCharge = (float)$request->input('service_charge',0);
        $address       = trim($request->input('address',''));
        $lat           = $request->input('latitude') !== '' ? (float)$request->input('latitude') : null;
        $lng           = $request->input('longitude') !== '' ? (float)$request->input('longitude') : null;
        $sdate         = trim($request->input('schedule_date','')) ?: null;
        $stime         = trim($request->input('schedule_time','')) ?: null;
        $payment       = $request->input('payment_method','COD');
        $selectedSize  = trim($request->input('selected_size', $checkout['selected_size'] ?? ''));

        if (!$sdate) return back()->with('error','Please select your preferred date.')->withInput();
        if (!$stime) return back()->with('error','Please select a preferred time slot.')->withInput();

        if ($fulfillment === 'Delivery' && ($address === '' || $lat === null || $lng === null))
            return back()->with('error','Please pin your location on the map.')->withInput();

        if ($fulfillment === 'Delivery' && !$zone && $lat !== null && $lng !== null) {
            $nearestZone = $this->nearestCoverageZone($lat, $lng, $product->shop_id ?? null);
            if ($nearestZone) {
                $zone = $nearestZone->barangay;
                $request->merge(['delivery_zone' => $zone]);
            }
        }

        if ($fulfillment === 'Delivery' && !$zone)
            return back()->with('error','Please pin a location inside this shop delivery coverage area or choose pickup.')->withInput();

        if ($fulfillment === 'Delivery' && $zone) {
            try {
                $zoneQuery = DB::table('delivery_zones')
                    ->where('barangay', $zone)
                    ->where('is_active', true);
                if (!empty($product->shop_id)) {
                    $zoneQuery->where('shop_id', $product->shop_id);
                } else {
                    $zoneQuery->whereNull('shop_id');
                }
                $zoneRow = $zoneQuery->first();
                if (!$zoneRow) {
                    return back()->with('error', 'This shop does not deliver to the selected barangay. Please choose pickup or select another product.')->withInput();
                }

                $pinnedZonesQuery = DB::table('delivery_zones')
                    ->where('is_active', true)
                    ->whereNotNull('lat')
                    ->whereNotNull('lng');
                if (!empty($product->shop_id)) $pinnedZonesQuery->where('shop_id', $product->shop_id);
                else $pinnedZonesQuery->whereNull('shop_id');
                if ($pinnedZonesQuery->exists()) {
                    $nearestZone = $this->nearestCoverageZone($lat, $lng, $product->shop_id ?? null);
                    if (!$nearestZone) {
                        return back()->with('error', 'Sorry, your delivery address is outside this shop delivery coverage area. Please move the pin or choose pickup.')->withInput();
                    }
                    $zone = $nearestZone->barangay ?? $zone;
                    $zoneRow = $nearestZone;
                }

                $settings = !empty($product->shop_id)
                    ? DB::table('site_settings')->where('shop_id', $product->shop_id)->first()
                    : DB::table('site_settings')->whereNull('shop_id')->first();
                if (!$settings) $settings = DB::table('site_settings')->first();
                if ($settings && $settings->shop_lat && $settings->shop_lng) {
                    $dist        = $this->haversine($lat, $lng, (float)$settings->shop_lat, (float)$settings->shop_lng);
                    $km          = $dist / 1000;
                    $baseFee     = (float)($settings->base_fee ?? 30);
                    $feePerKm    = (float)($settings->fee_per_km ?? 15);
                    $freeKm      = max(0, (int)($settings->free_delivery_radius ?? 0)) / 1000;
                    $chargeKm    = max(0, $km - $freeKm);
                    $deliveryFee = $chargeKm <= 0 ? 0 : (int) ceil($baseFee + ($feePerKm * $chargeKm));
                } else {
                    $deliveryFee = (float)($zoneRow->delivery_fee ?? 0);
                }
            } catch (\Exception $e) {}
        }

        $sizePrice = CakeshopHelper::resolveProductUnitPrice($product->id, (float) $product->price, $selectedSize);
        $discount = CakeshopHelper::getActiveProductDiscount($product->id);
        $pricing = CakeshopHelper::calculateDiscountSnapshot($sizePrice, $discount);

        $capacity = app(DailyCapacityService::class)->validate($product->shop_id ?? null, $sdate, $qty);
        if (!$capacity['allowed']) {
            return back()->with('error', $capacity['message'])->withInput();
        }

        $addonTotal  = 0;
        $validAddons = [];

        $total     = ($pricing['final_unit_price'] * $qty) + $addonTotal + ($fulfillment === 'Delivery' ? $deliveryFee + $serviceCharge : 0);
        $oid       = CakeshopHelper::generateId('orders');
        $trackCode = $request->session()->get('guest_pre_track') ?: $this->generateTrackCode();

        // COP and COD both require a 50%+ deposit upfront to deter scammers.
        // Only GCash orders skip the deposit (they pay the full amount via PayMongo).
        $needsDeposit  = ($payment !== 'GCash');
        $depositAmount = $needsDeposit ? round($total * 0.5, 2) : null;

        $submitKey = $this->submissionKey($request, 'guest_checkout_place');
        if ($submitKey && !Cache::add($submitKey . ':lock', true, now()->addMinutes(10))) {
            $existing = Cache::get($submitKey . ':result');
            if (!empty($existing['track_code'])) {
                return redirect()->route('track.order', $existing['track_code'])
                    ->with('msg', $existing['message'] ?? 'Order already placed.');
            }
            return back()->with('error', 'This order is already being processed. Please wait.')->withInput();
        }

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
            'is_read' => false, 'created_at' => now(),
        ]);

        try {
            $pushOrder = DB::table('orders')->where('id', $oid)->first();
            if ($pushOrder) {
                app(MobileNotificationService::class)->notifyOrderSeller(
                    $pushOrder,
                    'New Order #' . $oid,
                    "{$guestName} placed a new order.",
                    ['event' => 'new_order']
                );
            }
        } catch (\Throwable $e) {}

        $request->session()->forget(['guest_checkout','guest_otp','guest_otp_exp','guest_phone','guest_pre_track']);

        // Customer confirmation: push first for mobile app, SMS fallback when no mobile session exists.
        $siteName = config('app.name','Cake Shop');
        $shopName = SmsHelper::getShopName($product->shop_id ?? null);
        $header   = SmsHelper::header($siteName, $shopName);
        $shopLine = $shopName ? "\nShop: {$shopName}" : '';
        if ($needsDeposit) {
            $customerSms =
                "{$header}\n"
                . "Hi {$guestName}! Your order has been received.\n\n"
                . "Order No.: #{$oid}{$shopLine}\n"
                . "Action Required: Pay ₱" . number_format($depositAmount, 2) . " deposit via GCash to confirm your order.\n\n"
                . "Your Tracking Code: {$trackCode}\n"
                . "Track your order and pay the deposit on our website.";
            $customerTitle = 'Deposit Required';
            $customerBody = "Order #{$oid} was received. Please pay your deposit to confirm.";
        } else {
            $customerSms =
                "{$header}\n"
                . "Hi {$guestName}! Thank you for your order!\n\n"
                . "Order No.: #{$oid}{$shopLine}\n"
                . "Status: Pending Confirmation\n\n"
                . "We'll review and confirm your order shortly.\n\n"
                . "Your Tracking Code: {$trackCode}\n"
                . "Use this code to track your order on our website.\n\n"
                . "For concerns, contact us through our shop page.";
            $customerTitle = 'Order Received';
            $customerBody = "Order #{$oid} was received and is pending confirmation.";
        }
        app(MobileNotificationService::class)->notifyGuestTrackCode(
            $trackCode,
            $phone,
            $customerTitle,
            $customerBody,
            ['event' => 'new_order', 'order_id' => (string) $oid],
            $customerSms
        );

        $successMsg = $needsDeposit
            ? 'Order placed! 🎂 Please pay your 50% deposit below to confirm your order.'
            : 'Order placed! We\'ll contact you soon to confirm. 🎂';

        if ($submitKey) {
            Cache::put($submitKey . ':result', [
                'track_code' => $trackCode,
                'message' => $successMsg,
            ], now()->addMinutes(10));
        }

        return redirect()->route('track.order', $trackCode)->with('msg', $successMsg);
    }
}
