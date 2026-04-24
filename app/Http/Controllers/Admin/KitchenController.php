<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
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
                'o.user_id','o.fulfillment_type','o.rider_id')
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

        $riders = DB::table('riders')->where('is_active',1)->orderBy('name')->get();

        return view('admin.kitchen', compact('tickets','riders','customImages'));
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
                        'is_read'          => 0,
                        'created_at'       => now(),
                    ]);
                    $phone = DB::table('users')->where('id', $order->user_id)->value('phone');
                    if ($phone) SmsHelper::sendOrderStatus($phone, $orderId, 'Preparing', config('app.name','Cake Shop'));
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
                    $siteName = config('app.name', 'Cake Shop');
                    $name     = $order->guest_name ?? 'Customer';
                    $sms = $order->fulfillment_type === 'Pickup'
                        ? "{$siteName}: Hi {$name}! Your order #{$orderId} is ready for pickup. Your tracking code: {$order->track_code}"
                        : "{$siteName}: Hi {$name}! Your order #{$orderId} is on its way. Your tracking code: {$order->track_code}";
                    SmsHelper::send($guestPhone, $sms);
                }

                // Notify registered customer
                if ($order->user_id) {
                    $msg = $order->fulfillment_type === 'Pickup'
                        ? "Your order #{$orderId} is ready for pickup!"
                        : "Your order #{$orderId} is ready and on its way!";
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'customer',
                        'receiver_user_id' => $order->user_id,
                        'title'            => 'Order Update: ' . $nextStatus,
                        'message'          => $msg,
                        'is_read'          => 0,
                        'created_at'       => now(),
                    ]);
                    $phone = DB::table('users')->where('id', $order->user_id)->value('phone');
                    if ($phone) SmsHelper::send($phone, $msg);
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

                        $siteName   = config('app.name', 'Cake Shop');
                        $custName   = $order->guest_name ?? 'Customer';
                        $custPhone  = $order->guest_phone ?? '';
                        $addr       = $order->address ?? 'N/A';

                        if ($order->payment_method === 'COD') {
                            $paymentInfo = "COLLECT PHP " . number_format($order->total_price, 2) . " cash";
                        } elseif ($order->payment_status === 'Paid') {
                            $paymentInfo = "GCash Paid - No collection needed";
                        } elseif ($order->payment_status === 'Partial Payment') {
                            $remaining   = $order->total_price - ($order->deposit_amount ?? 0);
                            $paymentInfo = "Collect Remaining PHP " . number_format($remaining, 2) . " (GCash deposit paid)";
                        } else {
                            $paymentInfo = "GCash (not yet paid) - PHP " . number_format($order->total_price, 2);
                        }

                        $riderSms = "{$siteName}: Delivery Assignment\n"
                            . "Order #{$orderId}\n"
                            . "Customer: {$custName}\n"
                            . "Phone: {$custPhone}\n"
                            . "Address: {$addr}\n"
                            . "Payment: {$paymentInfo}\n"
                            . "Contact your dispatcher for the delivery portal link. Thank you!";

                        SmsHelper::send($rider->phone, $riderSms);
                    }
                }
            }
        }

        CakeshopHelper::logActivity($user['id'], 'admin', 'Update Kitchen Ticket', "Ticket #{$id}: {$current} → {$new}");
        $labels = ['in_progress' => 'In Progress (Preparing)', 'done' => 'Done (Out for Delivery)'];
        return back()->with('msg', "Kitchen ticket updated to: " . ($labels[$new] ?? $new));
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
