<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        $zones = DB::table('delivery_zones')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.delivery_zones', compact('zones'));
    }

    public function store(Request $request)
    {
        $barangay = trim($request->input('barangay', ''));
        $fee      = (float) $request->input('fee', 0);
        $type     = $request->input('zone_type', 'near');

        if (!$barangay) return back()->with('err', 'Barangay name is required.');

        // Duplicate check
        $exists = DB::table('delivery_zones')->whereRaw('LOWER(barangay) = ?', [strtolower($barangay)])->exists();
        if ($exists) return back()->with('err', "Barangay '{$barangay}' already exists in the delivery zones.");

        $validTypes = ['free', 'near', 'mid', 'far', 'ooc'];
        if (!in_array($type, $validTypes)) $type = 'near';

        $max = DB::table('delivery_zones')->max('sort_order') ?? 0;

        DB::table('delivery_zones')->insert([
            'barangay'       => $barangay,
            'delivery_fee' => $fee,
            'zone_type'      => $type,
            'estimated_time' => trim($request->input('estimated_time','30-45 mins')) ?: '30-45 mins',
            'is_active'      => 1,
            'sort_order'     => $max + 1,
            'created_at'     => now(),
        ]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Add Delivery Zone', $barangay);
        return back()->with('msg', "Barangay '{$barangay}' added.");
    }

    public function update(Request $request, string $id)
    {
        $barangay = trim($request->input('barangay', ''));
        $fee      = (float) $request->input('fee', 0);
        $type     = $request->input('zone_type', 'near');

        if (!$barangay) return back()->with('err', 'Barangay name is required.');

        // Duplicate check
        $exists = DB::table('delivery_zones')->whereRaw('LOWER(barangay) = ?', [strtolower($barangay)])->exists();
        if ($exists) return back()->with('err', "Barangay '{$barangay}' already exists in the delivery zones.");

        DB::table('delivery_zones')->where('id', $id)->update([
            'barangay'       => $barangay,
            'fee'            => $fee,
            'zone_type'      => $type,
            'estimated_time' => trim($request->input('estimated_time','30-45 mins')) ?: '30-45 mins',
        ]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Edit Delivery Zone', $barangay);
        return back()->with('msg', "Zone updated.");
    }

    public function toggle(string $id)
    {
        $zone = DB::table('delivery_zones')->where('id', $id)->first();
        if (!$zone) return back()->with('err', 'Zone not found.');
        DB::table('delivery_zones')->where('id', $id)
            ->update(['is_active' => $zone->is_active ? 0 : 1]);
        return back()->with('msg', $zone->is_active ? 'Zone hidden.' : 'Zone shown.');
    }

    public function destroy(string $id)
    {
        $zone = DB::table('delivery_zones')->where('id', $id)->first();
        DB::table('delivery_zones')->where('id', $id)->delete();
        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Delete Delivery Zone', $zone->barangay ?? 'ID:'.$id);
        return back()->with('msg', 'Zone deleted.');
    }
}
