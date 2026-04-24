<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderController extends Controller
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
        $shop   = $this->getShop();
        $riders = DB::table('riders')->where('shop_id', $shop->id)->orderBy('name')->get();
        $riderIds = $riders->pluck('id')->toArray();
        $incidents = []; $deliveries = [];
        if ($riderIds) {
            foreach (DB::table('orders')->whereIn('rider_id', $riderIds)->whereNotNull('issue_type')->select('rider_id', DB::raw('count(*) as cnt'))->groupBy('rider_id')->get() as $i)
                $incidents[$i->rider_id] = $i->cnt;
            foreach (DB::table('orders')->whereIn('rider_id', $riderIds)->where('status', 'Delivered')->select('rider_id', DB::raw('count(*) as cnt'))->groupBy('rider_id')->get() as $d)
                $deliveries[$d->rider_id] = $d->cnt;
        }
        return view('seller.riders', compact('riders', 'incidents', 'deliveries'));
    }

    public function store(Request $request)
    {
        $shop  = $this->getShop();
        $name  = trim($request->input('name', ''));
        $phone = trim($request->input('phone', ''));
        if (!$name) return back()->with('err', 'Rider name is required.');
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10) $phone = '+63' . $phone;
        elseif (strlen($phone) === 11 && $phone[0] === '0') $phone = '+63' . substr($phone, 1);
        DB::table('riders')->insert([
            'shop_id'                => $shop->id,
            'name'                   => $name,
            'nickname'               => trim($request->input('nickname', '')) ?: null,
            'phone'                  => $phone ?: null,
            'vehicle_type'           => $request->input('vehicle_type') ?: null,
            'license_plate'          => trim($request->input('license_plate', '')) ?: null,
            'emergency_contact_name'  => trim($request->input('emergency_contact_name', '')) ?: null,
            'emergency_contact_phone' => trim($request->input('emergency_contact_phone', '')) ?: null,
            'is_active'               => 1,

            'created_at'             => now(),
        ]);
        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Add Rider', $name);
        return back()->with('msg', "Rider '{$name}' added.");
    }

    public function update(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $rider = DB::table('riders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$rider) return back()->with('err', 'Rider not found.');
        $phone = preg_replace('/\D/', '', $request->input('phone', ''));
        if (strlen($phone) === 10) $phone = '+63' . $phone;
        elseif (strlen($phone) === 11 && $phone[0] === '0') $phone = '+63' . substr($phone, 1);
        DB::table('riders')->where('id', $id)->update([
            'name'                   => trim($request->input('name', $rider->name)),
            'nickname'               => trim($request->input('nickname', '')) ?: null,
            'phone'                  => $phone ?: $rider->phone,
            'vehicle_type'           => $request->input('vehicle_type') ?: null,
            'license_plate'          => trim($request->input('license_plate', '')) ?: null,
            'emergency_contact_name'  => trim($request->input('emergency_contact_name', '')) ?: null,
            'emergency_contact_phone' => trim($request->input('emergency_contact_phone', '')) ?: null,
        ]);
        return back()->with('msg', 'Rider updated.');
    }

    public function toggle(string $id)
    {
        $shop  = $this->getShop();
        $rider = DB::table('riders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$rider) return back()->with('err', 'Not found.');
        DB::table('riders')->where('id', $id)->update(['is_active' => !$rider->is_active]);
        return back()->with('msg', 'Rider ' . (!$rider->is_active ? 'activated' : 'deactivated') . '.');
    }
}
