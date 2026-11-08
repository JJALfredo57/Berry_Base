<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D/', '', $phone);
        if (preg_match('/^9\d{9}$/', $digits)) $phone = '+63' . $digits;
        elseif (preg_match('/^09\d{9}$/', $digits)) $phone = '+63' . substr($digits, 1);
        elseif (preg_match('/^639\d{9}$/', $digits)) $phone = '+' . $digits;

        return preg_match('/^\+639\d{9}$/', $phone) ? $phone : null;
    }

    private function validName(string $name): bool
    {
        return mb_strlen($name) >= 2 && mb_strlen($name) <= 120
            && (bool) preg_match("/^[\p{L}][\p{L}\s.'-]*$/u", $name);
    }

    public function store(Request $request)
    {
        $shop  = $this->getShop();
        $name  = trim($request->input('name', ''));
        $phone = trim($request->input('phone', ''));
        if (!$this->validName($name)) return back()->with('err', 'Enter a valid rider full name using letters, spaces, apostrophes, periods, or hyphens.')->withInput();
        $phone = $this->normalizePhone($phone);
        if (!$phone) return back()->with('err', 'Rider phone must use the format +639XXXXXXXXX.')->withInput();
        if (DB::table('riders')->where('phone', $phone)->exists()) return back()->with('err', 'This rider phone number is already in use.')->withInput();
        $rawEmergencyPhone = trim((string) $request->input('emergency_contact_phone', ''));
        $emergencyPhone = $rawEmergencyPhone === '' ? null : $this->normalizePhone($rawEmergencyPhone);
        if ($rawEmergencyPhone !== '' && !$emergencyPhone) return back()->with('err', 'Emergency phone must use the format +639XXXXXXXXX.')->withInput();
        $loginPin = (string) random_int(100000, 999999);
        $data = [
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
        ];
        if (Schema::hasColumn('riders', 'login_pin_hash')) {
            $data['login_pin_hash'] = Hash::make($loginPin);
            $data['login_pin_set_at'] = now();
        }
        if (Schema::hasColumn('riders', 'password_must_change')) {
            $data['password_must_change'] = true;
        }
        DB::table('riders')->insert($data);
        $smsSent = SmsHelper::send($phone, "You have been added as a Berry Base rider. Login phone: {$phone}. Temporary PIN: {$loginPin}. You must create your own password after your first login.");
        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Add Rider', $name);
        return back()->with($smsSent ? 'msg' : 'warn', $smsSent
            ? "Rider '{$name}' added. Temporary access was sent by SMS."
            : "Rider '{$name}' was added, but the temporary-access SMS could not be sent. Check SMS settings before asking the rider to log in.");
    }

    public function update(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $rider = DB::table('riders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$rider) return back()->with('err', 'Rider not found.');
        $name = trim((string) $request->input('name', $rider->name));
        if (!$this->validName($name)) return back()->with('err', 'Enter a valid rider full name using letters, spaces, apostrophes, periods, or hyphens.')->withInput();
        $phone = $this->normalizePhone($request->input('phone', ''));
        if (!$phone) return back()->with('err', 'Rider phone must use the format +639XXXXXXXXX.')->withInput();
        if (DB::table('riders')->where('phone', $phone)->where('id', '<>', $id)->exists()) return back()->with('err', 'This rider phone number is already in use.')->withInput();
        $rawEmergencyPhone = trim((string) $request->input('emergency_contact_phone', ''));
        $emergencyPhone = $rawEmergencyPhone === '' ? null : $this->normalizePhone($rawEmergencyPhone);
        if ($rawEmergencyPhone !== '' && !$emergencyPhone) return back()->with('err', 'Emergency phone must use the format +639XXXXXXXXX.')->withInput();
        $data = [
            'name'                   => $name,
            'nickname'               => trim($request->input('nickname', '')) ?: null,
            'phone'                  => $phone,
            'vehicle_type'           => $request->input('vehicle_type') ?: null,
            'license_plate'          => trim($request->input('license_plate', '')) ?: null,
            'emergency_contact_name'  => trim($request->input('emergency_contact_name', '')) ?: null,
            'emergency_contact_phone' => $emergencyPhone,
        ];
        DB::table('riders')->where('id', $id)->update($data);
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
