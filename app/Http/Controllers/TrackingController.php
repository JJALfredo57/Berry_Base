<?php
namespace App\Http\Controllers;

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

        $statusSteps  = ['Pending','Confirmed','Preparing','Out for Delivery','Delivered'];
        $currentStep  = array_search($order->status, $statusSteps);

        return view('guest.track_order', compact(
            'order','tracking','addons','customOrder','statusSteps','currentStep'
        ));
    }
}
