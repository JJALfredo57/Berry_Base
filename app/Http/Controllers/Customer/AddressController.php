<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddressController extends Controller
{
    public function index()
    {
        $uid = session('user')['id'];
        $hasArchive = Schema::hasColumn('user_addresses', 'archived_at');

        $listQuery = DB::table('user_addresses')
            ->where('user_id', $uid)
            ->orderByDesc('is_default')
            ->orderByDesc('id');
        if ($hasArchive) $listQuery->whereNull('archived_at');
        $list = $listQuery->get();

        $archived = $hasArchive
            ? DB::table('user_addresses')->where('user_id', $uid)->whereNotNull('archived_at')->orderByDesc('id')->get()
            : collect();

        $defaultAddrQuery = DB::table('user_addresses')->where('user_id', $uid)->where('is_default', 1);
        if ($hasArchive) $defaultAddrQuery->whereNull('archived_at');
        $defaultAddr = $defaultAddrQuery->first();

        return view('customer.addresses', compact('list', 'archived', 'defaultAddr'));
    }

    public function store(Request $request)
    {
        $uid = session('user')['id'];
        $data = $this->validatedAddressPayload($request);
        if (!$data['ok']) return back()->with('error', $data['message'])->withInput();

        $makeDefault = $request->has('make_default') ? 1 : 0;
        if ($makeDefault || !$this->hasActiveAddress($uid)) {
            $this->clearDefault($uid);
            $makeDefault = 1;
        }

        DB::table('user_addresses')->insert([
            'user_id' => $uid,
            'label_name' => $data['label'],
            'full_address' => $data['address'],
            'latitude' => $data['lat'],
            'longitude' => $data['lng'],
            'is_default' => $makeDefault,
            'created_at' => now(),
        ]);

        return redirect()->route('customer.addresses')->with('msg', 'Address saved.');
    }

    public function update(Request $request, string $id)
    {
        $uid = session('user')['id'];
        $data = $this->validatedAddressPayload($request);
        if (!$data['ok']) return back()->with('error', $data['message'])->withInput();

        $address = $this->activeAddressQuery($uid)->where('id', $id)->first();
        if (!$address) return back()->with('error', 'Address not found.');

        $makeDefault = $request->has('make_default') ? 1 : (int)($address->is_default ?? 0);
        if ($makeDefault) $this->clearDefault($uid);

        DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->update([
            'label_name' => $data['label'],
            'full_address' => $data['address'],
            'latitude' => $data['lat'],
            'longitude' => $data['lng'],
            'is_default' => $makeDefault,
        ]);

        return redirect()->route('customer.addresses')->with('msg', 'Address updated.');
    }

    public function destroy(string $id)
    {
        return $this->archive($id);
    }

    public function archive(string $id)
    {
        $uid = session('user')['id'];
        $address = DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->first();
        if (!$address) return redirect()->route('customer.addresses')->with('error', 'Address not found.');

        if (Schema::hasColumn('user_addresses', 'archived_at')) {
            DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->update([
                'is_default' => 0,
                'archived_at' => now(),
            ]);
        } else {
            DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->delete();
        }

        if ((int)($address->is_default ?? 0) === 1) {
            $next = $this->activeAddressQuery($uid)->where('id', '<>', $id)->orderByDesc('id')->first();
            if ($next) DB::table('user_addresses')->where('id', $next->id)->where('user_id', $uid)->update(['is_default' => 1]);
        }

        return redirect()->route('customer.addresses')->with('msg', 'Address archived.');
    }

    public function restore(string $id)
    {
        $uid = session('user')['id'];
        if (!Schema::hasColumn('user_addresses', 'archived_at')) {
            return redirect()->route('customer.addresses')->with('error', 'Address archive is not available yet.');
        }

        $address = DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->first();
        if (!$address) return redirect()->route('customer.addresses')->with('error', 'Address not found.');

        $makeDefault = $this->hasActiveDefault($uid) ? 0 : 1;
        DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->update([
            'archived_at' => null,
            'is_default' => $makeDefault,
        ]);

        return redirect()->route('customer.addresses')->with('msg', 'Address restored.');
    }

    public function setDefault(string $id)
    {
        $uid = session('user')['id'];
        $address = $this->activeAddressQuery($uid)->where('id', $id)->first();
        if (!$address) return redirect()->route('customer.addresses')->with('error', 'Address not found.');

        $this->clearDefault($uid);
        DB::table('user_addresses')->where('id', $id)->where('user_id', $uid)->update(['is_default' => 1]);

        return redirect()->route('customer.addresses')->with('msg', 'Default address updated.');
    }

    private function validatedAddressPayload(Request $request): array
    {
        $label = trim($request->input('label_name', 'Home')) ?: 'Address';
        $address = trim($request->input('full_address', ''));
        $lat = $request->input('latitude') !== '' ? (float)$request->input('latitude') : 0;
        $lng = $request->input('longitude') !== '' ? (float)$request->input('longitude') : 0;

        if ($address === '' || $lat == 0 || $lng == 0) {
            return ['ok' => false, 'message' => 'Please pin your exact location and enter the complete address.'];
        }

        return ['ok' => true, 'label' => substr($label, 0, 60), 'address' => $address, 'lat' => $lat, 'lng' => $lng];
    }

    private function activeAddressQuery(string $uid)
    {
        $query = DB::table('user_addresses')->where('user_id', $uid);
        if (Schema::hasColumn('user_addresses', 'archived_at')) $query->whereNull('archived_at');
        return $query;
    }

    private function clearDefault(string $uid): void
    {
        $this->activeAddressQuery($uid)->update(['is_default' => 0]);
    }

    private function hasActiveAddress(string $uid): bool
    {
        return $this->activeAddressQuery($uid)->exists();
    }

    private function hasActiveDefault(string $uid): bool
    {
        return $this->activeAddressQuery($uid)->where('is_default', 1)->exists();
    }
}