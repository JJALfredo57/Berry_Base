<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    use UploadsFiles;
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

        if ($request->hasFile('shop_logo') && $request->file('shop_logo')->isValid()) {
            $updates['shop_logo'] = $this->uploadFile($request->file('shop_logo'), 'uploads/shops');
        }
        if ($request->hasFile('shop_cover') && $request->file('shop_cover')->isValid()) {
            $updates['shop_cover'] = $this->uploadFile($request->file('shop_cover'), 'uploads/shops');
        }

        DB::table('shops')->where('id', $shop->id)->update($updates);
        return redirect()->to(route('seller.settings').'?tab=profile')->with('msg', 'Shop profile updated successfully.');
    }

    public function saveDailyCapacity(Request $request)
    {
        $shop = $this->getShop();
        $this->upsertSettings($shop->id, [
            'daily_max_cakes'    => max(0, (int)$request->input('daily_max_cakes', 0)),
            'lead_1day_max'      => max(0, (int)$request->input('lead_1day_max', 0)),
            'lead_2day_max'      => max(0, (int)$request->input('lead_2day_max', 0)),
            'lead_3day_plus_max' => max(0, (int)$request->input('lead_3day_plus_max', 0)),
        ]);
        return redirect()->to(route('seller.settings').'?tab=capacity')->with('msg', 'Daily capacity settings saved!');
    }

    private function upsertSettings(string $shopId, array $data): void
    {
        $data['updated_at'] = now();
        DB::table('site_settings')->updateOrInsert(['shop_id' => $shopId], $data);
    }

    public function saveShopLocation(Request $request)
    {
        $shop = $this->getShop();
        $lat  = $request->input('shop_lat') !== null ? (float) $request->input('shop_lat') : null;
        $lng  = $request->input('shop_lng') !== null ? (float) $request->input('shop_lng') : null;
        $addr = trim($request->input('shop_address', ''));

        $this->upsertSettings($shop->id, [
            'shop_lat'     => $lat,
            'shop_lng'     => $lng,
            'shop_address' => $addr ?: null,
        ]);

        if ($request->input('_ajax') === '1') {
            return response()->json(['ok' => true, 'lat' => $lat, 'lng' => $lng]);
        }

        return redirect()->route('seller.settings')->with('msg', 'Shop location saved.');
    }

    public function saveDeliveryCalc(Request $request)
    {
        $shop = $this->getShop();
        $this->upsertSettings($shop->id, [
            'fee_per_meter'       => max(0, (float) $request->input('fee_per_meter', 0.05)),
            'maintenance_per_km'  => max(0, (float) $request->input('maintenance_per_km', 5)),
            'fuel_per_km'         => max(0, (float) $request->input('fuel_per_km', 8)),
            'free_delivery_radius'=> max(0, (int)   $request->input('free_delivery_radius', 0)),
        ]);
        return redirect()->to(route('seller.settings').'?tab=delivery')->with('msg', 'Delivery fee settings saved.');
    }

    public function requestUpgrade(Request $request)
    {
        $shop = $this->getShop();

        if ($shop->tier === 'verified') {
            return back()->with('err', 'Your shop is already on the Verified tier.');
        }
        if (($shop->upgrade_request_status ?? null) === 'pending') {
            return back()->with('err', 'You already have a pending upgrade request. Please wait for the review.');
        }

        $request->validate([
            'business_permit' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'business_permit.required' => 'Please upload your Business Permit or DTI Certificate.',
            'business_permit.mimes'    => 'File must be JPG, PNG, or PDF.',
            'business_permit.max'      => 'File must not exceed 5MB.',
        ]);

        try {
            $file = $request->file('business_permit');
            $path = $this->uploadFile($file, 'uploads/seller_docs');

            DB::table('seller_documents')->insert([
                'shop_id'            => $shop->id,
                'document_type'      => 'upgrade_permit',
                'file_path'          => $path,
                'ocr_text'           => null,
                'ocr_business_name'  => null,
                'ocr_expiry_date'    => null,
                'ocr_is_expired'     => null,
                'ocr_is_dti_document'=> null,
                'ocr_name_match'     => null,
                'ocr_status'         => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            DB::table('shops')->where('id', $shop->id)->update([
                'upgrade_request_status' => 'pending',
                'upgrade_request_note'   => null,
                'upgrade_requested_at'   => now(),
            ]);

            // Notify superadmin
            try {
                $superAdmin = DB::table('users')->where('role', 'superadmin')->first();
                if ($superAdmin) {
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'superadmin',
                        'receiver_user_id' => $superAdmin->id,
                        'title'            => 'Upgrade Request: ' . $shop->shop_name,
                        'message'          => $shop->shop_name . ' is requesting to upgrade from Basic to Verified Seller.',
                        'is_read' => false,
                        'created_at'       => now(),
                    ]);
                }
            } catch (\Throwable $e) {}

        } catch (\Throwable $e) {
            return back()->with('err', 'Failed to submit upgrade request. Please try again or contact support.');
        }

        return redirect()->to(route('seller.settings').'?tab=upgrade')
            ->with('msg', 'Upgrade request submitted! Our team will review your documents within 1-3 business days.');
    }

    public function saveAppearance(Request $request)
    {
        $shop = $this->getShop();

        $request->validate([
            'shop_bg_color'          => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'shop_bg_gradient_start' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'shop_bg_gradient_end'   => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'shop_bg_image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $bgType    = $request->input('shop_bg_type', 'color');
        $bgOpacity = max(0.1, min(1.0, (float) $request->input('shop_bg_opacity', 1.0)));

        $data = [
            'bg_type'          => $bgType,
            'bg_color'         => $request->input('shop_bg_color', '#f9f9f9'),
            'gradient_start'   => $request->input('shop_bg_gradient_start', '#fff7fb'),
            'gradient_end'     => $request->input('shop_bg_gradient_end', '#ffe3f1'),
            'bg_image_opacity' => $bgOpacity,
        ];

        if ($request->hasFile('shop_bg_image') && $request->file('shop_bg_image')->isValid()) {
            $data['bg_image_path'] = $this->uploadFile($request->file('shop_bg_image'), 'uploads/shops');
        }

        $this->upsertSettings($shop->id, $data);
        return redirect()->to(route('seller.settings').'?tab=appearance')->with('msg', 'Shop page appearance saved!');
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
        return redirect()->to(route('seller.settings').'?tab=password')->with('msg', 'Password changed successfully.');
    }
}
