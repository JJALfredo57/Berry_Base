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
        $incidents = []; $deliveries = []; $riderRatings = [];
        if ($riderIds) {
            foreach (DB::table('orders')->whereIn('rider_id', $riderIds)->whereNotNull('issue_type')->select('rider_id', DB::raw('count(*) as cnt'))->groupBy('rider_id')->get() as $i)
                $incidents[$i->rider_id] = $i->cnt;
            foreach (DB::table('orders')->whereIn('rider_id', $riderIds)->where('status', 'Delivered')->select('rider_id', DB::raw('count(*) as cnt'))->groupBy('rider_id')->get() as $d)
                $deliveries[$d->rider_id] = $d->cnt;
            foreach (DB::table('order_reviews as r')
                ->join('orders as o', 'o.id', '=', 'r.order_id')
                ->whereIn('o.rider_id', $riderIds)
                ->whereNotNull('r.rider_rating')
                ->select('o.rider_id', DB::raw('AVG(r.rider_rating) as avg_rating'), DB::raw('COUNT(*) as rating_count'))
                ->groupBy('o.rider_id')
                ->get() as $rating) {
                $riderRatings[$rating->rider_id] = $rating;
            }
        }
        return view('seller.riders', compact('riders', 'incidents', 'deliveries', 'riderRatings'));
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (!$digits) return null;

        if (strlen($digits) === 10) return '+63' . $digits;
        if (strlen($digits) === 11 && $digits[0] === '0') return '+63' . substr($digits, 1);
        if (strlen($digits) === 12 && str_starts_with($digits, '63')) return '+' . $digits;

        return $digits;
    }

    public function store(Request $request)
    {
        $shop  = $this->getShop();
        $name  = trim($request->input('name', ''));
        $phone = trim($request->input('phone', ''));
        if (!$name) return back()->with('err', 'Rider name is required.');
        $phone = $this->normalizePhone($phone);
        $emergencyPhone = $this->normalizePhone($request->input('emergency_contact_phone', ''));
        DB::table('riders')->insert([
            'shop_id'                => $shop->id,
            'name'                   => $name,
            'nickname'               => trim($request->input('nickname', '')) ?: null,
            'phone'                  => $phone ?: null,
            'vehicle_type'           => $request->input('vehicle_type') ?: null,
            'license_plate'          => trim($request->input('license_plate', '')) ?: null,
            'emergency_contact_name'  => trim($request->input('emergency_contact_name', '')) ?: null,
            'emergency_contact_phone' => $emergencyPhone,
            'is_active' => true,

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
        $phone = $this->normalizePhone($request->input('phone', ''));
        $emergencyPhone = $this->normalizePhone($request->input('emergency_contact_phone', ''));
        DB::table('riders')->where('id', $id)->update([
            'name'                   => trim($request->input('name', $rider->name)),
            'nickname'               => trim($request->input('nickname', '')) ?: null,
            'phone'                  => $phone ?: $rider->phone,
            'vehicle_type'           => $request->input('vehicle_type') ?: null,
            'license_plate'          => trim($request->input('license_plate', '')) ?: null,
            'emergency_contact_name'  => trim($request->input('emergency_contact_name', '')) ?: null,
            'emergency_contact_phone' => $emergencyPhone,
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
