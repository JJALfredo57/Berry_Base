<?php
namespace App\Http\Controllers;

use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingController extends Controller
{
    public function show(string $trackCode)
    {
        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name', 'p.image_path', 'p.classification')
            ->first();

        if (!$order) abort(404, 'Order not found. Please check your tracking link.');

        $tracking = DB::table('order_tracking')
            ->where('order_id', $order->id)
            ->orderBy('created_at')->get();

        $addons = DB::table('order_addons')->where('order_id', $order->id)->get();

        $customOrder = null;
        try {
            $customOrder = DB::table('custom_orders')->where('order_id', $order->id)->first();
        } catch (\Exception $e) {}

        $isPickup    = ($order->fulfillment_type ?? '') === 'Pickup';
        $statusSteps = $isPickup
            ? ['Pending','Confirmed','Preparing','Ready for Pickup','Picked Up']
            : ['Pending','Confirmed','Preparing','Out for Delivery','Delivered'];
        $currentStep = array_search($order->status, $statusSteps);
        if ($currentStep === false) $currentStep = 0;

        return view('guest.track_order', compact(
            'order','tracking','addons','customOrder','statusSteps','currentStep'
        ));
    }

    public function requestCancel(Request $request, string $trackCode)
    {
        $reason = trim($request->input('cancel_reason', ''));
        if ($reason === '') {
            return back()->with('error', 'Please provide a reason for cancellation.');
        }

        $order = DB::table('orders')
            ->where('track_code', strtoupper($trackCode))
            ->first();

        if (!$order) {
            return back()->with('error', 'Order not found.');
        }

        $hasPaidDeposit = ($order->deposit_status ?? null) === 'paid'
            || in_array(($order->payment_status ?? ''), ['Partial Payment', 'Paid'], true);
        if ($hasPaidDeposit) {
            return back()->with('error', 'Cannot cancel this order because your deposit has already been paid.');
        }

        if (in_array($order->status, ['Preparing', 'Out for Delivery', 'Delivered', 'Cancelled', 'Picked Up'], true)) {
            return back()->with('error', "Cannot cancel this order because it is already {$order->status}.");
        }

        if (($order->cancel_requested ?? 0) && ($order->cancel_status ?? '') === 'pending') {
            return back()->with('error', 'A cancellation request is already pending for this order.');
        }

        DB::table('orders')->where('id', $order->id)->update([
            'cancel_requested'    => 1,
            'cancel_reason'       => $reason,
            'cancel_status'       => 'pending',
            'cancel_admin_note'   => null,
            'cancel_requested_at' => now()->format('Y-m-d H:i:s'),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Cancel Request - Order #' . $order->id,
            'message'          => ($order->guest_name ?? 'Guest customer') . " wants to cancel Order #{$order->id}. Reason: {$reason}",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        DB::table('messages')->insert([
            'order_id'    => $order->id,
            'sender_role' => 'guest',
            'sender_id'   => null,
            'message'     => "Cancel request submitted.\n\nReason: {$reason}",
            'is_read' => false,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity('guest', 'guest', 'Cancel Request', "Order #{$order->id} - {$reason}");
        return back()->with('msg', 'Cancel request submitted successfully. Waiting for admin approval.');
    }
}
