<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use App\Support\BecCastilloAddons;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomOrderController extends Controller
{
    use UploadsFiles;

    private function generateTrackCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
        } while (DB::table('orders')->where('track_code', $code)->exists());
        return $code;
    }

    private function loadOptions(?string $shopId = null): array
    {
        $base = DB::table('custom_order_options')->where('is_active', true);

        $shopRows     = $shopId
            ? (clone $base)->where('shop_id', $shopId)->orderBy('sort_order')->orderBy('id')->get()->groupBy('type')
            : collect();
        $defaultRows  = (clone $base)->whereNull('shop_id')->orderBy('sort_order')->orderBy('id')->get()->groupBy('type');

        $resolve = fn(string $type) => $shopRows->get($type)?->isNotEmpty()
            ? $shopRows->get($type)
            : ($defaultRows->get($type) ?? collect());

        return [
            'flavors'      => $resolve('flavor'),
            'sizes'        => $resolve('size'),
            'layers'       => $resolve('layer'),
            'complexities' => $resolve('complexity'),
            'timeSlots'    => $resolve('time_slot'),
        ];
    }

    public function show(Request $request)
    {
        $targetShop = null;
        $shopId     = null;
        if ($slug = $request->query('shop')) {
            $targetShop = DB::table('shops')->where('shop_slug', $slug)->where('status', 'approved')->first();
            if ($targetShop) $shopId = $targetShop->id;
        }

        if ($targetShop && BecCastilloAddons::isBecCastilloShop($targetShop)) {
            BecCastilloAddons::ensureForShop($targetShop->id);
        }

        $options = $this->loadOptions($shopId);

        $addonCategories = DB::table('cake_addon_categories')
            ->where('is_active', true)
            ->when($shopId, fn($q) => $q->where('shop_id', $shopId), fn($q) => $q->whereNull('shop_id'))
            ->orderBy('sort_order')->get();
        $addonsByCategory = DB::table('cake_addons as a')
            ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
            ->where('a.is_active', true)->where('c.is_active', true)
            ->when($shopId, fn($q) => $q->where('c.shop_id', $shopId), fn($q) => $q->whereNull('c.shop_id'))
            ->select('a.*', 'c.name as category_name', 'c.icon as category_icon')
            ->orderBy('a.category_id')->orderBy('a.sort_order')
            ->get()->groupBy('category_id');

        $deliveryZones = collect();
        try {
            $deliveryZones = DB::table('delivery_zones')
                ->when($shopId, fn($q) => $q->where('shop_id', $shopId))
                ->where('is_active', true)->orderBy('sort_order')->get();
        } catch (\Exception $e) {}

        $defaultAddr  = null;
        $shopSettings = $shopId
            ? DB::table('site_settings')->where('shop_id', $shopId)->first()
            : DB::table('site_settings')->whereNull('shop_id')->first();
        $shopLat = $shopSettings->shop_lat ?? 15.8107127;
        $shopLng = $shopSettings->shop_lng ?? 120.4716710;

        return view('guest.custom_order', array_merge($options, compact(
            'addonCategories','addonsByCategory','deliveryZones','defaultAddr','shopLat','shopLng','targetShop'
        )));
    }

    public function sendOtp(Request $request)
    {
        $phone = trim($request->input('phone',''));
        if (!$phone) return response()->json(['ok'=>false,'error'=>'Please enter your phone number.']);

        $phone = preg_replace('/\D/','',$phone);
        if (strlen($phone) === 10) $phone = '0'.$phone;
        if (strlen($phone) === 11 && substr($phone,0,1)==='0') $phone = '+63'.substr($phone,1);
        elseif (strlen($phone) === 12 && substr($phone,0,2)==='63') $phone = '+'.$phone;

        $otp          = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
        $preTrackCode = $this->generateTrackCode();
        $preTrackUrl  = url('/track/' . $preTrackCode);

        $request->session()->put('co_guest_otp',       $otp);
        $request->session()->put('co_guest_otp_exp',   now()->addMinutes(10)->format('Y-m-d H:i:s'));
        $request->session()->put('co_guest_phone',     $phone);
        $request->session()->put('co_guest_pre_track', $preTrackCode);

        $siteName = config('app.name', 'Cake Shop');
        $shopName = '';
        if ($slug = $request->input('shop_slug')) {
            $shopRow  = \Illuminate\Support\Facades\DB::table('shops')->where('shop_slug', $slug)->where('status', 'approved')->first();
            $shopName = $shopRow->shop_name ?? '';
        }

        $header  = SmsHelper::header($siteName, $shopName);
        $message = "{$header}\nCode: {$otp}\nValid 10 mins. Do not share.";

        $result = SmsHelper::sendWithResult($phone, $message, true);

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Failed to send OTP. Please try again.']);
        }

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $otp       = trim($request->input('otp_code',''));
        $storedOtp = $request->session()->get('co_guest_otp');
        $otpExp    = $request->session()->get('co_guest_otp_exp');
        $phone     = $request->session()->get('co_guest_phone');

        if (!$otp || !$storedOtp || $otp !== $storedOtp)
            return back()->with('error','Invalid verification code.')->withInput();
        if (now()->format('Y-m-d H:i:s') > $otpExp)
            return back()->with('error','Verification code expired.')->withInput();

        $guestName = trim($request->input('guest_name',''));
        if (!$guestName) return back()->with('error','Please enter your name.')->withInput();

        $shopId = null;
        if ($slug = $request->input('shop_slug')) {
            $shopRow = DB::table('shops')->where('shop_slug', $slug)->where('status', 'approved')->first();
            if ($shopRow) $shopId = $shopRow->id;
        }
        $options = $this->loadOptions($shopId);
        $cakeName   = trim($request->input('cake_name','Customized Cake'));
        $flavor     = trim($request->input('flavor',''));
        $sizeLabel  = trim($request->input('size',''));
        $layerLabel = trim($request->input('layers',''));
        $compLabel  = trim($request->input('design_complexity',''));
        $dedication = trim($request->input('dedication',''));
        $timeSlot   = trim($request->input('time_slot',''));
        $qty        = max(1,(int)$request->input('quantity',1));
        $customNote = trim($request->input('custom_note',''));
        $addonInstructions = trim($request->input('addon_instructions',''));

        // Reference images
        $refImages = [];
        if ($request->hasFile('reference_images')) {
            foreach ($request->file('reference_images') as $file) {
                if (!$file->isValid() || $file->getSize() > 5*1024*1024) continue;
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext,['jpg','jpeg','png','webp','gif'])) continue;
                $refImages[] = $this->uploadFile($file, 'uploads/custom_orders');
            }
        }

        $basePrice = 1200.00; $sizeSurcharge = 0; $layerSurcharge = 0; $complexitySurcharge = 0;
        if ($sizeLabel) { $so = $options['sizes']->firstWhere('label',$sizeLabel); if ($so) $sizeSurcharge = (float)$so->price; }
        if ($layerLabel) { $lo = $options['layers']->firstWhere('label',$layerLabel); if ($lo) $layerSurcharge = (float)$lo->price; }
        if ($compLabel) { $co = $options['complexities']->firstWhere('label',$compLabel); if ($co) $complexitySurcharge = (float)$co->price; }

        $selectedAddonIds = array_filter(array_map('intval',$request->input('addons',[])));
        $addonTotal = 0; $validAddons = [];
        if (!empty($selectedAddonIds)) {
            $addons = DB::table('cake_addons')->whereIn('id',$selectedAddonIds)->where('is_active', true)->get();
            foreach ($addons as $a) { $addonTotal += (float)$a->price; $validAddons[] = $a; }
        }

        $fulfillment   = $request->input('fulfillment_type','Pickup');
        $zone          = $request->input('delivery_zone','');
        $deliveryFee   = (float)$request->input('delivery_fee',0);
        $serviceCharge = (float)$request->input('service_charge',0);
        $address       = trim($request->input('address',''));
        $lat           = $request->input('latitude') !== '' ? (float)$request->input('latitude') : null;
        $lng           = $request->input('longitude') !== '' ? (float)$request->input('longitude') : null;
        $sdate         = $request->input('schedule_date') ?: null;
        $payment       = $request->input('payment_method','COD');

        if ($sdate && $sdate <= date('Y-m-d'))
            return back()->with('error', 'Preferred date must be at least tomorrow. Custom cakes require preparation time — same-day orders are not accepted.')->withInput();

        if ($fulfillment === 'Delivery' && ($address === '' || $lat === null || $lng === null))
            return back()->with('error','Please pin your location.')->withInput();
        if ($fulfillment === 'Delivery' && !$zone)
            return back()->with('error','Delivery is not available at the pinned location. Please move the pin or choose pickup.')->withInput();

        if ($fulfillment === 'Delivery' && $zone) {
            try {
                $zoneQuery = DB::table('delivery_zones')->where('barangay',$zone)->where('is_active', true);
                if ($shopId) $zoneQuery->where('shop_id', $shopId);
                else $zoneQuery->whereNull('shop_id');
                $zr = $zoneQuery->first();
                if (!$zr) {
                    return back()->with('error','This shop does not deliver to the pinned location. Please move the pin or choose pickup.')->withInput();
                }
                $deliveryFee = (float)$zr->fee;
            } catch (\Exception $e) {
                return back()->with('error','We could not verify delivery availability. Please try again.')->withInput();
            }
        }

        $unitPrice = $basePrice + $sizeSurcharge + $layerSurcharge + $complexitySurcharge;
        $total     = ($unitPrice * $qty) + $addonTotal + ($fulfillment === 'Delivery' ? $deliveryFee + $serviceCharge : 0);

        $breakdown = [
            'base_price'=>$basePrice,'size_surcharge'=>$sizeSurcharge,'layer_surcharge'=>$layerSurcharge,
            'complexity_surcharge'=>$complexitySurcharge,'unit_price'=>$unitPrice,
            'quantity'=>$qty,'subtotal'=>$unitPrice*$qty,
            'addon_total'=>$addonTotal,'delivery_fee'=>$fulfillment==='Delivery'?$deliveryFee:0,
            'service_charge'=>$fulfillment==='Delivery'?$serviceCharge:0,'total'=>$total,
        ];

        $parts = ["CUSTOM ORDER — {$cakeName}"];
        if ($flavor)     $parts[] = "Flavor: {$flavor}";
        if ($sizeLabel)  $parts[] = "Size: {$sizeLabel}";
        if ($layerLabel) $parts[] = "Layers: {$layerLabel}";
        if ($compLabel)  $parts[] = "Design: {$compLabel}";
        if ($dedication) $parts[] = "Dedication: \"{$dedication}\"";
        if ($customNote) $parts[] = "Notes: {$customNote}";
        if (!empty($validAddons) && $addonInstructions) {
            $parts[] = "Add-on instructions: {$addonInstructions}";
        }
        $fullNote = implode(' | ',$parts);

        $customProduct = DB::table('products')->where('classification','Custom')->orderBy('created_at')->first();
        if (!$customProduct) {
            $customPid = CakeshopHelper::generateId('products');
            DB::table('products')->insert(['id'=>$customPid,'name'=>'Custom Cake Order','description'=>'Custom cake placeholder.','price'=>$basePrice,'image_path'=>'/images/default-cake.svg','classification'=>'Custom','flavor'=>null,'created_at'=>now()]);
        } else { $customPid = $customProduct->id; }

        $oid       = CakeshopHelper::generateId('orders');
        $trackCode = $request->session()->get('co_guest_pre_track') ?: $this->generateTrackCode();

        DB::table('orders')->insert([
            'id'=>$oid,'shop_id'=>$shopId,
            'guest_name'=>$guestName,'guest_phone'=>$phone,'track_code'=>$trackCode,
            'user_id'=>null,'product_id'=>$customPid,'quantity'=>$qty,
            'custom_note'=>$fullNote,'total_price'=>$total,'status'=>'Pending Review',
            'fulfillment_type'=>$fulfillment,'delivery_zone'=>$zone??'',
            'delivery_fee'=>$deliveryFee,'service_charge'=>$serviceCharge,
            'selected_size'=>$sizeLabel?:null,'selected_size_price'=>$unitPrice,
            'delivery_address'=>$address??'','latitude'=>$lat??0,
            'schedule_date'=>$sdate,'schedule_time'=>null,
            'payment_method'=>$payment,'payment_status'=>'Unpaid','created_at'=>now(),
        ]);

        DB::table('custom_orders')->insert([
            'id'               => CakeshopHelper::generateId('custom_orders'),
            'order_id'         => $oid,
            'shop_id'          => $shopId,
            'user_id'          => null,
            'guest_name'       => $guestName,
            'guest_phone'      => $phone,
            'cake_name'        => $cakeName,
            'flavor'           => $flavor ?: null,
            'size'             => $sizeLabel ?: null,
            'layers'           => $layerLabel ?: null,
            'design_complexity'=> $compLabel ?: null,
            'dedication'       => $dedication ?: null,
            'custom_note'      => trim($customNote . (!empty($validAddons) && $addonInstructions ? "\nAdd-on instructions: {$addonInstructions}" : '')) ?: null,
            'time_slot'        => $timeSlot ?: null,
            'reference_images' => !empty($refImages) ? json_encode($refImages) : null,
            'estimated_price'  => $total,
            'price_breakdown'  => json_encode($breakdown),
            'review_status'    => 'pending',
            'created_at'       => now(),
        ]);

        foreach ($validAddons as $a) {
            DB::table('order_addons')->insert(['order_id'=>$oid,'addon_id'=>$a->id,'addon_name'=>$a->name,'addon_price'=>$a->price,'created_at'=>now()]);
        }

        DB::table('order_tracking')->insert(['order_id'=>$oid,'status'=>'Pending Review','notes'=>'Custom order submitted. Awaiting admin review.','created_at'=>now()]);

        $notifMsg = "{$guestName} ({$phone}) submitted a custom cake order.";
        DB::table('notifications')->insert([
            'receiver_role'=>'admin','receiver_user_id'=>null,
            'title'=>'🎨 New Custom Order from '.$guestName,
            'message'=>$notifMsg,
            'is_read' => false,'created_at'=>now(),
        ]);
        if ($shopId) {
            $sellerUser = DB::table('shops')->join('users','users.id','=','shops.seller_id')
                ->where('shops.id', $shopId)->value('users.id');
            if ($sellerUser) {
                DB::table('notifications')->insert([
                    'receiver_role'=>'seller','receiver_user_id'=>$sellerUser,
                    'title'=>'🎨 New Custom Order from '.$guestName,
                    'message'=>$notifMsg,
                    'is_read' => false,'created_at'=>now(),
                ]);
            }
        }

        $request->session()->forget(['co_guest_otp','co_guest_otp_exp','co_guest_phone','co_guest_pre_track']);
        $request->session()->put('guest_track_code', $trackCode);

        $siteName    = config('app.name','Cake Shop');
        $shopNameStr = SmsHelper::getShopName($shopId);
        $header      = SmsHelper::header($siteName, $shopNameStr);
        $shopLine    = $shopNameStr ? "\nShop: {$shopNameStr}" : '';
        SmsHelper::send($phone,
            "{$header}\n"
            . "Hi {$guestName}! We received your custom cake order!\n\n"
            . "Order No.: #{$oid}{$shopLine}\n"
            . "Status: Awaiting Review\n\n"
            . "Our team will review your order and get back to you with the final price.\n\n"
            . "Your Tracking Code: {$trackCode}\n"
            . "Use this code to track your order on our website.\n\n"
            . "For concerns, contact us through our shop page."
        );

        return redirect()->route('track.order',$trackCode)
            ->with('msg','Custom order submitted! We\'ll review it and contact you. 🎂');
    }

    /** Guest accepts final price set by admin */
    public function acceptPrice(string $coId)
    {
        $co = DB::table('custom_orders')->where('id', $coId)->whereNotNull('guest_phone')->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'pending') return back()->with('err', 'Price already responded to.');

        // Verify ownership: the order's track_code must match the session track_code
        $order = DB::table('orders')->where('id', $co->order_id)->first();
        $sessionTrack = session('guest_track_code');
        if (!$order || !$sessionTrack || strtoupper($sessionTrack) !== strtoupper($order->track_code)) {
            return redirect()->route('platform.home')->with('err', 'Unauthorized action.');
        }

        $totalPrice    = max((float) $co->admin_price, (float) $order->total_price);
        $depositAmount = round($totalPrice * 0.5, 2);
        $isFullPayment = abs($depositAmount - $totalPrice) < 0.01;

        DB::table('custom_orders')->where('id', $coId)->update([
            'price_confirmed'       => 'accepted',
            'customer_confirmed_at' => now(),
        ]);

        DB::table('orders')->where('id', $co->order_id)->update([
            'deposit_required' => 1,
            'deposit_amount'   => $depositAmount,
            'deposit_status'   => $order->payment_method === 'GCash' ? 'pending' : 'paid',
            'deposit_paid_at'  => $order->payment_method === 'GCash' ? null : now(),
            'payment_status'   => $order->payment_method === 'GCash'
                ? 'Unpaid'
                : ($isFullPayment ? 'Paid' : 'Partial Payment'),
            'paid_at'          => $order->payment_method !== 'GCash' && $isFullPayment ? now() : null,
            'status'           => $order->payment_method === 'GCash' ? $order->status : 'Confirmed',
            'total_price'      => $totalPrice,
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => $order->payment_method === 'GCash' ? $order->status : 'Confirmed',
            'notes'      => $order->payment_method === 'GCash'
                ? 'Guest accepted the final price of PHP ' . number_format($totalPrice, 2) . '. A 50% GCash deposit was prepared automatically.'
                : CakeshopHelper::shortPaymentCode($order->payment_method, $order->fulfillment_type ?? null) . ' deposit of PHP ' . number_format($depositAmount, 2) . ' acknowledged automatically after price acceptance. Order confirmed.',
            'created_at' => now(),
        ]);

        DB::table('messages')->insert([
            'order_id'    => $co->order_id,
            'sender_role' => 'guest',
            'sender_id'   => null,
            'message'     => "I accept the final price of PHP " . number_format($co->admin_price, 2) . ". I will proceed with the deposit payment.",
            'is_read' => false,
            'created_at'  => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Custom Order #' . $co->order_id . ' - Price Accepted',
            'message'          => ($co->guest_name ?? 'Guest') . " accepted PHP " . number_format($co->admin_price, 2) . " for Custom Order #{$co->order_id}. Waiting for deposit.",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        $order = DB::table('orders')->where('id', $co->order_id)->first();
        if ($order->payment_method === 'GCash') {
            return redirect()->route('guest.pay_deposit', $order->track_code);
        }

        $freshOrder = DB::table('orders')->where('id', $co->order_id)->first();
        $this->sendGuestCustomToKitchen($co, $freshOrder ?? $order);

        return redirect()->route('track.order', $order->track_code ?? '')->with('msg', 'Price accepted. Your order is now confirmed and sent to the kitchen.');
    }

    private function sendGuestCustomToKitchen(object $co, object $order): void
    {
        if ($order->kitchen_sent) return;

        try {
            $addons = DB::table('order_addons')->where('order_id', $co->order_id)->get();
            $addonList = $addons->count() > 0
                ? "\nADD-ONS:\n" . $addons->map(fn($a) => '  - ' . $a->addon_name . ($a->addon_price > 0 ? ' (+PHP ' . $a->addon_price . ')' : ' (FREE)'))->implode("\n")
                : '';

            $productName = DB::table('products')->where('id', $order->product_id)->value('name') ?? 'Custom Cake';
            $fullname    = $order->guest_name ?? $co->guest_name ?? 'Guest';
            $phone       = $order->guest_phone ?? $co->guest_phone ?? '';
            $sizeInfo    = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
            $noteInfo    = $order->custom_note ? "\nSPECIAL NOTE: {$order->custom_note}" : '';
            $schedInfo   = $order->schedule_date ? "\nSCHEDULE: " . date('M d, Y', strtotime($order->schedule_date)) : '';
            $payInfo     = CakeshopHelper::shortPaymentCode($order->payment_method, $order->fulfillment_type ?? null)
                . ' - Deposit PHP ' . number_format((float) $order->deposit_amount, 2) . ' acknowledged';

            DB::table('kitchen_tickets')->where('order_id', $co->order_id)->delete();
            DB::table('kitchen_tickets')->insert([
                'shop_id'       => $order->shop_id ?? null,
                'order_id'      => $co->order_id,
                'product_name'  => $productName . ' (Custom)',
                'product_image' => $order->product_image ?? null,
                'quantity'      => $order->quantity ?? 1,
                'instructions'  => "=== KITCHEN ORDER TICKET ===\nOrder #: {$co->order_id}\nCustomer: {$fullname}" . ($phone ? " ({$phone})" : '') . "\nProduct: {$productName} (Custom)\nQty: {$order->quantity}{$sizeInfo}{$noteInfo}{$addonList}{$schedInfo}\nFulfillment: {$order->fulfillment_type}\nPayment: {$payInfo}\n===========================",
                'status'        => 'pending',
                'sent_at'       => now()->format('Y-m-d H:i:s'),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('orders')->where('id', $co->order_id)->update(['kitchen_sent' => true]);
        } catch (\Exception $e) {
            DB::table('order_tracking')->insert([
                'order_id'   => $co->order_id,
                'status'     => $order->status ?? 'Confirmed',
                'notes'      => 'Order confirmed, but kitchen ticket could not be generated automatically. Please notify the shop.',
                'created_at' => now(),
            ]);
        }
    }

    /** Guest cancels custom order after price set */
    public function cancelPrice(string $coId)
    {
        $co = DB::table('custom_orders')->where('id', $coId)->whereNotNull('guest_phone')->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'pending') return back()->with('err', 'Price already responded to.');

        // Verify ownership: the order's track_code must match the session track_code
        $order = DB::table('orders')->where('id', $co->order_id)->first();
        $sessionTrack = session('guest_track_code');
        if (!$order || !$sessionTrack || strtoupper($sessionTrack) !== strtoupper($order->track_code)) {
            return redirect()->route('platform.home')->with('err', 'Unauthorized action.');
        }

        DB::table('custom_orders')->where('id', $coId)->update(['price_confirmed' => 'cancelled']);
        DB::table('orders')->where('id', $co->order_id)->update(['status' => 'Cancelled']);
        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => 'Cancelled',
            'notes'      => 'Guest cancelled the custom order after admin set price.',
            'created_at' => now(),
        ]);

        $order = DB::table('orders')->where('id', $co->order_id)->first();
        return redirect()->route('track.order', $order->track_code ?? '')->with('msg', 'Custom order cancelled.');
    }


}
