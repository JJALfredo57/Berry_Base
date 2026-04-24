<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index()
    {
        $uid  = session('user')['id'];
        $list = DB::table('user_addresses')
            ->where('user_id', $uid)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
        $defaultAddr = DB::table('user_addresses')
            ->where('user_id', $uid)
            ->where('is_default', 1)
            ->first();
        return view('customer.addresses', compact('list', 'defaultAddr'));
    }

    public function store(Request $request)
    {
        $uid     = session('user')['id'];
        $label   = trim($request->input('label_name', 'Home'));
        $address = trim($request->input('full_address', ''));
        $lat     = $request->input('latitude') !== '' ? (float)$request->input('latitude') : 0;
        $lng     = $request->input('longitude') !== '' ? (float)$request->input('longitude') : 0;
        $makeDefault = $request->has('make_default') ? 1 : 0;

        if ($address === '' || $lat == 0 || $lng == 0) {
            return back()->with('error', 'Please pin your exact location and enter the complete address.');
        }

        $has = DB::table('user_addresses')->where('user_id', $uid)->first();
        if ($makeDefault || !$has) {
            DB::table('user_addresses')->where('user_id', $uid)->update(['is_default' => 0]);
            $makeDefault = 1;
        }

        DB::table('user_addresses')->insert([
            'user_id'      => $uid,
            'label_name'   => $label,
            'full_address' => $address,
            'latitude'     => $lat,
            'longitude'    => $lng,
            'is_default'   => $makeDefault,
            'created_at'   => now(),
        ]);
        return redirect()->route('customer.addresses')->with('msg', 'Address saved.');
    }

    public function destroy(string $id)
    {
        $uid = session('user')['id'];
        DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->delete();
        return redirect()->route('customer.addresses')->with('msg', 'Address deleted.');
    }

    public function setDefault(string $id)
    {
        $uid = session('user')['id'];
        DB::table('user_addresses')->where('user_id', $uid)->update(['is_default' => 0]);
        DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->update(['is_default' => 1]);
        return redirect()->route('customer.addresses')->with('msg', 'Default address updated.');
    }
}
