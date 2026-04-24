<?php
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomOrderController extends Controller
{
    private function loadOptions(): array
    {
        $rows = DB::table('custom_order_options')
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('id')
            ->get()->groupBy('type');

        return [
            'flavors'      => $rows['flavor']     ?? collect(),
            'sizes'        => $rows['size']        ?? collect(),
            'layers'       => $rows['layer']       ?? collect(),
            'complexities' => $rows['complexity']  ?? collect(),
            'timeSlots'    => $rows['time_slot']   ?? collect(),
        ];
    }

    public function show(Request $request)
    {
        $options = $this->loadOptions();

        $addonCategories = DB::table('cake_addon_categories')
            ->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get();

        $addonsByCategory = DB::table('cake_addons as a')
            ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
            ->where('a.is_active', 1)->where('c.is_active', 1)
            ->select('a.*', 'c.name as category_name', 'c.icon as category_icon')
            ->orderBy('a.category_id')->orderBy('a.sort_order')
            ->get()->groupBy('category_id');

        $uid         = session('user')['id'];
        $customer    = DB::table('users')->where('id', $uid)->first();
        $defaultAddr = DB::table('user_addresses')
            ->where('user_id', $uid)
            ->orderByDesc('is_default')->orderByDesc('id')->first();

        $deliveryZones = collect();
        try {
            $deliveryZones = DB::table('delivery_zones')
                ->where('is_active', 1)->orderBy('sort_order')->get();
        } catch (\Exception $e) {}

        $targetShop = null;
        if ($slug = $request->query('shop')) {
            $targetShop = DB::table('shops')
                ->where('shop_slug', $slug)
                ->where('status', 'approved')
                ->first();
        }

        return view('customer.custom_order', array_merge($options, compact(
            'addonCategories', 'addonsByCategory', 'customer', 'defaultAddr', 'deliveryZones', 'targetShop'
        )));
    }

    public function store(Request $request)
    {
        $uid     = session('user')['id'];
        $options = $this->loadOptions();

        $shopId = null;
        if ($slug = $request->input('shop_slug')) {
            $shop = DB::table('shops')->where('shop_slug', $slug)->where('status', 'approved')->first();
            if ($shop) $shopId = $shop->id;
        }

        $cakeName   = trim($request->input('cake_name', 'Customized Cake'));
        $flavor     = trim($request->input('flavor', ''));
        $sizeLabel  = trim($request->input('size', ''));
        $layerLabel = trim($request->input('layers', ''));
        $compLabel  = trim($request->input('design_complexity', ''));
        $dedication = trim($request->input('dedication', ''));
        $timeSlot   = trim($request->input('time_slot', ''));
        $qty        = max(1, (int)$request->input('quantity', 1));
        $customNote = trim($request->input('custom_note', ''));

        // Save reference images
        $refImages = [];
        if ($request->hasFile('reference_images')) {
            foreach ($request->file('reference_images') as $file) {
                if (!$file->isValid()) continue;
                if ($file->getSize() > 5 * 1024 * 1024) continue;
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $file->storeAs('uploads/custom_orders', $filename, 'public');
                $refImages[] = '/storage/uploads/custom_orders/' . $filename;
            }
        }

        $basePrice           = 1200.00;
        $sizeSurcharge       = 0.00;
        $complexitySurcharge = 0.00;

        if ($sizeLabel) {
            $sizeOpt = $options['sizes']->firstWhere('label', $sizeLabel);
            if ($sizeOpt) $sizeSurcharge = (float)$sizeOpt->price;
        }
        if ($compLabel) {
            $compOpt = $options['complexities']->firstWhere('label', $compLabel);
            if ($compOpt) $complexitySurcharge = (float)$compOpt->price;
        }

        $selectedAddonIds = array_filter(array_map('intval', $request->input('addons', [])));
        $addonTotal  = 0;
        $validAddons = [];
        if (!empty($selectedAddonIds)) {
            $addons = DB::table('cake_addons')->whereIn('id', $selectedAddonIds)->where('is_active', 1)->get();
            foreach ($addons as $addon) {
                $addonTotal += (float)$addon->price;
                $validAddons[] = $addon;
            }
        }

        $fulfillment   = $request->input('fulfillment_type', 'Pickup');
        $zone          = $request->input('delivery_zone', '');
        $deliveryFee   = (float)$request->input('delivery_fee', 0);
        $serviceCharge = (float)$request->input('service_charge', 0);
        $address       = trim($request->input('address', ''));
        $lat           = $request->input('latitude') !== '' ? (float)$request->input('latitude') : null;
        $lng           = $request->input('longitude') !== '' ? (float)$request->input('longitude') : null;
        $sdate         = $request->input('schedule_date') ?: null;
        $stime         = null; // Time slot label is stored in custom_orders.time_slot, not orders.schedule_time
        $payment       = $request->input('payment_method', 'COD');

        if ($fulfillment === 'Delivery' && ($address === '' || $lat === null || $lng === null)) {
            return back()->with('error', 'Please pin your location and enter your address.')->withInput();
        }

        if ($fulfillment === 'Delivery' && $request->has('save_default_address')) {
            DB::table('user_addresses')->where('user_id', $uid)->update(['is_default' => 0]);
            DB::table('user_addresses')->insert([
                'user_id' => $uid, 'label_name' => 'Default',
                'full_address' => $address,                 'longitude' => $lng ?? 0, 'is_default' => 1, 'created_at' => now(),
            ]);
        }

        $unitPrice = $basePrice + $sizeSurcharge + $complexitySurcharge;
        $subtotal  = $unitPrice * $qty;
        $total     = $subtotal + $addonTotal + ($fulfillment === 'Delivery' ? $deliveryFee + $serviceCharge : 0);

        // Price breakdown for admin reference
        $breakdown = [
            'base_price'          => $basePrice,
            'size_surcharge'      => $sizeSurcharge,
            'complexity_surcharge'=> $complexitySurcharge,
            'unit_price'          => $unitPrice,
            'quantity'            => $qty,
            'subtotal'            => $subtotal,
            'addon_total'         => $addonTotal,
            'delivery_fee'        => $fulfillment === 'Delivery' ? $deliveryFee : 0,
            'service_charge'      => $fulfillment === 'Delivery' ? $serviceCharge : 0,
            'total'               => $total,
        ];

        $parts = ["CUSTOM ORDER — {$cakeName}"];
        if ($flavor)     $parts[] = "Flavor: {$flavor}";
        if ($sizeLabel)  $parts[] = "Size: {$sizeLabel}";
        if ($layerLabel) $parts[] = "Layers: {$layerLabel}";
        if ($compLabel)  $parts[] = "Design: {$compLabel}";
        if ($dedication) $parts[] = "Dedication: \"{$dedication}\"";
        if ($customNote) $parts[] = "Notes: {$customNote}";
        $fullNote = implode(' | ', $parts);

        // Get or create custom product placeholder
        $customProduct = DB::table('products')->where('classification', 'Custom')->orderBy('created_at')->first();
        if (!$customProduct) {
            $customPid = CakeshopHelper::generateId('products');
            DB::table('products')->insert([
                'id' => $customPid,
                'name' => 'Custom Cake Order', 'description' => 'Customized cake order placeholder.',
                'price' => $basePrice, 'image_path' => '/storage/uploads/products/default.png',
                'classification' => 'Custom', 'flavor' => null, 'created_at' => now(),
            ]);
        } else {
            $customPid = $customProduct->id;
        }

        // Insert order with status "Pending Review"
        $oid = CakeshopHelper::generateId('orders');
        DB::table('orders')->insert([
            'id' => $oid,
            'shop_id' => $shopId,
            'user_id' => $uid, 'product_id' => $customPid, 'quantity' => $qty,
            'delivery_fee' => $addonTotal, 'custom_note' => $fullNote,
            'total_price' => $total, 'status' => 'Pending Review',
            'fulfillment_type' => $fulfillment, 'delivery_zone' => $zone ?? '',
            'delivery_fee' => $deliveryFee, 'service_charge' => $serviceCharge,
            'selected_size' => $sizeLabel ?: null, 'selected_size_price' => $unitPrice,
            'delivery_address' => $address ?? '', 'latitude' => $lat ?? 0,             'schedule_date' => $sdate, 'schedule_time' => $stime,
            'payment_method' => $payment, 'payment_status' => 'Unpaid', 'created_at' => now(),
        ]);

        // Insert custom_orders record
        $coid = DB::table('custom_orders')->insertGetId([
            'order_id'          => $oid,
            'shop_id'           => $shopId,
            'user_id'           => $uid,
            'cake_name'         => $cakeName,
            'flavor'            => $flavor ?: null,
            'size'        => $sizeLabel ?: null,
            'layers'            => $layerLabel ?: null,
            'design_complexity' => $compLabel ?: null,
            'dedication'        => $dedication ?: null,
            'custom_note'       => $customNote ?: null,
            'time_slot'         => $timeSlot ?: null,
            'reference_images'  => !empty($refImages) ? json_encode($refImages) : null,
            'estimated_price'   => $total,
            'price_breakdown'   => json_encode($breakdown),
            'review_status'     => 'pending',
            'created_at'        => now(),
        ]);

        // Update order to reference the custom_order record
        DB::table('orders')->where('id', $oid)->update(['custom_note' => $fullNote]);

        foreach ($validAddons as $addon) {
            DB::table('order_addons')->insert([
                'order_id' => $oid, 'addon_id' => $addon->id,
                'name' => $addon->name, 'addon_price' => $addon->price, 'created_at' => now(),
            ]);
        }

        DB::table('order_tracking')->insert([
            'order_id' => $oid, 'status' => 'Pending Review',
            'notes' => 'Custom order submitted. Awaiting admin review.', 'created_at' => now(),
        ]);

        $addonNames = count($validAddons)
            ? "\nAdd-ons: " . implode(', ', array_map(fn($a) => $a->name, $validAddons)) : '';

        // Initial message with reference note
        $refNote = !empty($refImages) ? "\n📎 " . count($refImages) . " reference image(s) attached." : '';
        DB::table('messages')->insert([
            'order_id' => $oid, 'sender_role' => 'customer', 'sender_id' => $uid,
            'message'  => "🎨 CUSTOM ORDER #{$oid} — Pending Review\n{$fullNote}" . $addonNames . $refNote,
            'is_read'  => 0, 'created_at' => now(),
        ]);

        $notifMsg = 'Custom cake from ' . (session('user')['fullname'] ?? 'Customer')
            . ' — awaiting your review.' . ($compLabel ? " Design: {$compLabel}" : '');
        DB::table('notifications')->insert([
            'receiver_role' => 'admin', 'receiver_user_id' => null,
            'title'   => '🎨 New Custom Order #' . $oid,
            'message' => $notifMsg,
            'is_read' => 0, 'created_at' => now(),
        ]);
        if ($shopId) {
            $sellerUser = DB::table('shops')->join('users','users.id','=','shops.seller_id')
                ->where('shops.id', $shopId)->value('users.id');
            if ($sellerUser) {
                DB::table('notifications')->insert([
                    'receiver_role' => 'seller', 'receiver_user_id' => $sellerUser,
                    'title'   => '🎨 New Custom Order #' . $oid,
                    'message' => $notifMsg,
                    'is_read' => 0, 'created_at' => now(),
                ]);
            }
        }

        CakeshopHelper::logActivity($uid, 'customer', 'Custom Order', "Order #{$oid} - {$cakeName}");

        return redirect()->route('customer.orders')
            ->with('msg', "🎂 Custom Order #{$oid} submitted! We'll review it and get back to you soon.");
    }
}
