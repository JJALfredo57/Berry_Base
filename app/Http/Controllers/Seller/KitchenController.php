<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403, 'Shop not found or not approved.');
        return $shop;
    }

    public function index()
    {
        $shop    = $this->getShop();
        $tickets = DB::table('kitchen_tickets as kt')
            ->join('orders as o','o.id','=','kt.order_id')
            ->leftJoin('users as u','u.id','=','o.user_id')
            ->join('products as p','p.id','=','o.product_id')
            ->select('kt.*','kt.status as ticket_status','o.id as order_id',
                \Illuminate\Support\Facades\DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                'p.name as product_name','p.image_path as product_image',
                'o.user_id','o.fulfillment_type','o.rider_id','o.rider_sms_sent','o.custom_note')
            ->where('o.shop_id', $shop->id)
            ->orderByDesc('kt.id')
            ->get();

        // Load custom order reference images
        $orderIds = $tickets->pluck('order_id')->toArray();
        $customImages = [];
        if ($orderIds) {
            try {
                $customs = DB::table('custom_orders')
                    ->whereIn('order_id', $orderIds)
                    ->whereNotNull('reference_images')
                    ->select('order_id','reference_images')
                    ->get();
                foreach ($customs as $c) {
                    $imgs = json_decode($c->reference_images, true);
                    if (is_array($imgs) && count($imgs) > 0) {
                        $customImages[$c->order_id] = $imgs;
                    }
                }
            } catch (\Exception $e) {}
        }

        $riders = DB::table('riders')->where('shop_id', $shop->id)->where('is_active', true)->orderBy('name')->get();

        return view('seller.kitchen', compact('tickets','riders','customImages','shop'));
    }

    public function update(Request $request, string $id)
    {
        $user   = session('user');
        $ticket = DB::table('kitchen_tickets')->where('id', $id)->first();
        if (!$ticket) return back()->with('err', 'Ticket not found.');

        $current = $ticket->status;
        $new     = $request->input('status', '');

        // Waterfall validation — only allow forward movement
        $allowed = [];
        if ($current === 'pending')      $allowed = ['in_progress'];
        if ($current === 'in_progress')  $allowed = ['done'];
        if ($current === 'done')         $allowed = []; // no more moves

        if (!in_array($new, $allowed)) {
            return back()->with('err', "Cannot change kitchen status from '{$current}' to '{$new}'.");
        }

        DB::table('kitchen_tickets')->where('id', $id)->update(['status' => $new]);

        $orderId = $ticket->order_id;

        // Sync order status
        if ($new === 'in_progress') {
            // Kitchen is preparing → update order to Preparing
            $order = DB::table('orders')->where('id', $orderId)->first();
            if ($order && $order->status !== 'Preparing') {
                DB::table('orders')->where('id', $orderId)->update(['status' => 'Preparing']);
                DB::table('order_tracking')->insert([
                    'order_id'   => $orderId,
                    'status'     => 'Preparing',
                    'notes'      => 'Kitchen started preparing the order.',
                    'created_at' => now(),
                ]);
                // Notify customer
                if ($order->user_id) {
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'customer',
                        'receiver_user_id' => $order->user_id,
                        'title'            => 'Order Update: Preparing',
                        'message'          => "Your order #{$orderId} is now being prepared.",
                        'is_read' => false,
                        'created_at'       => now(),
                    ]);
                    // No SMS for Preparing — informational only, visible on tracking page
                }
            }
        }

        if ($new === 'done') {
            $order = DB::table('orders')->where('id', $orderId)->first();
            if ($order) {
                // Pickup order → Pickup status, Delivery order → Out for Delivery
                $nextStatus = $order->fulfillment_type === 'Pickup' ? 'Pickup' : 'Out for Delivery';
                $notes      = $order->fulfillment_type === 'Pickup'
                    ? 'Kitchen completed the order. Ready for pickup.'
                    : 'Kitchen completed the order. Ready for delivery.';

                DB::table('orders')->where('id', $orderId)->update(['status' => $nextStatus]);
                DB::table('order_tracking')->insert([
                    'order_id'   => $orderId,
                    'status'     => $nextStatus,
                    'notes'      => $notes,
                    'created_at' => now(),
                ]);

                // Notify guest via SMS
                $guestPhone = $order->guest_phone ?? null;
                if ($guestPhone) {
                    $siteName  = config('app.name', 'Cake Shop');
                    $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
                    $header    = SmsHelper::header($siteName, $shopName);
                    $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
                    $name      = $order->guest_name ?? 'Customer';
                    $sms = $order->fulfillment_type === 'Pickup'
                        ? "{$header}\nHi {$name}! Your order is ready!\n\nOrder No.: #{$orderId}{$shopLine}\nStatus: Ready for Pickup\n\nYour cake is now ready for pickup. Please visit our shop at your earliest convenience.\n\nYour Tracking Code: {$order->track_code}"
                        : "{$header}\nHi {$name}! Your order is on its way!\n\nOrder No.: #{$orderId}{$shopLine}\nStatus: Out for Delivery\n\nOur rider is now heading to your location. Please make sure someone is available to receive your order.\n\nYour Tracking Code: {$order->track_code}";
                    SmsHelper::send($guestPhone, $sms);
                }

                // Notify registered customer
                if ($order->user_id) {
                    $siteName  = config('app.name', 'Cake Shop');
                    $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
                    $header    = SmsHelper::header($siteName, $shopName);
                    $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
                    $custName  = DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Customer';
                    $notifMsg  = $order->fulfillment_type === 'Pickup'
                        ? "Your order #{$orderId} is ready for pickup! 🎂"
                        : "Your order #{$orderId} is on its way! 🚴";
                    $smsMsg    = $order->fulfillment_type === 'Pickup'
                        ? "{$header}\nHi {$custName}! 🎂 Your order is ready!\n\nOrder No.: #{$orderId}{$shopLine}\nStatus: Ready for Pickup\n\nYour cake is now ready for pickup. Please visit our shop at your earliest convenience."
                        : "{$header}\nHi {$custName}! 🚴 Your order is on its way!\n\nOrder No.: #{$orderId}{$shopLine}\nStatus: Out for Delivery\n\nOur rider is now heading to your location. Please make sure someone is available to receive your order.";
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'customer',
                        'receiver_user_id' => $order->user_id,
                        'title'            => 'Order Update: ' . $nextStatus,
                        'message'          => $notifMsg,
                        'is_read' => false,
                        'created_at'       => now(),
                    ]);
                    $phone = DB::table('users')->where('id', $order->user_id)->value('phone');
                    if ($phone) SmsHelper::send($phone, $smsMsg);
                }

                // ── Send SMS to Rider (Delivery orders only) ─────────────
                if ($order->fulfillment_type === 'Delivery' && $order->rider_id) {
                    $rider = DB::table('riders')->where('id', $order->rider_id)->first();
                    if ($rider && $rider->phone) {
                        // Generate rider token if not yet set
                        $riderToken = $order->rider_token;
                        if (!$riderToken) {
                            $riderToken = bin2hex(random_bytes(16));
                            DB::table('orders')->where('id', $orderId)->update(['rider_token' => $riderToken]);
                        }

                        $siteName  = config('app.name', 'Cake Shop');
                        $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
                        $header    = SmsHelper::header($siteName, $shopName);
                        $custName  = $order->guest_name
                            ?? DB::table('users')->where('id', $order->user_id)->value('fullname')
                            ?? 'Customer';
                        $custPhone = $order->guest_phone
                            ?? DB::table('users')->where('id', $order->user_id)->value('phone')
                            ?? '';
                        $addr      = $order->delivery_address ?? 'N/A';

                        $riderPin = SmsHelper::generateRiderPin();
                        DB::table('orders')->where('id', $orderId)
                            ->update(['rider_pin' => $riderPin]);

                        $riderSmsSent = SmsHelper::send($rider->phone, SmsHelper::buildRiderSms(
                            $header, $orderId, $custName, $custPhone, $addr,
                            SmsHelper::paymentLine($order), $riderPin, $rider->phone, $riderToken
                        ));
                        DB::table('orders')->where('id', $orderId)
                            ->update(['rider_sms_sent' => (bool) $riderSmsSent]);
                    }
                }
            }
        }

        CakeshopHelper::logActivity($user['id'], 'seller', 'Update Kitchen Ticket', "Ticket #{$id}: {$current} → {$new}");
        $labels = ['in_progress' => 'In Progress (Preparing)', 'done' => 'Done (Out for Delivery)'];
        $baseMsg = "Kitchen ticket updated to: " . ($labels[$new] ?? $new);

        if ($new === 'done' && isset($riderSmsSent)) {
            $baseMsg .= $riderSmsSent
                ? ' — SMS sent to rider.'
                : ' — Warning: SMS to rider was not delivered. The message may have been flagged or the number is unreachable.';
        }

        return back()->with('msg', $baseMsg);
    }

    /** Resend rider SMS (e.g. if previous attempt failed) */
    public function resendRiderSms(string $orderId)
    {
        $shop  = $this->getShop();
        $order = DB::table('orders')->where('id', $orderId)->where('shop_id', $shop->id)->first();
        if (!$order || !$order->rider_id)
            return back()->with('err', 'Order or rider not found.');

        $rider = DB::table('riders')->where('id', $order->rider_id)->first();
        if (!$rider || !$rider->phone)
            return back()->with('err', 'This rider has no phone number on record.');

        if (!$order->rider_token) {
            DB::table('orders')->where('id', $orderId)
                ->update(['rider_token' => bin2hex(random_bytes(16))]);
            $order = DB::table('orders')->where('id', $orderId)->first();
        }

        $siteName  = config('app.name', 'Cake Shop');
        $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
        $header    = SmsHelper::header($siteName, $shopName);
        $custName  = $order->guest_name
            ?? DB::table('users')->where('id', $order->user_id)->value('fullname')
            ?? 'Customer';
        $custPhone = $order->guest_phone
            ?? DB::table('users')->where('id', $order->user_id)->value('phone')
            ?? '';
        $addr = $order->delivery_address ?? 'N/A';

        $riderPin = SmsHelper::generateRiderPin();
        DB::table('orders')->where('id', $orderId)->update(['rider_pin' => $riderPin]);

        $sent = SmsHelper::send($rider->phone, SmsHelper::buildRiderSms(
            $header, $orderId, $custName, $custPhone, $addr,
            SmsHelper::paymentLine($order), $riderPin, $rider->phone, $order->rider_token ?? ''
        ));

        DB::table('orders')->where('id', $orderId)->update(['rider_sms_sent' => (bool) $sent]);

        return back()->with(
            $sent ? 'msg' : 'err',
            $sent ? 'SMS resent to rider successfully.' : 'SMS still could not be sent. Please verify the rider\'s phone number.'
        );
    }

    /** Assign rider then auto-mark kitchen ticket as done */
    public function assignRiderAndDone(Request $request, string $orderId)
    {
        $riderId = $request->input('rider_id');
        if (!$riderId) return back()->with('err','Please select a rider first.');

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order) return back()->with('err','Order not found.');

        // Save rider assignment
        DB::table('orders')->where('id', $orderId)->update(['rider_id' => $riderId]);

        // Find and mark kitchen ticket as done
        $ticket = DB::table('kitchen_tickets')->where('order_id', $orderId)
            ->whereIn('status', ['pending','in_progress'])->first();
        if ($ticket) {
            $fakeRequest = new \Illuminate\Http\Request();
            $fakeRequest->merge(['status' => 'done']);
            return $this->update($fakeRequest, $ticket->id);
        }

        return back()->with('msg', "Rider assigned and order marked as done! ✅");
    }
}
