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
        $shop  = $this->getShop();
        $zones = DB::table('delivery_zones')
            ->where('shop_id', $shop->id)
            ->orderBy('id')
            ->get();
        return view('admin.delivery_zones', compact('zones'));
    }

    public function store(Request $request)
    {
        $shop     = $this->getShop();
        $barangay = trim($request->input('barangay', ''));
        $fee      = (float)$request->input('fee', 0);
        if (!$barangay) return back()->with('err', 'Barangay name is required.');
        if (DB::table('delivery_zones')->where('shop_id', $shop->id)
            ->whereRaw('LOWER(barangay) = ?', [strtolower($barangay)])->exists())
            return back()->with('err', "'{$barangay}' already exists.");
        DB::table('delivery_zones')->insert([
            'shop_id'      => $shop->id,
            'barangay'     => $barangay,
            'delivery_fee' => $fee,
            'is_active'    => 1,
            'created_at'   => now(),
        ]);
        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Add Delivery Zone', $barangay);
        return back()->with('msg', "Zone '{$barangay}' added.");
    }

    public function update(Request $request, string $id)
    {
        $shop = $this->getShop();
        $zone = DB::table('delivery_zones')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$zone) return back()->with('err', 'Zone not found.');
        DB::table('delivery_zones')->where('id', $id)->update([
            'barangay'     => trim($request->input('barangay', $zone->barangay)),
            'delivery_fee' => (float)$request->input('fee', $zone->delivery_fee),
        ]);
        return back()->with('msg', 'Zone updated.');
    }

    public function toggle(string $id)
    {
        $shop = $this->getShop();
        $zone = DB::table('delivery_zones')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$zone) return back()->with('err', 'Not found.');
        DB::table('delivery_zones')->where('id', $id)->update(['is_active' => !$zone->is_active]);
        return back()->with('msg', 'Zone ' . (!$zone->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(string $id)
    {
        $shop = $this->getShop();
        $zone = DB::table('delivery_zones')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$zone) return back()->with('err', 'Not found.');
        DB::table('delivery_zones')->where('id', $id)->delete();
        return back()->with('msg', 'Zone deleted.');
    }
}
