<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403);
        return $shop;
    }

    public function index()
    {
        $shop = $this->getShop();

        $orders = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.shop_id', $shop->id)
            ->select(
                'o.*',
                DB::raw('COALESCE(o.guest_name, u.fullname, "Customer") as fullname'),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'),
                'p.name as product_name', 'p.image_path'
            )
            ->orderByRaw("CASE WHEN o.status IN ('Pending','Pending Review') THEN 0 ELSE 1 END")
            ->orderByDesc('o.id')
            ->get();

        $orderIds    = $orders->pluck('id')->toArray();
        $orderAddons = [];
        $customData  = [];
        if ($orderIds) {
            try {
                $addons = DB::table('order_addons')->whereIn('order_id', $orderIds)->get();
                foreach ($addons as $a) $orderAddons[$a->order_id][] = $a;
                $customs = DB::table('custom_orders')->whereIn('order_id', $orderIds)->get();
                foreach ($customs as $c) $customData[$c->order_id] = $c;
            } catch (\Exception $e) {}
        }

        return view('seller.orders', compact('shop', 'orders', 'orderAddons', 'customData'));
    }

    public function updateStatus(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $order = DB::table('orders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$order) return back()->with('err', 'Order not found.');

        $newStatus = $request->input('status');
        $allowed   = ['Confirmed','Preparing','Ready for Pickup','Out for Delivery','Delivered','Picked Up','Cancelled'];
        if (!in_array($newStatus, $allowed)) return back()->with('err', 'Invalid status.');

        // Cancellation requires reason
        if ($newStatus === 'Cancelled') {
            $request->validate(['cancel_reason' => 'required|string|min:5'],[
                'cancel_reason.required' => 'Please provide a reason for cancellation.',
            ]);
        }

        // Picked Up requires full payment
        if ($newStatus === 'Picked Up' && $order->payment_status !== 'Paid') {
            return back()->with('err', 'Cannot mark as Picked Up — customer still has an unpaid balance. Payment must be completed first.');
        }

        $upd = [
            'status'        => $newStatus,
            'cancel_reason' => $newStatus === 'Cancelled' ? $request->input('cancel_reason') : $order->cancel_reason,
            'updated_at'    => now(),
        ];
        if ($newStatus === 'Picked Up') {
            $upd['delivered_at']     = now()->format('Y-m-d H:i:s');
            $upd['review_requested'] = 1;
            if ($order->payment_method === 'COD' && $order->payment_status !== 'Paid') {
                $upd['payment_status'] = 'Paid';
                $upd['paid_at']        = now()->format('Y-m-d H:i:s');
            }
        }
        DB::table('orders')->where('id', $id)->update($upd);

        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => $newStatus,
            'notes'      => $request->input('notes', ''),
            'created_at' => now(),
        ]);

        // SMS + in-app notification — send only for actionable statuses
        try {
            $siteName  = config('app.name', 'Cake Shop');
            $shopName  = SmsHelper::getShopName($shop->id ?? null);
            $header    = SmsHelper::header($siteName, $shopName);
            $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
            $custName  = $order->guest_name
                ?? DB::table('users')->where('id', $order->user_id)->value('fullname')
                ?? 'Customer';
            $smsMsgs = [
                'Ready for Pickup' => "{$header}\nHi {$custName}! Your order is ready!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Ready for Pickup\n\nYour cake is now ready for pickup. Please visit our shop at your earliest convenience.\n\nYour Tracking Code: {$order->track_code}",
                'Out for Delivery' => "{$header}\nHi {$custName}! Your order is on its way!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Out for Delivery\n\nOur rider is now heading to your location. Please make sure someone is available to receive your order.\n\nYour Tracking Code: {$order->track_code}",
                'Cancelled'        => "{$header}\nHi {$custName}, your order has been cancelled.\n\nOrder No.: #{$id}{$shopLine}\nStatus: Cancelled\n\nIf you have questions or concerns, please contact us through our shop page. We hope to serve you again soon.",
            ];
            $phone = $order->guest_phone ?? DB::table('users')->where('id', $order->user_id)->value('phone');
            if ($phone && isset($smsMsgs[$newStatus])) SmsHelper::send($phone, $smsMsgs[$newStatus]);

            // In-app notification for registered customers
            if ($order->user_id) {
                $notifMsgs = [
                    'Confirmed'        => "Your order #{$id} has been confirmed!",
                    'Preparing'        => "Your order #{$id} is now being prepared.",
                    'Ready for Pickup' => "Your order #{$id} is ready for pickup!",
                    'Out for Delivery' => "Your order #{$id} is on its way!",
                    'Picked Up'        => "Your order #{$id} has been picked up. Enjoy!",
                    'Cancelled'        => "Your order #{$id} has been cancelled.",
                ];
                if (isset($notifMsgs[$newStatus])) {
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'customer',
                        'receiver_user_id' => $order->user_id,
                        'title'            => 'Order Update: ' . $newStatus,
                        'message'          => $notifMsgs[$newStatus],
                        'is_read'          => 0,
                        'created_at'       => now(),
                    ]);
                }
                if (in_array($newStatus, ['Picked Up', 'Delivered'])) {
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'customer',
                        'receiver_user_id' => $order->user_id,
                        'title'            => 'Rate Your Order #' . $id,
                        'message'          => "How was your cake? Please leave a rating for Order #{$id}!",
                        'is_read'          => 0,
                        'created_at'       => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {}

        return back()->with('msg', "Order status updated to {$newStatus}.");
    }

    public function assignRider(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $order = DB::table('orders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$order) return back()->with('err', 'Order not found.');

        $validated = $request->validate([
            'rider_id' => 'required|exists:riders,id',
        ],[
            'rider_id.required' => 'Please select a rider.',
        ]);

        $rider = DB::table('riders')->where('id', $validated['rider_id'])
            ->where('shop_id', $shop->id)->first();
        if (!$rider) return back()->with('err', 'Rider not found.');

        DB::table('orders')->where('id', $id)->update([
            'rider_id'   => $rider->id,
            'status'     => 'Out for Delivery',
            'updated_at' => now(),
        ]);
        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => 'Out for Delivery',
            'notes'      => "Assigned to rider: {$rider->name}",
            'created_at' => now(),
        ]);

        // SMS rider
        if ($rider->phone) {
            try {
                $riderToken = $order->rider_token;
                if (!$riderToken) {
                    $riderToken = bin2hex(random_bytes(16));
                    DB::table('orders')->where('id', $id)->update(['rider_token' => $riderToken]);
                }
                $siteName    = config('app.name', 'Cake Shop');
                $shopName    = SmsHelper::getShopName($shop->id ?? null);
                $header      = SmsHelper::header($siteName, $shopName);
                $custName    = $order->guest_name ?? DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Customer';
                $custPhone   = $order->guest_phone ?? DB::table('users')->where('id', $order->user_id)->value('phone') ?? '';
                $addr        = $order->delivery_address ?? 'N/A';
                $paymentInfo = $order->payment_method === 'COD'
                    ? "COLLECT PHP " . number_format($order->total_price, 2) . " cash"
                    : ($order->payment_status === 'Paid' ? "GCash Paid - No collection needed" : "GCash (not yet paid) - PHP " . number_format($order->total_price, 2));
                SmsHelper::send($rider->phone,
                    "{$header}\n"
                    . "New Delivery Assignment\n\n"
                    . "Order No.: #{$id}\n"
                    . "Customer: {$custName}\n"
                    . "Phone: {$custPhone}\n"
                    . "Address: {$addr}\n"
                    . "Payment: {$paymentInfo}\n\n"
                    . "Contact your dispatcher for the delivery portal link to update the status. Thank you!"
                );
            } catch (\Exception $e) {}
        }

        return back()->with('msg', "Rider assigned. Order is now Out for Delivery.");
    }
}
