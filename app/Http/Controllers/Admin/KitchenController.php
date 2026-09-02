<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use App\Services\MobileNotificationService;
use App\Services\RiderAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    public function index()
    {
        $tickets = DB::table('kitchen_tickets as kt')
            ->join('orders as o','o.id','=','kt.order_id')
            ->leftJoin('users as u','u.id','=','o.user_id')
            ->join('products as p','p.id','=','o.product_id')
            ->select('kt.*','kt.status as ticket_status','o.id as order_id',
                \Illuminate\Support\Facades\DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                'p.name as product_name','p.image_path as product_image',
                'o.user_id','o.fulfillment_type','o.rider_id','o.rider_sms_sent')
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

        $riders = DB::table('riders')->where('is_active', true)->orderBy('name')->get();

        return view('admin.kitchen', compact('tickets','riders','customImages'));
    }

    public function update(Request $request, string $id)
    {
        $user   = session('user');
        $ticket = DB::table('kitchen_tickets')->where('id', $id)->first();
        if (!$ticket) return back()->with('err', 'Ticket not found.');

        $current = $ticket->status;
        $new     = $request->input('status', '');

        $allowed = [];
        if ($current === 'pending') $allowed = ['in_progress'];
        if ($current === 'in_progress') $allowed = ['done'];
        if ($current === 'done') $allowed = [];

        if (!in_array($new, $allowed, true)) {
            return back()->with('err', "Cannot change kitchen status from '{$current}' to '{$new}'.");
        }

        DB::table('kitchen_tickets')->where('id', $id)->update(['status' => $new]);
        $orderId = $ticket->order_id;

        if ($new === 'in_progress') {
            $order = DB::table('orders')->where('id', $orderId)->first();
            if ($order && $order->status !== 'Preparing') {
                DB::table('orders')->where('id', $orderId)->update(['status' => 'Preparing']);
                DB::table('order_tracking')->insert([
                    'order_id' => $orderId,
                    'status' => 'Preparing',
                    'notes' => 'Kitchen started preparing the order.',
                    'created_at' => now(),
                ]);
            }
            if ($order) {
                app(MobileNotificationService::class)->notifyOrderCustomer(
                    $order,
                    'Order Update: Preparing',
                    "Your order #{$orderId} is now being prepared.",
                    ['event' => 'kitchen_update']
                );
            }
        }

        if ($new === 'done') {
            $order = DB::table('orders')->where('id', $orderId)->first();
            if ($order) {
                $nextStatus = $order->fulfillment_type === 'Pickup' ? 'Pickup' : 'Out for Delivery';
                $notes = $order->fulfillment_type === 'Pickup'
                    ? 'Kitchen completed the order. Ready for pickup.'
                    : 'Kitchen completed the order. Ready for delivery.';

                DB::table('orders')->where('id', $orderId)->update(['status' => $nextStatus]);
                DB::table('order_tracking')->insert([
                    'order_id' => $orderId,
                    'status' => $nextStatus,
                    'notes' => $notes,
                    'created_at' => now(),
                ]);

                $message = $order->fulfillment_type === 'Pickup'
                    ? "Your order #{$orderId} is ready for pickup."
                    : "Your order #{$orderId} is on its way.";
                $sms = $this->customerStatusSms($order, $nextStatus);
                app(MobileNotificationService::class)->notifyOrderCustomer($order, 'Order Update: ' . $nextStatus, $message, ['event' => 'kitchen_update'], $sms);

                if ($order->fulfillment_type === 'Delivery' && $order->rider_id) {
                    $rider = DB::table('riders')->where('id', $order->rider_id)->where('is_active', true)->first();
                    if ($rider) {
                        $assignment = app(RiderAssignmentService::class)->assign($order, $rider, null, 'admin');
                        $riderNotifyChannel = $assignment['notice']['channel'] ?? 'none';
                    }
                }
            }
        }

        CakeshopHelper::logActivity($user['id'], 'admin', 'Update Kitchen Ticket', "Ticket #{$id}: {$current} -> {$new}");
        $labels = ['in_progress' => 'In Progress (Preparing)', 'done' => 'Done (Out for Delivery)'];
        $baseMsg = 'Kitchen ticket updated to: ' . ($labels[$new] ?? $new);

        if ($new === 'done' && isset($riderNotifyChannel)) {
            $baseMsg .= match ($riderNotifyChannel) {
                'push' => ' - app notification sent to rider.',
                'sms' => ' - SMS fallback sent to rider.',
                default => ' - warning: no rider notification channel was reached.',
            };
        }

        return back()->with('msg', $baseMsg);
    }

    private function customerStatusSms(object $order, string $status): ?string
    {
        if (empty($order->guest_phone) && empty($order->user_id)) {
            return null;
        }

        $siteName = config('app.name', 'Cake Shop');
        $shopName = SmsHelper::getShopName($order->shop_id ?? null);
        $header = SmsHelper::header($siteName, $shopName);
        $shopLine = $shopName ? "\nShop: {$shopName}" : '';
        $name = $order->guest_name
            ?? (!empty($order->user_id) ? DB::table('users')->where('id', $order->user_id)->value('fullname') : null)
            ?? 'Customer';

        if ($status === 'Pickup') {
            return "{$header}\nHi {$name}! Your order is ready!\n\nOrder No.: #{$order->id}{$shopLine}\nStatus: Ready for Pickup\n\nYour cake is now ready for pickup. Please visit our shop at your earliest convenience.";
        }

        if ($status === 'Out for Delivery') {
            return "{$header}\nHi {$name}! Your order is on its way!\n\nOrder No.: #{$order->id}{$shopLine}\nStatus: Out for Delivery\n\nOur rider is now heading to your location. Please make sure someone is available to receive your order.";
        }

        return null;
    }

    /** Resend rider SMS (e.g. if previous attempt failed) */
    public function resendRiderSms(string $orderId)
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
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
        $addr = $order->delivery_address ?? $order->address ?? 'N/A';

        $riderPin = SmsHelper::generateRiderPin();
        DB::table('orders')->where('id', $orderId)->update(['rider_pin' => $riderPin]);

        $riderSms = SmsHelper::buildRiderSms(
            $header, $orderId, $custName, $custPhone, $addr,
            SmsHelper::paymentLine($order), $riderPin, $rider->phone, $order->rider_token ?? ''
        );
        $riderUrl = route('rider.show', [$orderId, $order->rider_token ?? ''], false);
        $result = app(MobileNotificationService::class)->notifyRider(
            (int) $rider->id,
            $rider->phone,
            'Delivery Reminder',
            "Order #{$orderId} delivery details are ready.",
            ['event' => 'rider_resend', 'order_id' => (string) $orderId, 'url' => $riderUrl],
            $riderSms,
            $riderUrl
        );
        $ok = (int) ($result['push_sent'] ?? 0) > 0 || (bool) ($result['sms_sent'] ?? false);

        DB::table('orders')->where('id', $orderId)->update(['rider_sms_sent' => $ok]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], 'admin', 'Resend Rider Notification', "Order #{$orderId}: channel " . ($result['channel'] ?? 'none'));

        if ($ok) {
            return back()->with('msg', 'Notification sent to rider ' . $rider->name . ' (' . $rider->phone . ') successfully.');
        }

        return back()->with('err', 'Rider notification could not be sent. Please try again or contact support.');
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
