<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->first();
        if (!$shop) abort(403);
        return $shop;
    }

    public function index()
    {
        $shop         = $this->getShop();
        $shopSettings = DB::table('site_settings')
            ->where('shop_id', $shop->id)->first()
            ?? (object)[];
        return view('seller.settings', compact('shop', 'shopSettings'));
    }

    public function updateShop(Request $request)
    {
        $shop = $this->getShop();

        $validated = $request->validate([
            'shop_name'    => 'required|string|min:3|max:100',
            'description'  => 'nullable|string|max:500',
            'city'         => 'required|string|max:80',
            'address'      => 'required|string|max:255',
            'gcash_number' => 'required|regex:/^(\+63)?9[0-9]{9}$/|max:13',
            'theme_color'  => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'shop_logo'    => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
            'shop_cover'   => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ],[
            'shop_name.required'    => 'Shop name is required.',
            'shop_name.min'         => 'Shop name must be at least 3 characters.',
            'city.required'         => 'City is required.',
            'address.required'      => 'Address is required.',
            'gcash_number.required' => 'GCash number is required.',
            'gcash_number.regex'    => 'Please enter a valid GCash number.',
            'theme_color.regex'     => 'Theme color must be a valid hex color.',
            'shop_logo.mimes'       => 'Logo must be JPG or PNG.',
            'shop_logo.max'         => 'Logo must not exceed 3MB.',
            'shop_cover.max'        => 'Cover photo must not exceed 5MB.',
        ]);

        // Check shop name uniqueness (exclude own shop)
        $slug = Str::slug($validated['shop_name']);
        $duplicate = DB::table('shops')
            ->where('shop_slug', $slug)
            ->where('id', '!=', $shop->id)
            ->exists();
        if ($duplicate) return back()->withInput()->with('err', 'A shop with this name already exists. Please choose a different name.');

        $updates = [
            'shop_name'      => $validated['shop_name'],
            'shop_slug'      => $slug,
            'description'    => $validated['description'] ?? null,
            'city'           => $validated['city'],
            'address'        => $validated['address'],
            'gcash_number'   => $validated['gcash_number'],
            'theme_color'    => $validated['theme_color'] ?? null,
        ];

        // Logo upload
        if ($request->hasFile('shop_logo') && $request->file('shop_logo')->isValid()) {
            $fn = date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$request->file('shop_logo')->getClientOriginalExtension();
            $request->file('shop_logo')->storeAs('uploads/shops', $fn, 'public');
            $updates['shop_logo'] = '/storage/uploads/shops/'.$fn;
        }

        // Cover upload
        if ($request->hasFile('shop_cover') && $request->file('shop_cover')->isValid()) {
            $fn = date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$request->file('shop_cover')->getClientOriginalExtension();
            $request->file('shop_cover')->storeAs('uploads/shops', $fn, 'public');
            $updates['shop_cover'] = '/storage/uploads/shops/'.$fn;
        }

        DB::table('shops')->where('id', $shop->id)->update($updates);
        return back()->with('msg', 'Shop profile updated successfully.');
    }

    public function saveDailyCapacity(Request $request)
    {
        $shop = $this->getShop();
        $data = [
            'daily_max_cakes'    => max(0, (int)$request->input('daily_max_cakes', 0)),
            'lead_1day_max'      => max(0, (int)$request->input('lead_1day_max', 0)),
            'lead_2day_max'      => max(0, (int)$request->input('lead_2day_max', 0)),
            'lead_3day_plus_max' => max(0, (int)$request->input('lead_3day_plus_max', 0)),
            'updated_at'         => now(),
        ];
        $exists = DB::table('site_settings')->where('shop_id', $shop->id)->exists();
        if ($exists) {
            DB::table('site_settings')->where('shop_id', $shop->id)->update($data);
        } else {
            $nextId = (DB::table('site_settings')->max('id') ?? 0) + 1;
            DB::table('site_settings')->insert(array_merge($data, [
                'id'      => $nextId,
                'shop_id' => $shop->id,
            ]));
        }
        return redirect()->route('seller.settings')->with('msg', 'Daily capacity settings saved!');
    }

    public function updatePassword(Request $request)
    {
        $user = session('user');
        $validated = $request->validate([
            'current_password'      => 'required',
            'password'              => 'required|min:8|confirmed',
        ],[
            'current_password.required' => 'Current password is required.',
            'password.required'         => 'New password is required.',
            'password.min'              => 'Password must be at least 8 characters.',
            'password.confirmed'        => 'Passwords do not match.',
        ]);

        $dbUser = DB::table('users')->where('id', $user['id'])->first();
        if (!$dbUser || !password_verify($validated['current_password'], $dbUser->password)) {
            return back()->with('err', 'Current password is incorrect.');
        }

        DB::table('users')->where('id', $user['id'])->update([
            'password'   => password_hash($validated['password'], PASSWORD_DEFAULT),
                    ]);
        return back()->with('msg', 'Password changed successfully.');
    }
}
