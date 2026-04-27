<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryZoneController extends Controller
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
        $shop         = $this->getShop();
        $zones        = DB::table('delivery_zones')
            ->where('shop_id', $shop->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
        $shopSettings = DB::table('site_settings')->where('shop_id', $shop->id)->first();
        return view('seller.delivery_zones', compact('shop', 'zones', 'shopSettings'));
    }

    public function store(Request $request)
    {
        $shop     = $this->getShop();
        $barangay = trim($request->input('barangay', ''));
        $address  = trim($request->input('zone_address', ''));
        $lat      = $request->input('lat') ? (float) $request->input('lat') : null;
        $lng      = $request->input('lng') ? (float) $request->input('lng') : null;

        if (!$barangay) return back()->with('err', 'Area name is required.');

        if (DB::table('delivery_zones')->where('shop_id', $shop->id)
            ->whereRaw('LOWER(barangay) = ?', [strtolower($barangay)])->exists())
            return back()->with('err', "'{$barangay}' already exists in your coverage.");

        DB::table('delivery_zones')->insert([
            'shop_id'      => $shop->id,
            'barangay'     => $barangay,
            'zone_address' => $address ?: null,
            'delivery_fee' => 0,
            'zone_type'    => 'near',
            'is_active'    => true,
            'lat'          => $lat,
            'lng'          => $lng,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Add Coverage Area', $barangay);
        return back()->with('msg', "Coverage area '{$barangay}' added.");
    }

    public function update(Request $request, string $id)
    {
        $shop = $this->getShop();
        $zone = DB::table('delivery_zones')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$zone) return back()->with('err', 'Coverage area not found.');

        $lat = $request->input('lat') ? (float) $request->input('lat') : ($zone->lat ?? null);
        $lng = $request->input('lng') ? (float) $request->input('lng') : ($zone->lng ?? null);

        DB::table('delivery_zones')->where('id', $id)->update([
            'barangay'     => trim($request->input('barangay', $zone->barangay)),
            'zone_address' => trim($request->input('zone_address', $zone->zone_address ?? '')) ?: null,
            'lat'          => $lat,
            'lng'          => $lng,
            'updated_at'   => now(),
        ]);

        return back()->with('msg', 'Coverage area updated.');
    }

    public function toggle(string $id)
    {
        $shop = $this->getShop();
        $zone = DB::table('delivery_zones')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$zone) return back()->with('err', 'Not found.');
        $newState = !$zone->is_active;
        DB::table('delivery_zones')->where('id', $id)->update(['is_active' => $newState]);
        return back()->with('msg', 'Coverage area ' . ($newState ? 'shown' : 'hidden') . '.');
    }

    public function destroy(string $id)
    {
        $shop = $this->getShop();
        $zone = DB::table('delivery_zones')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$zone) return back()->with('err', 'Not found.');
        DB::table('delivery_zones')->where('id', $id)->delete();
        return back()->with('msg', 'Coverage area removed.');
    }
}
