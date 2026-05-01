<?php
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomOrderController extends Controller
{
    use UploadsFiles;
    private function loadOptions(): array
    {
        $rows = DB::table('custom_order_options')
            ->where('is_active', true)
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

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function show(Request $request)
    {
        $options = $this->loadOptions();

        $addonCategories = DB::table('cake_addon_categories')
            ->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $addonsByCategory = DB::table('cake_addons as a')
            ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
            ->where('a.is_active', true)->where('c.is_active', true)
            ->select('a.*', 'c.name as category_name', 'c.icon as category_icon')
            ->orderBy('a.category_id')->orderBy('a.sort_order')
            ->get()->groupBy('category_id');

        $uid         = session('user')['id'];
        $customer    = DB::table('users')->where('id', $uid)->first();
        $defaultAddr = DB::table('user_addresses')
            ->where('user_id', $uid)
            ->orderByDesc('is_default')->orderByDesc('id')->first();

        $targetShop = null;
        if ($slug = $request->query('shop')) {
            $targetShop = DB::table('shops')
                ->where('shop_slug', $slug)->where('status', 'approved')->first();
        }

        // Shop settings and coverage zones
        $shopSettings  = null;
        $deliveryZones = collect();
        if ($targetShop) {
            $shopSettings  = DB::table('site_settings')->where('shop_id', $targetShop->id)->first();
            $deliveryZones = DB::table('delivery_zones')
                ->where('shop_id', $targetShop->id)
                ->where('is_active', true)
                ->whereNotNull('lat')->whereNotNull('lng')
                ->get();
        }

        return view('customer.custom_order', array_merge($options, compact(
            'addonCategories', 'addonsByCategory', 'customer', 'defaultAddr',
            'deliveryZones', 'targetShop', 'shopSettings'
        )));
    }

    public function store(Request $request)
    {
        $uid     = session('user')['id'];
        $options = $this->loadOptions();

        $shopId = null;
        $shop   = null;
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
                $refImages[] = $this->uploadFile($file, 'uploads/custom_orders');
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
            $addons = DB::table('cake_addons')->whereIn('id', $selectedAddonIds)->where('is_active', true)->get();
            foreach ($addons as $addon) {
                $addonTotal += (float)$addon->price;
                $validAddons[] = $addon;
            }
        }

        $fulfillment = $request->input('fulfillment_type', 'Pickup');
        $zone        = $request->input('delivery_zone', '');
        $deliveryFee = (float)$request->input('delivery_fee', 0);
        $address     = trim($request->input('address', ''));
        $lat         = $request->input('latitude') !== '' ? (float)$request->input('latitude') : null;
        $lng         = $request->input('longitude') !== '' ? (float)$request->input('longitude') : null;
        $sdate       = $request->input('schedule_date') ?: null;
        $payment     = $request->input('payment_method', 'COD');

        if ($fulfillment === 'Delivery' && ($address === '' || $lat === null || $lng === null)) {
            return back()->with('error', 'Please pin your location on the map and enter your address.')->withInput();
        }

        if ($fulfillment === 'Delivery' && $lat !== null && $lng !== null && $shopId) {
            // ── Coverage validation ────────────────────────────
            $shopZones = DB::table('delivery_zones')
                ->where('shop_id', $shopId)
                ->where('is_active', true)
                ->whereNotNull('lat')->whereNotNull('lng')
                ->get();

            if ($shopZones->isNotEmpty()) {
                $inCoverage = false;
                foreach ($shopZones as $z) {
                    if ($this->haversine($lat, $lng, (float)$z->lat, (float)$z->lng) <= 3000) {
                        $inCoverage = true;
                        break;
                    }
                }
                if (!$inCoverage) {
                    return back()->with('error', 'Sorry, your delivery address is outside our delivery coverage area. Please contact the shop for assistance.')->withInput();
                }
            }

            // ── Recalculate fee server-side ────────────────────
            $settings = DB::table('site_settings')->where('shop_id', $shopId)->first();
            if ($settings && $settings->shop_lat && $settings->shop_lng) {
                $dist       = $this->haversine($lat, $lng, (float)$settings->shop_lat, (float)$settings->shop_lng);
                $freeRadius = (int)($settings->free_delivery_radius ?? 0);
                if ($freeRadius > 0 && $dist <= $freeRadius) {
                    $deliveryFee = 0;
                } else {
                    $km = $dist / 1000;
                    $deliveryFee = ceil(
                        ((float)($settings->fee_per_meter ?? 0.05)) * $dist
                        + (((float)($settings->maintenance_per_km ?? 5)) + ((float)($settings->fuel_per_km ?? 8))) * $km
                    );
                }
            }
        }

        if ($fulfillment === 'Delivery' && $request->has('save_default_address')) {
            DB::table('user_addresses')->where('user_id', $uid)->update(['is_default' => 0]);
            DB::table('user_addresses')->insert([
                'user_id'      => $uid,
                'label_name'   => 'Default',
                'full_address' => $address,
                'latitude'     => $lat ?? 0,
                'longitude'    => $lng ?? 0,
                'is_default'   => 1,
                'created_at'   => now(),
            ]);
        }

        $unitPrice = $basePrice + $sizeSurcharge + $complexitySurcharge;
        $subtotal  = $unitPrice * $qty;
        $total     = $subtotal + $addonTotal + ($fulfillment === 'Delivery' ? $deliveryFee : 0);

        $breakdown = [
            'base_price'           => $basePrice,
            'size_surcharge'       => $sizeSurcharge,
            'complexity_surcharge' => $complexitySurcharge,
            'unit_price'           => $unitPrice,
            'quantity'             => $qty,
            'subtotal'             => $subtotal,
            'addon_total'          => $addonTotal,
            'delivery_fee'         => $fulfillment === 'Delivery' ? $deliveryFee : 0,
            'service_charge'       => 0,
            'total'                => $total,
        ];

        $parts = ["CUSTOM ORDER — {$cakeName}"];
        if ($flavor)     $parts[] = "Flavor: {$flavor}";
        if ($sizeLabel)  $parts[] = "Size: {$sizeLabel}";
        if ($layerLabel) $parts[] = "Layers: {$layerLabel}";
        if ($compLabel)  $parts[] = "Design: {$compLabel}";
        if ($dedication) $parts[] = "Dedication: \"{$dedication}\"";
        if ($customNote) $parts[] = "Notes: {$customNote}";
        $fullNote = implode(' | ', $parts);

        $customProduct = DB::table('products')->where('classification', 'Custom')->orderBy('created_at')->first();
        if (!$customProduct) {
            $customPid = CakeshopHelper::generateId('products');
            DB::table('products')->insert([
                'id' => $customPid, 'name' => 'Custom Cake Order',
                'description' => 'Customized cake order placeholder.',
                'price' => $basePrice, 'image_path' => '/storage/uploads/products/default.png',
                'classification' => 'Custom', 'flavor' => null, 'created_at' => now(),
            ]);
        } else {
            $customPid = $customProduct->id;
        }

        $oid = CakeshopHelper::generateId('orders');
        DB::table('orders')->insert([
            'id'               => $oid,
            'shop_id'          => $shopId,
            'user_id'          => $uid,
            'product_id'       => $customPid,
            'quantity'         => $qty,
            'custom_note'      => $fullNote,
            'total_price'      => $total,
            'status'           => 'Pending Review',
            'fulfillment_type' => $fulfillment,
            'delivery_zone'    => $zone ?: ($address ? substr($address, 0, 80) : ''),
            'delivery_fee'     => $deliveryFee,
            'service_charge'   => 0,
            'selected_size'    => $sizeLabel ?: null,
            'selected_size_price' => $unitPrice,
            'delivery_address' => $address ?? '',
            'schedule_date'    => $sdate,
            'schedule_time'    => null,
            'payment_method'   => $payment,
            'payment_status'   => 'Unpaid',
            'created_at'       => now(),
        ]);

        DB::table('custom_orders')->insertGetId([
            'order_id'          => $oid,
            'shop_id'           => $shopId,
            'user_id'           => $uid,
            'cake_name'         => $cakeName,
            'flavor'            => $flavor ?: null,
            'size'              => $sizeLabel ?: null,
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

        DB::table('orders')->where('id', $oid)->update(['custom_note' => $fullNote]);

        foreach ($validAddons as $addon) {
            DB::table('order_addons')->insert([
                'order_id'    => $oid,
                'addon_id'    => $addon->id,
                'addon_name'  => $addon->name,
                'addon_price' => $addon->price,
                'created_at'  => now(),
            ]);
        }

        DB::table('order_tracking')->insert([
            'order_id'   => $oid,
            'status'     => 'Pending Review',
            'notes'      => 'Custom order submitted. Awaiting admin review.',
            'created_at' => now(),
        ]);

        $addonNames = count($validAddons)
            ? "\nAdd-ons: " . implode(', ', array_map(fn($a) => $a->name, $validAddons)) : '';
        $refNote    = !empty($refImages) ? "\n📎 " . count($refImages) . " reference image(s) attached." : '';

        DB::table('messages')->insert([
            'order_id'    => $oid,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => "🎨 CUSTOM ORDER #{$oid} — Pending Review\n{$fullNote}" . $addonNames . $refNote,
            'is_read' => false,
            'created_at'  => now(),
        ]);

        $notifMsg = 'Custom cake from ' . (session('user')['fullname'] ?? 'Customer')
            . ' — awaiting your review.' . ($compLabel ? " Design: {$compLabel}" : '');

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => '🎨 New Custom Order #' . $oid,
            'message'          => $notifMsg,
            'is_read' => false,
            'created_at'       => now(),
        ]);

        if ($shopId) {
            $sellerUser = DB::table('shops')->join('users','users.id','=','shops.seller_id')
                ->where('shops.id', $shopId)->value('users.id');
            if ($sellerUser) {
                DB::table('notifications')->insert([
                    'receiver_role'    => 'seller',
                    'receiver_user_id' => $sellerUser,
                    'title'            => '🎨 New Custom Order #' . $oid,
                    'message'          => $notifMsg,
                    'is_read' => false,
                    'created_at'       => now(),
                ]);
            }
        }

        CakeshopHelper::logActivity($uid, 'customer', 'Custom Order', "Order #{$oid} - {$cakeName}");

        return redirect()->route('customer.orders')
            ->with('msg', "🎂 Custom Order #{$oid} submitted! We'll review it and get back to you soon.");
    }
}
