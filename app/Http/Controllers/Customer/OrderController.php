<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $uid    = session('user')['id'];
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'All');

        $orders = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.user_id', $uid)
            ->select('o.*', 'p.name as product_name', 'p.image_path')
            ->when($search, fn($q) => $q->where(fn($sq) => $sq
                ->where('o.id', 'like', "%$search%")
                ->orWhere('p.name', 'like', "%$search%")
            ))
            ->when($status && $status !== 'All', fn($q) => $q->where('o.status', $status))
            ->orderByDesc('o.id')
            ->paginate(10)
            ->withQueryString();

        $orderIds = collect($orders->items())->pluck('id')->toArray();
        $tracking = [];
        $orderAddons = [];
        $orderReviews = [];
        $customOrderData = [];

        if ($orderIds) {
            $rows = DB::table('order_tracking')->whereIn('order_id', $orderIds)->orderBy('created_at')->get();
            foreach ($rows as $t) $tracking[$t->order_id][] = $t;
            try {
                $addonRows = DB::table('order_addons')->whereIn('order_id', $orderIds)->orderBy('id')->get();
                foreach ($addonRows as $a) $orderAddons[$a->order_id][] = $a;
            } catch (\Exception $e) {}
            try {
                $reviewRows = DB::table('order_reviews')->whereIn('order_id', $orderIds)->get();
                foreach ($reviewRows as $r) $orderReviews[$r->order_id] = $r;
            } catch (\Exception $e) {}
            try {
                $coRows = DB::table('custom_orders')->whereIn('order_id', $orderIds)->get();
                foreach ($coRows as $co) $customOrderData[$co->order_id] = $co;
            } catch (\Exception $e) {}
        }

        return view('customer.orders', compact('orders','tracking','orderAddons','orderReviews','customOrderData','search','status'));
    }

    public function requestCancel(Request $request, string $id)
    {
        $uid    = session('user')['id'];
        $reason = trim($request->input('cancel_reason', ''));

        if (!$reason) return back()->with('error', 'Please provide a reason for cancellation.');

        $order = DB::table('orders')->where('id', $id)->where('user_id', $uid)->first();
        if (!$order) return back()->with('error', 'Order not found.');

        $hasPaidDeposit = ($order->deposit_status ?? null) === 'paid'
            || in_array(($order->payment_status ?? ''), ['Partial Payment', 'Paid'], true);
        if ($hasPaidDeposit) {
            return back()->with('error', 'Cannot cancel this order because your deposit has already been paid.');
        }

        $notAllowed = ['Preparing','Out for Delivery','Delivered','Cancelled'];
        if (in_array($order->status, $notAllowed)) {
            return back()->with('error', "Cannot cancel — status is already '\1'.");
        }

        if ($order->cancel_requested && $order->cancel_status === 'pending') {
            return back()->with('error', 'You already have a pending cancel request for this order.');
        }

        DB::table('orders')->where('id', $id)->update([
            'cancel_requested'    => 1,
            'cancel_reason'       => $reason,
            'cancel_status'       => 'pending',
            'cancel_admin_note'   => null,
            'cancel_requested_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $custName = session('user')['fullname'] ?? 'Customer';
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => '❌ Cancel Request — Order #' . $id,
            'message'          => "{$custName} wants to cancel Order #{$id}. Reason: {$reason}",
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        DB::table('messages')->insert([
                        'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => "❌ Cancel Request submitted.\n\nReason: {$reason}",
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'Cancel Request', "Order #{$id} — {$reason}");
        return back()->with('msg', 'Cancel request submitted. Waiting for admin approval.');
    }

    /** Customer accepts the admin-set price → notify admin to proceed */
    public function acceptPrice(string $coId)
    {
        $uid = session('user')['id'];
        $co  = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'pending') return back()->with('err', 'Price already responded to.');

        // Mark price as accepted — but DO NOT confirm yet, wait for deposit payment
        DB::table('custom_orders')->where('id', $coId)->update([
            'price_confirmed'       => 'accepted',
            'customer_confirmed_at' => now(),
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => 'Pending',
            'notes'      => 'Customer accepted the final price of ₱' . number_format($co->admin_price, 2) . '. Waiting for deposit payment.',
            'created_at' => now(),
        ]);

        $custName = session('user')['fullname'] ?? 'Customer';
        DB::table('messages')->insert([
            'order_id'    => $co->order_id,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => "I accept the final price of PHP " . number_format($co->admin_price, 2) . ". I will proceed with the deposit payment.",
            'is_read'     => 0,
            'created_at'  => now(),
        ]);
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Custom Order #' . $co->order_id . ' - Price Accepted',
            'message'          => "{$custName} accepted PHP " . number_format($co->admin_price, 2) . " for Custom Order #{$co->order_id}. Waiting for deposit payment.",
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'Accept Custom Price', "Custom Order #{$coId}");
        return back()->with('msg', '✅ Price accepted! Please proceed with your deposit payment to confirm your order. 🎂');
    }

    /** Customer sets deposit amount for custom order (min 50%) */
    public function setCustomDeposit(Request $request, string $coId)
    {
        $uid = session('user')['id'];
        $co  = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'accepted') return back()->with('err', 'Please accept the price first.');

        $order = DB::table('orders')->where('id', $co->order_id)->first();
        if (!$order) return back()->with('err', 'Order not found.');
        if ($order->payment_status === 'Paid') return back()->with('err', 'This order is already fully paid.');

        $totalPrice    = (float) $co->admin_price;
        $minDeposit    = round($totalPrice * 0.5, 2);
        $depositAmount = round((float) $request->input('deposit_amount', $minDeposit), 2);

        if ($depositAmount < $minDeposit)
            return back()->with('err', 'Minimum deposit is 50% of total (₱' . number_format($minDeposit, 2) . ').');
        if ($depositAmount > $totalPrice)
            $depositAmount = $totalPrice;

        $isFullPayment = abs($depositAmount - $totalPrice) < 0.01;

        // Save deposit info on order
        DB::table('orders')->where('id', $co->order_id)->update([
            'deposit_required' => 1,
            'deposit_amount'   => $depositAmount,
            'deposit_status'   => 'pending',
            'total_price'      => $totalPrice,
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => $order->status,
            'notes'      => $isFullPayment
                ? "Customer chose to pay full amount PHP {$depositAmount}."
                : "Customer set deposit of PHP {$depositAmount} (min 50%).",
            'created_at' => now(),
        ]);

        // GCash — redirect to PayMongo
        if ($order->payment_method === 'GCash') {
            return redirect()->route('customer.custom_orders.pay_deposit', $coId);
        }

        // COD — acknowledge and auto-confirm
        return $this->acknowledgeCustomCod($coId, $co, $order, $depositAmount, $isFullPayment, $uid);
    }

    /** COD custom order — acknowledge deposit and auto-confirm */
    private function acknowledgeCustomCod(string $coId, object $co, object $order, float $depositAmount, bool $isFullPayment, int $uid)
    {
        DB::table('orders')->where('id', $co->order_id)->update([
            'deposit_status' => 'paid',
            'deposit_paid_at'=> now(),
            'payment_status' => $isFullPayment ? 'Paid' : 'Partial Payment',
            'status'         => 'Confirmed',
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => 'Confirmed',
            'notes'      => $isFullPayment
                ? "COD full payment ₱{$depositAmount} acknowledged. Order auto-confirmed."
                : "COD deposit ₱{$depositAmount} acknowledged. Order auto-confirmed. Remaining: ₱" . ($order->total_price - $depositAmount),
            'created_at' => now(),
        ]);

        $this->sendCustomToKitchen($co, $order);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Custom Order #' . $co->order_id . ' - COD Deposit Acknowledged',
            'message'          => "Customer acknowledged COD deposit of PHP {$depositAmount} for Custom Order #{$co->order_id}. Auto-confirmed.",
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'COD Custom Deposit Acknowledged', "Custom Order #{$coId}");
        return back()->with('msg', '✅ Order confirmed! Your custom cake is now being prepared. 🎂');
    }

    /** Send custom order to kitchen (shared helper) */
    private function sendCustomToKitchen(object $co, object $order)
    {
        if ($order->kitchen_sent) return;
        $addons    = DB::table('order_addons')->where('order_id', $co->order_id)->get();
        $addonList = $addons->count() > 0
            ? "\nADD-ONS:\n" . $addons->map(fn($a) => "  • {$a->addon_name}" . ($a->addon_price > 0 ? " (+₱{$a->addon_price})" : " (FREE)"))->implode("\n")
            : '';
        $productName = DB::table('products')->where('id', $order->product_id)->value('name') ?? 'Custom Cake';
        $fullname    = DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Customer';
        $phone       = DB::table('users')->where('id', $order->user_id)->value('phone') ?? '';
        $sizeInfo    = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
        $noteInfo    = $order->custom_note   ? "\nSPECIAL NOTE: {$order->custom_note}" : '';
        $schedInfo   = $order->schedule_date
            ? "\nSCHEDULE: " . date('M d, Y', strtotime($order->schedule_date)) : '';
        $payInfo     = $order->payment_method === 'COD'
            ? CakeshopHelper::shortPaymentCode($order->payment_method, $order->fulfillment_type ?? null) . " — Deposit ₱{$order->deposit_amount} acknowledged"
            : "GCash Deposit ₱{$order->deposit_amount} ✓ Paid";

        DB::table('kitchen_tickets')->where('order_id', $co->order_id)->delete();
        DB::table('kitchen_tickets')->insert([
                        'shop_id'       => $order->shop_id ?? null,
            'order_id'     => $co->order_id,
            'product_name' => $productName . ' (Custom)',
            'quantity'     => $order->quantity ?? 1,
                        'instructions' => "=== KITCHEN ORDER TICKET ===\nOrder #: {$co->order_id}\nCustomer: {$fullname} ({$phone})\nProduct: {$productName} (Custom)\nQty: {$order->quantity}{$sizeInfo}{$noteInfo}{$addonList}{$schedInfo}\nFulfillment: {$order->fulfillment_type}\nPayment: {$payInfo}\n===========================",
            'status'       => 'pending',
            'sent_at'      => now()->format('Y-m-d H:i:s'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        DB::table('orders')->where('id', $co->order_id)->update(['kitchen_sent' => 1]);
    }

    /** Customer cancels order after admin sets a different price */
    public function cancelAfterPrice(string $coId)
    {
        $uid = session('user')['id'];
        $co  = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'pending') return back()->with('err', 'Price already responded to.');

        DB::table('custom_orders')->where('id', $coId)->update([
            'price_confirmed'      => 'cancelled',
            'customer_confirmed_at'=> now(),
        ]);

        if ($co->order_id) {
            DB::table('orders')->where('id', $co->order_id)->update(['status' => 'Cancelled']);
            DB::table('order_tracking')->insert([
                'order_id'   => $co->order_id,
                'status'     => 'Cancelled',
                'notes'      => 'Customer declined the final price of ₱' . number_format($co->admin_price, 2),
                'created_at' => now(),
            ]);

            $custName = session('user')['fullname'] ?? 'Customer';
            DB::table('messages')->insert([
                'order_id'    => $co->order_id,
                'sender_role' => 'customer',
                'sender_id'   => $uid,
                'message'     => "I am cancelling my custom cake order. The final price of PHP " . number_format($co->admin_price, 2) . " does not work for me. Thank you.",
                'is_read'     => 0,
                'created_at'  => now(),
            ]);
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'Custom Order #' . $co->order_id . ' - Price Declined',
                'message'          => "{$custName} declined PHP " . number_format($co->admin_price, 2) . " and cancelled Custom Order #{$co->order_id}.",
                'is_read'          => 0,
                'created_at'       => now(),
            ]);
        }

        CakeshopHelper::logActivity($uid, 'customer', 'Decline Custom Price', "Custom Order #{$coId}");
        return back()->with('msg', 'Order cancelled. Feel free to place a new custom order anytime!');
    }

}
