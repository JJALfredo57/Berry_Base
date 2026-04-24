<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function show(Request $request)
    {
        $checkout = $request->session()->get('checkout');
        if (!$checkout) return redirect()->route('customer.catalog');

        $product = DB::table('products')->where('id', $checkout['product_id'])->first();
        if (!$product) return redirect()->route('customer.catalog');

        $uid         = session('user')['id'];
        $customer    = DB::table('users')->where('id', $uid)->first();
        $defaultAddr = DB::table('user_addresses')
            ->where('user_id', $uid)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        // Load seller shop for this product
        $shop = null;
        try {
            if ($product->shop_id ?? null) {
                $shop = DB::table('shops')->where('id', $product->shop_id)->first();
            }
        } catch (\Exception $e) {}

        // Load sizes for this product
        $sizes = collect();
        try {
            $sizes = DB::table('product_sizes')
                ->where('product_id', $checkout['product_id'])
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get();
        } catch (\Exception $e) {}

        // Load delivery zones from DB
        $deliveryZones = collect();
        try {
            $deliveryZones = DB::table('delivery_zones')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get();
        } catch (\Exception $e) {}

        return view('customer.checkout', compact(
            'product', 'checkout', 'defaultAddr', 'customer',
            'sizes', 'deliveryZones', 'shop'
        ));
    }

    public function placeOrder(Request $request)
    {
        $uid      = session('user')['id'];
        $checkout = $request->session()->get('checkout');
        if (!$checkout) return redirect()->route('customer.catalog');

        $pid  = (int) $checkout['product_id'];
        $qty  = (int) $checkout['quantity'];
        $note = $checkout['custom_note'];

        $product = DB::table('products')->where('id', $pid)->first();
        if (!$product) return redirect()->route('customer.catalog');

        // ── DUPLICATE ORDER PREVENTION ────────────────────────────
        $recentDuplicate = DB::table('orders')
            ->where('user_id', $uid)
            ->where('product_id', $pid)
            ->where('status', 'Pending')
            ->where('created_at', '>=', now()->subSeconds(30)->format('Y-m-d H:i:s'))
            ->first();

        if ($recentDuplicate) {
            $request->session()->forget('checkout');
            return redirect()->route('customer.orders')
                ->with('warn', "Order #{$recentDuplicate->id} was already placed! Check your orders.");
        }

        $fulfillment   = $request->input('fulfillment_type', 'Pickup');
        $zone          = $request->input('delivery_zone', '');
        $deliveryFee   = (float) $request->input('delivery_fee', 0);
        $address       = trim($request->input('address', ''));
        $lat           = $request->input('latitude') !== '' ? (float) $request->input('latitude') : null;
        $lng           = $request->input('longitude') !== '' ? (float) $request->input('longitude') : null;
        $sdate         = $request->input('schedule_date') ?: null;
        $stime         = $request->input('schedule_time') ?: null;
        $payment       = $request->input('payment_method', 'COD');
        $serviceCharge = (float) $request->input('service_charge', 0);
        $selectedSize  = trim($request->input('selected_size', $checkout['selected_size'] ?? ''));

        if ($fulfillment === 'Delivery' && !$zone) {
            return back()->with('error', 'Please select your barangay for delivery.');
        }
        if ($fulfillment === 'Delivery' && ($address === '' || $lat === null || $lng === null)) {
            return back()->with('error', 'Please pin your location on the map and enter your address.');
        }

        // ── Verify delivery fee from DB (prevent tampering) ───────
        if ($fulfillment === 'Delivery' && $zone) {
            try {
                $zoneRow = DB::table('delivery_zones')
                    ->where('barangay', $zone)
                    ->where('is_active', 1)
                    ->first();
                if ($zoneRow) $deliveryFee = (float) $zoneRow->fee;
            } catch (\Exception $e) {}
        }

        // Save default address if requested
        if ($fulfillment === 'Delivery' && $request->has('save_default_address')) {
            DB::table('user_addresses')->where('user_id', $uid)->update(['is_default' => 0]);
            DB::table('user_addresses')->insert([
                'user_id'      => $uid,
                'label_name'   => 'Default',
                'full_address' => $address,
                                                'is_default'   => 1,
                'created_at'   => now(),
            ]);
        }

        // If a size was selected, use size price instead of base price
        $sizePrice = (float) $product->price;
        if ($selectedSize) {
            try {
                $sz = DB::table('product_sizes')
                    ->where('product_id', $pid)
                    ->where('label', $selectedSize)
                    ->where('is_active', 1)
                    ->first();
                if ($sz) $sizePrice = (float) $sz->price;
            } catch (\Exception $e) {}
        }

        // ── SHOP-WIDE DAILY CAPACITY CHECK ───────────────────────────────────
        if ($sdate) {
            $shopId   = $product->shop_id ?? null;
            $settings = $shopId ? DB::table('site_settings')->where('shop_id', $shopId)->first() : null;
            if (!$settings) $settings = DB::table('site_settings')->whereNull('shop_id')->first() ?? DB::table('site_settings')->first();
            $dailyMax = (int)($settings->daily_max_cakes ?? 0);
            if ($dailyMax > 0) {
                $today    = date('Y-m-d');
                $leadDays = (int)ceil((strtotime($sdate) - strtotime($today)) / 86400);
                $effectiveMax = $dailyMax;
                if ($leadDays === 1 && ($settings->lead_1day_max ?? 0) > 0) $effectiveMax = (int)$settings->lead_1day_max;
                elseif ($leadDays === 2 && ($settings->lead_2day_max ?? 0) > 0) $effectiveMax = (int)$settings->lead_2day_max;
                elseif ($leadDays >= 3 && ($settings->lead_3day_plus_max ?? 0) > 0) $effectiveMax = (int)$settings->lead_3day_plus_max;
                $totalOrdered = (int) DB::table('orders')
                    ->where('schedule_date', $sdate)
                    ->whereNotIn('status', ['Cancelled'])
                    ->sum('quantity');
                try {
                    $totalOrdered += (int) DB::table('custom_orders')
                        ->where('schedule_date', $sdate)
                        ->whereNotIn('status', ['Rejected','Cancelled'])
                        ->sum('quantity');
                } catch (\Exception $e) {}
                if (($totalOrdered + $qty) > $effectiveMax) {
                    $remaining = max(0, $effectiveMax - $totalOrdered);
                    $msg = $remaining === 0
                        ? "Sorry, {$sdate} is fully booked ({$effectiveMax} pcs max). Please choose another date."
                        : "Only {$remaining} pcs available on {$sdate}. Please reduce your quantity or choose another date.";
                    return back()->with('error', $msg);
                }
            }
        }

        $baseTotal = $sizePrice * $qty;
        $total     = $baseTotal + ($fulfillment === 'Delivery' ? $deliveryFee + $serviceCharge : 0);
        $oid       = CakeshopHelper::generateId('orders');

        DB::table('orders')->insert([
            'id'                  => $oid,
            'shop_id'             => $product->shop_id ?? null,
            'user_id'             => $uid,
            'product_id'          => $pid,
            'quantity'            => $qty,
            'custom_note'         => $note,
            'total_price'         => $total,
            'status'              => 'Pending',
            'fulfillment_type'    => $fulfillment,
            'delivery_zone'       => $zone ?? '',
            'delivery_fee'        => $deliveryFee,
            'service_charge'      => $serviceCharge,
            'selected_size'       => $selectedSize ?: null,
            'selected_size_price' => $sizePrice,
            'delivery_address'    => $address ?? '',
            'schedule_date'       => $sdate,
            'schedule_time'       => $stime,
            'payment_method'      => $payment,
            'payment_status'      => 'Unpaid',
            'created_at'          => now(),
        ]);

        // Initial tracking entry
        DB::table('order_tracking')->insert([
            'order_id'   => $oid,
            'status'     => 'Pending',
            'notes'      => 'Order placed successfully.',
            'created_at' => now(),
        ]);

        // Notify admin — runs for both COD and GCash
        DB::table('messages')->insert([
            'order_id'    => $oid,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => "New order placed. Order #{$oid}." . ($note ? "\nNote: {$note}" : ''),
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'New Order #' . $oid,
            'message'          => "New order from " . (session('user')['fullname'] ?? 'Customer') . ".",
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        $request->session()->forget('checkout');

        if ($payment === 'GCash') {
            $request->session()->put('last_order_id', $oid);
            return redirect()->route('customer.pay_gcash', ['id' => $oid]);
        }

        return redirect()->route('customer.orders')->with('msg', "Order #{$oid} placed successfully!");
    }
}
