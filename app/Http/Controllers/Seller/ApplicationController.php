<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    use UploadsFiles;

    /** Show seller application form */
    public function show()
    {
        // If already logged in as seller, redirect to seller dashboard
        $role = session('user')['role'] ?? null;
        if ($role === 'seller') return redirect()->route('seller.dashboard');
        if ($role === 'admin' || $role === 'superadmin') return redirect()->route('admin.dashboard');

        return view('seller.apply');
    }

    /** Step 1: Save basic info + send OTP */
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'shop_name'      => 'required|string|min:3|max:100',
            'full_name'      => 'required|string|min:2|max:100',
            'username'       => 'required|string|min:3|max:60|regex:/^[a-zA-Z0-9_]+$/',
            'email'          => 'required|email|max:150',
            'phone'          => 'required|regex:/^9[0-9]{9}$/|max:10',
            'city'           => 'required|string|max:80',
            'address'        => 'required|string|max:255',
            'gcash_number'   => 'required|regex:/^9[0-9]{9}$/|max:10',
            'description'    => 'nullable|string|max:500',
            'tier'           => 'required|in:basic,verified',
        ], [
            'shop_name.required'    => 'Shop name is required.',
            'shop_name.min'         => 'Shop name must be at least 3 characters.',
            'full_name.required'    => 'Full name is required.',
            'username.required'     => 'Username is required.',
            'username.min'          => 'Username must be at least 3 characters.',
            'username.max'          => 'Username must not exceed 60 characters.',
            'username.regex'        => 'Username may only contain letters, numbers, and underscores.',
            'email.required'        => 'Email address is required.',
            'email.email'           => 'Please enter a valid email address.',
            'phone.required'        => 'Phone number is required.',
            'phone.regex'           => 'Phone number must be 10 digits starting with 9.',
            'city.required'         => 'City is required.',
            'address.required'      => 'Business address is required.',
            'gcash_number.required' => 'GCash number is required.',
            'gcash_number.regex'    => 'GCash number must be 10 digits starting with 9.',
            'tier.required'         => 'Please select a seller tier.',
        ]);

        // Check for duplicate shop name
        $slug = Str::slug($validated['shop_name']);
        if (DB::table('shops')->where('shop_slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['shop_name' => 'A shop with this name already exists. Please choose a different name.']);
        }

        // Check for duplicate username
        if (DB::table('users')->where('username', $validated['username'])->exists()) {
            return back()->withInput()->withErrors(['username' => 'This username is already taken. Please choose another.']);
        }

        // Check for duplicate email in users
        if (DB::table('users')->where('email', $validated['email'])->exists()) {
            return back()->withInput()->withErrors(['email' => 'This email is already registered.']);
        }

        // Check duplicate phone
        if (DB::table('users')->where('phone', '+63'.$validated['phone'])->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is already registered.']);
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store in session
        session([
            'seller_apply' => array_merge($validated, [
                'otp'        => $otp,
                'otp_sent_at'=> now()->timestamp,
            ])
        ]);

        // Send OTP via SMS
        try {
            $phone = '+63' . $validated['phone'];
            \App\Helpers\SmsHelper::send($phone,
                "Your Cake Shop Platform OTP is: {$otp}\nDo not share this with anyone. Valid for 10 minutes."
            );
        } catch (\Exception $e) {}

        return redirect()->route('seller.apply.otp');
    }

    /** Show OTP verification step */
    public function showOtp()
    {
        if (!session('seller_apply')) return redirect()->route('seller.apply');
        return view('seller.apply_otp');
    }

    /** Step 2: Verify OTP + upload documents */
    public function verifyOtp(Request $request)
    {
        $apply = session('seller_apply');
        if (!$apply) return redirect()->route('seller.apply');

        $validated = $request->validate([
            'otp'             => 'required|digits:6',
            'password'        => 'required|min:8|confirmed',
            'valid_id'        => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'dti_certificate' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'shop_logo'       => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
            'shop_cover'      => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ], [
            'otp.required'        => 'Please enter the OTP.',
            'otp.digits'          => 'OTP must be exactly 6 digits.',
            'password.required'   => 'Password is required.',
            'password.min'        => 'Password must be at least 8 characters.',
            'password.confirmed'  => 'Passwords do not match.',
            'valid_id.required'   => 'A valid government ID is required.',
            'valid_id.mimes'      => 'ID must be a JPG, PNG, or PDF file.',
            'valid_id.max'        => 'ID file must not exceed 5MB.',
            'dti_certificate.mimes' => 'DTI certificate must be a JPG, PNG, or PDF file.',
            'dti_certificate.max'   => 'DTI certificate must not exceed 5MB.',
        ]);

        // Validate OTP
        if ($validated['otp'] !== (string)$apply['otp']) {
            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        // Check OTP expiry (10 minutes)
        if (now()->timestamp - ($apply['otp_sent_at'] ?? 0) > 600) {
            session()->forget('seller_apply');
            return redirect()->route('seller.apply')->withErrors(['phone' => 'OTP has expired. Please start over.']);
        }

        // Create user account
        $userId   = CakeshopHelper::generateId('users');
        $username = $apply['username'];

        DB::table('users')->insert([
            'id'          => $userId,
            'fullname'    => $apply['full_name'],
            'username'    => $username,
            'email'       => $apply['email'],
            'phone'       => '+63' . $apply['phone'],
            'password'    => password_hash($validated['password'], PASSWORD_DEFAULT),
            'role'        => 'seller',
            'is_verified' => 0,
            'created_at'  => now(),
        ]);

        // Create shop
        $shopId   = CakeshopHelper::generateId('shops');
        $slug     = Str::slug($apply['shop_name']);

        // Upload shop logo
        $logoPath  = null;
        $coverPath = null;
        if ($request->hasFile('shop_logo') && $request->file('shop_logo')->isValid()) {
            $logoPath = $this->uploadFile($request->file('shop_logo'), 'uploads/shops');
        }
        if ($request->hasFile('shop_cover') && $request->file('shop_cover')->isValid()) {
            $coverPath = $this->uploadFile($request->file('shop_cover'), 'uploads/shops');
        }

        // Get commission rate from platform settings
        $platform = DB::table('platform_settings')->first();
        $commRate = $apply['tier'] === 'verified'
            ? ($platform->commission_rate_verified ?? 0.00)
            : ($platform->commission_rate_basic    ?? 0.00);

        DB::table('shops')->insert([
            'id'              => $shopId,
            'seller_id'       => $userId,
            'shop_name'       => $apply['shop_name'],
            'shop_slug'       => $slug,
            'shop_logo'       => $logoPath,
            'shop_cover'      => $coverPath,
            'description'     => $apply['description'] ?? null,
            'address'         => $apply['address'],
            'city'            => $apply['city'],
            'contact_number'  => '+63'.$apply['phone'],
            'gcash_number'    => '+63'.$apply['gcash_number'],
            'status'          => 'pending',
            'tier'            => $apply['tier'],
            'commission_rate' => $commRate,
            'created_at'      => now(),
                    ]);

        // Upload documents
        $this->uploadDocument($request, 'valid_id', $shopId, 'valid_id', null);

        // Notify super admin
        $superAdmin = DB::table('users')->where('role', 'superadmin')->first();
        if ($superAdmin) {
            DB::table('notifications')->insert([
                'receiver_role'    => 'superadmin',
                'receiver_user_id' => $superAdmin->id,
                'title'            => 'New Seller Application',
                'message'          => $apply['shop_name'] . ' applied to become a ' . ucfirst($apply['tier']) . ' Seller.',
                'is_read'          => 0,
                'created_at'       => now(),
            ]);
        }

        session()->forget('seller_apply');
        return redirect()->route('seller.apply.success');
    }

    /** Upload and run OCR on document */
    private function uploadDocument(Request $request, string $field, string $shopId, string $type, ?string $shopName)
    {
        if (!$request->hasFile($field) || !$request->file($field)->isValid()) return;

        $file = $request->file($field);
        $path = $this->uploadFile($file, 'uploads/seller_docs');

        $ocrData = ['ocr_status' => null];

        DB::table('seller_documents')->insert([
            'shop_id'            => $shopId,
            'document_type'      => $type,
            'file_path'          => $path,
            'ocr_text'           => $ocrData['raw_text'] ?? null,
            'ocr_business_name'  => $ocrData['business_name'] ?? null,
            'ocr_expiry_date'    => $ocrData['expiry_date'] ?? null,
            'ocr_is_expired'     => isset($ocrData['is_expired']) ? (int)$ocrData['is_expired'] : null,
            'ocr_is_dti_document'=> isset($ocrData['is_dti']) ? (int)$ocrData['is_dti'] : null,
            'ocr_name_match'     => isset($ocrData['name_match']) ? (int)$ocrData['name_match'] : null,
            'ocr_status'         => $ocrData['ocr_status'],
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /** Run Tesseract OCR on DTI certificate */
    private function runOcr(string $filePath, string $shopName): array
    {
        $result = [
            'raw_text'      => null,
            'business_name' => null,
            'expiry_date'   => null,
            'is_expired'    => null,
            'is_dti'        => false,
            'name_match'    => false,
            'ocr_status'    => 'needs_review',
        ];

        try {
            // Run Tesseract OCR
            $escapedPath = escapeshellarg($filePath);
            $text = shell_exec("tesseract {$escapedPath} stdout 2>/dev/null");
            if (!$text) return $result;

            $result['raw_text'] = $text;

            // Check DTI keywords
            $isDTI = preg_match('/department of trade|DTI|business name registration/i', $text);
            $result['is_dti'] = (bool)$isDTI;

            // Extract business name
            if (preg_match('/business name[:\s]+([^\n\r]+)/i', $text, $m)) {
                $result['business_name'] = trim($m[1]);
            }

            // Extract expiry date
            if (preg_match('/expir[yed\s]+[:\s]*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $text, $m)) {
                $result['expiry_date'] = trim($m[1]);
                $result['is_expired']  = strtotime($m[1]) < time();
            }

            // Check name match (case-insensitive, partial)
            if ($result['business_name']) {
                $result['name_match'] = (bool) stripos($result['business_name'], substr($shopName, 0, 5));
            }

            // Determine OCR status
            $isExpired  = $result['is_expired'];
            $isDTIDoc   = $result['is_dti'];
            $nameMatch  = $result['name_match'];

            if ($isDTIDoc && !$isExpired && $nameMatch) {
                $result['ocr_status'] = 'likely_valid';
            } elseif (!$isDTIDoc || $isExpired) {
                $result['ocr_status'] = 'likely_invalid';
            } else {
                $result['ocr_status'] = 'needs_review';
            }

        } catch (\Exception $e) {
            $result['ocr_status'] = 'needs_review';
        }

        return $result;
    }

    public function success()
    {
        return view('seller.apply_success');
    }
}
