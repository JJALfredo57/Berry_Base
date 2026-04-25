<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->select('o.*',
                DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'),
                DB::raw("COALESCE(u.username, 'Guest') as username"),
                DB::raw("COALESCE(u.email, '') as email"),
                'p.name as product_name', 'p.image_path', 'p.price')
            ->orderByRaw("CASE WHEN o.status IN ('Pending','Pending Review') THEN 0 ELSE 1 END")
            ->orderByDesc('o.id')
            ->get();

        // Load add-ons per order
        $orderIds = $orders->pluck('id')->toArray();
        $orderAddons = [];
        $orderReviews = [];
        if ($orderIds) {
            try {
                $addonRows = DB::table('order_addons')->whereIn('order_id', $orderIds)->get();
                foreach ($addonRows as $a) $orderAddons[$a->order_id][] = $a;
                $reviewRows = DB::table('order_reviews')->whereIn('order_id', $orderIds)->get();
                foreach ($reviewRows as $r) $orderReviews[$r->order_id] = $r;
            } catch (\Exception $e) {}
        }

        $pendingCancelCount = $orders->where('cancel_status', 'pending')->count();

        return view('admin.orders', compact('orders','pendingCancelCount','orderAddons','orderReviews'));
    }

    /** Admin confirms order — only allowed after deposit is paid */
    public function confirmOrder(Request $request, string $id)
    {
        $user  = session('user');
        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->where('o.id', $id)
            ->select('o.*', 'p.name as product_name',
                DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'))
            ->first();

        if (!$order) return back()->with('err', 'Order not found.');
        if (!in_array($order->status, ['Pending','Pending Review']))
            return back()->with('err', 'Order is already confirmed or processed.');

        if ($order->deposit_required && $order->deposit_status !== 'paid')
            return back()->with('err', 'Cannot confirm — deposit has not been paid yet.');

        DB::table('orders')->where('id', $id)->update(['status' => 'Confirmed']);
        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => 'Confirmed',
            'notes'      => 'Order confirmed by admin.',
            'created_at' => now(),
        ]);

        if (!$order->kitchen_sent) $this->doSendToKitchen($id, $order, $user);

        // No SMS on Confirmed — customer already got the order placed SMS

        CakeshopHelper::logActivity($user['id'], 'admin', 'Confirm Order', "Order #{$id}");
        return back()->with('msg', "Order #{$id} confirmed and sent to kitchen! ✅");
    }

    /** Assign rider to a delivery order */
    public function assignRider(Request $request, string $id)
    {
        $riderId = $request->input('rider_id');
        $order   = DB::table('orders')->where('id',$id)->first();
        if (!$order) return back()->with('err','Order not found.');
        if ($order->fulfillment_type !== 'Delivery')
            return back()->with('err','This is a Pickup order — no rider needed.');

        DB::table('orders')->where('id',$id)->update(['rider_id' => $riderId ?: null]);
        $riderName = $riderId ? DB::table('riders')->where('id',$riderId)->value('name') : 'None';
        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Assign Rider', "Order #{$id} → Rider: {$riderName}");
        return back()->with('msg', $riderId ? "Rider {$riderName} assigned to Order #{$id}. ✅" : "Rider unassigned from Order #{$id}.");
    }

    /** Admin requests deposit from guest before confirming */
    public function requestDeposit(Request $request, string $id)
    {
        $user  = session('user');
        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) return back()->with('err', 'Order not found.');
        if (!in_array($order->status, ['Pending','Pending Review']))
            return back()->with('err', 'Can only request deposit on pending orders.');
        if ($order->deposit_status === 'paid')
            return back()->with('err', 'Deposit has already been paid.');

        $amount  = (float) $request->input('deposit_amount', round($order->total_price * 0.5, 2));
        $message = trim($request->input('deposit_message', ''));

        if ($amount <= 0 || $amount > $order->total_price)
            return back()->with('err', 'Invalid deposit amount.');

        DB::table('orders')->where('id', $id)->update([
            'deposit_required'  => 1,
            'deposit_amount'    => $amount,
            'deposit_status'    => 'pending',
            'deposit_message'   => $message ?: null,
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => 'Awaiting Deposit',
            'notes'      => "Admin requested deposit of ₱{$amount}.",
            'created_at' => now(),
        ]);

        // Send SMS to guest with deposit payment link
        $phone = $order->guest_phone ?? null;
        if ($phone) {
            $siteName  = config('app.name', 'Cake Shop');
            $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
            $header    = SmsHelper::header($siteName, $shopName);
            $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
            $name      = $order->guest_name ?? 'Customer';
            $customMsg = $message ? "\n{$message}" : '';
            $sms = "{$header}\n"
                . "Hi {$name}! Action needed for your order.\n\n"
                . "Order No.: #{$id}{$shopLine}\n"
                . "Deposit Amount: PHP " . number_format($amount, 2) . "\n\n"
                . "To proceed with your order, please complete your deposit payment.{$customMsg}\n\n"
                . "Your Tracking Code: {$order->track_code}\n"
                . "Visit our website and use your tracking code to pay your deposit.\n\n"
                . "Note: Do not send payment to any personal GCash number.";
            SmsHelper::send($phone, $sms);
        }

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => "Deposit Requested — Order #{$id}",
            'message'          => "Deposit of ₱{$amount} requested from " . ($order->guest_name ?? 'Guest') . ".",
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        CakeshopHelper::logActivity($user['id'], 'admin', 'Request Deposit', "Order #{$id} — ₱{$amount}");
        return back()->with('msg', "Deposit request sent! SMS delivered to customer. ✅");
    }

    public function updateStatus(Request $request, string $id)
    {
        $user    = session('user');
        $status  = trim($request->input('status'));

        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) return redirect()->route('admin.orders.index')->with('err', 'Order not found.');

        $isPickup = $order->fulfillment_type === 'Pickup';

        // ── Allowed statuses ──────────────────────────────────────────────
        $allowed = ['Pending','Confirmed','Preparing','Pickup','Out for Delivery',
                    'Delivered','Picked Up','Cancelled'];
        if (!in_array($status, $allowed))
            return redirect()->route('admin.orders.index')->with('err', 'Invalid status.');

        // ── Waterfall: Pickup vs Delivery ─────────────────────────────────
        $waterfall = [
            'Pending'          => ['Confirmed','Cancelled'],
            'Pending Review'   => ['Confirmed','Cancelled'],
            'Confirmed'        => ['Preparing','Cancelled'],
            'Preparing'        => $isPickup ? ['Pickup','Cancelled'] : ['Out for Delivery','Cancelled'],
            'Out for Delivery' => $isPickup ? ['Pickup','Picked Up','Cancelled'] : ['Delivered','Cancelled'],
            'Pickup'           => ['Picked Up','Cancelled'],
            'Delivered'        => [],
            'Picked Up'        => [],
            'Cancelled'        => [],
        ];
        $current     = $order->status;
        $allowedNext = $waterfall[$current] ?? [];
        if (!in_array($status, $allowedNext))
            return redirect()->route('admin.orders.index')
                ->with('err', "Cannot change status from '{$current}' to '{$status}'.");

        // ── Block admin from marking Delivery orders as Delivered ─────────
        // Only the rider can mark Delivery orders as Delivered via rider page
        if ($status === 'Delivered' && !$isPickup)
            return back()->with('err', 'Delivery orders can only be marked as Delivered by the rider.');
        $finalStatuses = ['Delivered','Picked Up'];
        if (in_array($status, $finalStatuses)
            && $order->payment_method === 'GCash'
            && $order->payment_status !== 'Paid')
            return back()->with('err', "Cannot mark as {$status} — GCash payment is not yet completed.");

        // ── Build update ──────────────────────────────────────────────────
        $upd = ['status' => $status];
        if (in_array($status, $finalStatuses)) {
            $upd['delivered_at']     = now()->format('Y-m-d H:i:s');
            $upd['review_requested'] = 1;
            // COD → auto Paid on final status
            if ($order->payment_method === 'COD' && $order->payment_status !== 'Paid') {
                $upd['payment_status'] = 'Paid';
                $upd['paid_at']        = now()->format('Y-m-d H:i:s');
            }
        }

        DB::table('orders')->where('id', $id)->update($upd);

        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => $status,
            'notes'      => $request->input('notes') ?? '',
            'created_at' => now(),
        ]);

        // ── Auto-send to kitchen when Confirmed ───────────────────────────
        if ($status === 'Confirmed' && !$order->kitchen_sent)
            $this->doSendToKitchen($id, $order, $user);

        // ── Generate rider token + SMS when Out for Delivery ──────────────
        if ($status === 'Out for Delivery' && $order->fulfillment_type === 'Delivery') {
            $token = bin2hex(random_bytes(16));
            DB::table('orders')->where('id',$id)->update(['rider_token' => $token]);
            $order->rider_token = $token;

            if ($order->rider_id) {
                $rider      = DB::table('riders')->where('id',$order->rider_id)->first();
                $riderPhone = $rider->phone ?? null;
                if ($riderPhone) {
                    $custName  = $order->guest_name ?? 'Customer';
                    $custPhone = $order->guest_phone ?? '';
                    $addr      = $order->delivery_address ?? 'See link';
                    $payment   = $order->payment_method === 'COD'
                        ? "COLLECT \u{20B1}" . number_format($order->total_price, 2) . " cash"
                        : ($order->payment_status === 'Paid' ? "GCash Paid \u{2713} — No collection needed" : "GCash (not yet paid) — \u{20B1}" . number_format($order->total_price, 2));
                    $siteName  = config('app.name', 'Cake Shop');
                    $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
                    $header    = SmsHelper::header($siteName, $shopName);
                    SmsHelper::send($riderPhone,
                        "{$header}\n"
                        . "New Delivery Assignment\n\n"
                        . "Order No.: #{$id}\n"
                        . "Customer: {$custName}\n"
                        . "Phone: {$custPhone}\n"
                        . "Address: {$addr}\n"
                        . "Payment: {$payment}\n\n"
                        . "Please contact your dispatcher for the delivery portal link to update the status. Thank you!"
                    );
                }
            }
        }

        // ── SMS to guest (no account) — send only for actionable statuses ───
        $guestPhone = $order->guest_phone ?? null;
        if ($guestPhone) {
            $siteName  = config('app.name', 'Cake Shop');
            $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
            $header    = SmsHelper::header($siteName, $shopName);
            $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
            $name      = $order->guest_name ?? 'Customer';
            $payNote = $order->payment_method === 'GCash' ? "\nGCash payment: visit our website using your tracking code to pay." : '';
            $guestSms  = match($status) {
                'Out for Delivery' => "{$header}\nHi {$name}! Your order is on its way!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Out for Delivery\n\nOur rider is now heading to your location. Please make sure someone is available to receive your order.\n\nYour Tracking Code: {$order->track_code}{$payNote}",
                'Pickup'           => "{$header}\nHi {$name}! Your order is ready!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Ready for Pickup\n\nYour cake is now ready for pickup. Please visit our shop at your earliest convenience.\n\nYour Tracking Code: {$order->track_code}{$payNote}",
                'Cancelled'        => "{$header}\nHi {$name}, your order has been cancelled.\n\nOrder No.: #{$id}{$shopLine}\nStatus: Cancelled\n\nIf you have questions or concerns, please contact us through our shop page. We hope to serve you again soon.",
                default            => null,
            };
            if ($guestSms) SmsHelper::send($guestPhone, $guestSms);
        }

        // ── Notify registered customer — send only for actionable statuses ─
        $custId = $order->user_id ?? null;
        if ($custId) {
            $siteName  = config('app.name', 'Cake Shop');
            $shopName  = SmsHelper::getShopName($order->shop_id ?? null);
            $header    = SmsHelper::header($siteName, $shopName);
            $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
            $custName  = DB::table('users')->where('id', $custId)->value('fullname') ?? 'Customer';
            // In-app notification messages (short)
            $notifMessages = [
                'Confirmed'        => "Your order #{$id} has been confirmed! We'll start preparing it soon.",
                'Preparing'        => "Your order #{$id} is now being prepared.",
                'Out for Delivery' => "Your order #{$id} is on its way! 🚴 Our rider is heading to your location.",
                'Pickup'           => "Your order #{$id} is ready for pickup! 🎂",
                'Delivered'        => "Your order #{$id} has been delivered. Enjoy!",
                'Picked Up'        => "Your order #{$id} has been picked up. Enjoy!",
                'Cancelled'        => "Your order #{$id} has been cancelled.",
            ];
            DB::table('notifications')->insert([
                'receiver_role'    => 'customer',
                'receiver_user_id' => $custId,
                'title'            => 'Order Update: ' . $status,
                'message'          => $notifMessages[$status] ?? "Order #{$id} updated to {$status}.",
                'is_read'          => 0,
                'created_at'       => now(),
            ]);

            // SMS — send only for actionable statuses
            $smsCustMessages = [
                'Out for Delivery' => "{$header}\nHi {$custName}! 🚴 Your order is on its way!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Out for Delivery\n\nOur rider is now heading to your location. Please make sure someone is available to receive your order.",
                'Pickup'           => "{$header}\nHi {$custName}! 🎂 Your order is ready!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Ready for Pickup\n\nYour cake is now ready for pickup. Please visit our shop at your earliest convenience.",
                'Cancelled'        => "{$header}\nHi {$custName}, your order has been cancelled.\n\nOrder No.: #{$id}{$shopLine}\nStatus: Cancelled\n\nIf you have questions or concerns, please contact us through our shop page. We hope to serve you again soon.",
            ];
            $custPhone = DB::table('users')->where('id', $custId)->value('phone');
            if ($custPhone && isset($smsCustMessages[$status])) SmsHelper::send($custPhone, $smsCustMessages[$status]);

            if (in_array($status, $finalStatuses)) {
                DB::table('notifications')->insert([
                    'receiver_role'    => 'customer',
                    'receiver_user_id' => $custId,
                    'title'            => '⭐ Rate Your Order #' . $id,
                    'message'          => "How was your cake? Please leave a rating for Order #{$id}!",
                    'is_read'          => 0,
                    'created_at'       => now(),
                ]);
            }
        }

        CakeshopHelper::logActivity($user['id'], $user['role'], 'Update Order Status', "Order #{$id} => {$status}");
        return redirect()->route('admin.orders.index')->with('msg', "Order #{$id} updated to {$status}.");
    }

    /** Shared kitchen-send logic (used by updateStatus and sendToKitchen) */
    private function doSendToKitchen(string $id, object $order, array $user): void
    {
        $addons    = DB::table('order_addons')->where('order_id', $id)->get();
        $addonList = $addons->count() > 0
            ? "\nADD-ONS:\n" . $addons->map(fn($a) => "  • {$a->addon_name}" . ($a->addon_price > 0 ? " (+₱{$a->addon_price})" : " (FREE)"))->implode("\n")
            : '';

        $sizeInfo  = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
        $noteInfo  = $order->custom_note   ? "\nSPECIAL NOTE: {$order->custom_note}" : '';
        $schedInfo = $order->schedule_date
            ? "\nSCHEDULE: " . date('M d, Y', strtotime($order->schedule_date)) .
              ($order->schedule_time ? ' at ' . date('g:i A', strtotime($order->schedule_time)) : '')
            : '';

        // Support both guest and registered customer orders
        $productName = $order->product_name
            ?? DB::table('products')->where('id', $order->product_id)->value('name')
            ?? 'Custom Cake';

        $fullname = $order->guest_name
            ?? $order->fullname
            ?? DB::table('users')->where('id', $order->user_id)->value('fullname')
            ?? 'Guest';

        $phone = $order->guest_phone
            ?? $order->phone
            ?? DB::table('users')->where('id', $order->user_id)->value('phone')
            ?? '';

        $fulfillment = $order->fulfillment_type ?? 'Pickup';

        $instructions = "=== KITCHEN ORDER TICKET ===\n" .
            "Order #: {$id}\n" .
            "Customer: {$fullname}" . ($phone ? " ({$phone})" : '') . "\n" .
            "Product: {$productName}\n" .
            "Qty: {$order->quantity}" .
            $sizeInfo . $noteInfo . $addonList . $schedInfo .
            "\nFulfillment: {$fulfillment}" .
            "\n===========================";

        DB::table('kitchen_tickets')->where('order_id', $id)->delete();
        DB::table('kitchen_tickets')->insert([
                        'shop_id'       => $order->shop_id ?? null,
            'order_id'     => $id,
            'product_name' => $productName,
            'product_image'=> $order->product_image ?? null,
            'quantity'     => $order->quantity ?? 1,
                        'instructions' => $instructions,
            'status'       => 'pending',
            'sent_at'      => now()->format('Y-m-d H:i:s'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        DB::table('orders')->where('id', $id)->update(['kitchen_sent' => 1]);
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Send to Kitchen', "Order #{$id}");
    }


    public function sendToKitchen(Request $request, string $id)
    {
        $user  = session('user');
        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->where('o.id', $id)
            ->select('o.*', 'p.name as product_name',
                DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'))
            ->first();

        if (!$order) return back()->with('err', 'Order not found.');

        $this->doSendToKitchen($id, $order, $user);
        return back()->with('msg', "Order #{$id} sent to kitchen successfully!");
    }


    public function acceptCancel(Request $request, string $id)
    {
        $user      = session('user');
        $order     = DB::table('orders')->where('id', $id)->first();
        if (!$order) return back()->with('err', 'Order not found.');
        $adminNote = trim($request->input('admin_note', 'Cancel request approved.'));

        DB::table('orders')->where('id', $id)->update([
            'status'           => 'Cancelled',
            'cancel_status'    => 'accepted',
            'cancel_admin_note'=> $adminNote,
        ]);
        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => 'Cancelled',
            'notes'      => 'Cancel request accepted. ' . $adminNote,
            'created_at' => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'customer',
            'receiver_user_id' => $order->user_id,
            'title'            => 'Cancel Approved — Order #' . $id,
            'message'          => "Your cancel request for Order #{$id} was approved. {$adminNote}",
            'is_read'          => 0,
            'created_at'       => now(),
        ]);
        DB::table('messages')->insert([
            'order_id'    => $id,
            'sender_role' => 'admin',
            'sender_id'   => $user['id'],
            'message'     => "Cancel request approved.\n\n{$adminNote}",
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        // SMS notification
        $custPhone = DB::table('users')->where('id', $order->user_id)->value('phone');
        if ($custPhone) {
            $siteName = config('app.name', 'Cake Shop');
            $shopName = SmsHelper::getShopName($order->shop_id ?? null);
            $header   = SmsHelper::header($siteName, $shopName);
            $shopLine = $shopName ? "\nShop: {$shopName}" : '';
            $custName = DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Customer';
            SmsHelper::send($custPhone,
                "{$header}\n"
                . "Hi {$custName}! Your cancellation request has been approved.\n\n"
                . "Order No.: #{$id}{$shopLine}\n\n"
                . "{$adminNote}\n\n"
                . "Thank you for your patience. We hope to serve you again soon."
            );
        }

        CakeshopHelper::logActivity($user['id'], $user['role'], 'Accept Cancel', "Order #{$id}");
        return back()->with('msg', "Order #{$id} cancel approved.");
    }

    public function rejectCancel(Request $request, string $id)
    {
        $user      = session('user');
        $order     = DB::table('orders')->where('id', $id)->first();
        if (!$order) return back()->with('err', 'Order not found.');
        $adminNote = trim($request->input('admin_note', ''));
        if (!$adminNote) return back()->with('err', 'Please provide a reason for rejection.');

        DB::table('orders')->where('id', $id)->update([
            'cancel_status'    => 'rejected',
            'cancel_admin_note'=> $adminNote,
        ]);
        DB::table('notifications')->insert([
            'receiver_role'    => 'customer',
            'receiver_user_id' => $order->user_id,
            'title'            => '❌ Cancel Rejected — Order #' . $id,
            'message'          => "Your cancel request for Order #{$id} was rejected. Reason: {$adminNote}",
            'is_read'          => 0,
            'created_at'       => now(),
        ]);
        DB::table('messages')->insert([
                        'sender_role' => 'admin',
            'sender_id'   => $user['id'],
            'message'     => "❌ Cancel request rejected.\n\nReason: {$adminNote}",
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        $custPhone = DB::table('users')->where('id', $order->user_id)->value('phone');
        if ($custPhone) {
            $siteName = config('app.name', 'Cake Shop');
            $shopName = SmsHelper::getShopName($order->shop_id ?? null);
            $header   = SmsHelper::header($siteName, $shopName);
            $shopLine = $shopName ? "\nShop: {$shopName}" : '';
            $custName = DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Customer';
            SmsHelper::send($custPhone,
                "{$header}\n"
                . "Hi {$custName}, your cancellation request was not approved.\n\n"
                . "Order No.: #{$id}{$shopLine}\n"
                . "Reason: {$adminNote}\n\n"
                . "If you have further concerns, please contact us through our shop page."
            );
        }

        CakeshopHelper::logActivity($user['id'], $user['role'], 'Reject Cancel', "Order #{$id}");
        return back()->with('msg', "Order #{$id} cancel rejected.");
    }
}
