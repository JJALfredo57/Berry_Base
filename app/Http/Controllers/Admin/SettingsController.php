<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * FIX: Store in storage/app/public/uploads/* which symlinks to public/storage/uploads/*
     * This ensures correct path via asset('/storage/uploads/...') or url('/storage/uploads/...')
     */
    private function saveBrandFile($file, string $folder = 'branding'): string
    {
        if (!$file || !$file->isValid()) return '';
        if ($file->getSize() > 5 * 1024 * 1024) return '';
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return '';
        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        // Store using Laravel's "public" disk: storage/app/public/...
        $path = $file->storeAs('uploads/' . $folder, $filename, 'public');
        if (!$path) return '';
        // Return URL path: /storage/uploads/branding/filename.ext
        return '/storage/uploads/' . $folder . '/' . $filename;
    }

    private function saveProfilePhoto($file): string
    {
        if (!$file || !$file->isValid()) return '';
        if ($file->getSize() > 5 * 1024 * 1024) return '';
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) return '';
        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $path = $file->storeAs('uploads/profiles', $filename, 'public');
        if (!$path) return '';
        return '/storage/uploads/profiles/' . $filename;
    }

    public function index(Request $request)
    {
        $tab      = $request->input('tab', 'site');
        $settings = CakeshopHelper::getSettings();
        $files    = [];
        $backupsDir = storage_path('app/backups');
        if (is_dir($backupsDir)) {
            $files = array_filter(glob($backupsDir . '/*.sql') ?: [], 'is_file');
            usort($files, fn($a,$b) => filemtime($b) - filemtime($a));
        }
        return view('admin.settings', compact('tab','settings','files'));
    }

    public function savePaymongo(Request $request)
    {
        $user = session('user');
        $mode = $request->input('paymongo_mode', 'test');

        // Validate mode
        if (!in_array($mode, ['test', 'live'])) $mode = 'test';

        $testSecret = trim($request->input('paymongo_test_secret', ''));
        $testPublic = trim($request->input('paymongo_test_public', ''));
        $liveSecret = trim($request->input('paymongo_live_secret', ''));
        $livePublic = trim($request->input('paymongo_live_public', ''));

        // Warn if switching to live without live keys
        if ($mode === 'live' && (empty($liveSecret) || empty($livePublic))) {
            return redirect()->route('admin.settings.index', ['tab' => 'payments'])->with('err', '⚠️ Cannot switch to Live mode — please enter your Live Secret Key and Live Public Key first.');
        }

        DB::table('site_settings')->where('id', 1)->update([
            'paymongo_mode'        => $mode,
            'paymongo_test_secret' => $testSecret,
            'paymongo_test_public' => $testPublic,
            'paymongo_live_secret' => $liveSecret,
            'paymongo_live_public' => $livePublic,
            'updated_at'           => now(),
        ]);

        CakeshopHelper::logActivity($user['id'], $user['role'], 'Update PayMongo Settings', "Mode set to: {$mode}");
        return redirect()->route('admin.settings.index', ['tab' => 'payments'])->with('msg', '✅ PayMongo settings saved! Now in ' . strtoupper($mode) . ' mode.');
    }

    public function saveSite(Request $request)
    {
        $user     = session('user');
        $settings = CakeshopHelper::getSettings();

        $siteTitle = trim($request->input('site_title', 'Simple Cake Shop'));
        $tagline   = trim($request->input('tagline', ''));
        $bgType    = $request->input('bg_type', 'gradient');
        $bgColor   = $request->input('bg_color', '#ffffff');
        $gs        = $request->input('gradient_start', '#fff7fb');
        $ge        = $request->input('gradient_end', '#ffe3f1');
        $primary   = $request->input('primary_color', '#e91e63');
        $bgOpacity = max(0.05, min(1.0, (float) $request->input('bg_image_opacity', 1.0)));

        $logo  = $settings['logo_path'] ?? '';
        $bgImg = $settings['bg_image_path'] ?? '';

        // FIX: use 'public' disk storeAs for correct symlink path
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $newLogo = $this->saveBrandFile($request->file('logo'), 'branding');
            if ($newLogo) $logo = $newLogo;
        }
        if ($request->hasFile('bg_image') && $request->file('bg_image')->isValid()) {
            $newBg = $this->saveBrandFile($request->file('bg_image'), 'branding');
            if ($newBg) $bgImg = $newBg;
        }

        DB::table('site_settings')->updateOrInsert(['id' => 1], [
            'site_title'        => $siteTitle,
            'tagline'           => $tagline,
            'logo_path'         => $logo,
            'bg_type'           => $bgType,
            'bg_color'          => $bgColor,
            'gradient_start'    => $gs,
            'gradient_end'      => $ge,
            'bg_image_path'     => $bgImg,
            'bg_image_opacity'  => $bgOpacity,
            'primary_color'     => $primary,
            'vat_enabled'       => $request->input('vat_enabled', 0) ? 1 : 0,
            'vat_rate'          => max(0, min(100, (float) $request->input('vat_rate', 12))),
            'tin_number'        => trim($request->input('tin_number', '')),
            'timezone'          => $request->input('timezone', 'Asia/Manila'),
            'updated_at'        => now(),
        ]);

        CakeshopHelper::logActivity($user['id'], $user['role'], 'Update Site Settings', 'Updated branding/theme');
        return redirect()->route('admin.settings.index', ['tab' => 'site'])->with('msg', 'Settings saved successfully!');
    }

    public function saveShopLocation(Request $request)
    {
        $lat = (float) $request->input('shop_lat', 15.8107127);
        $lng = (float) $request->input('shop_lng', 120.4716710);

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)
            return back()->with('err', 'Invalid coordinates.');

        DB::table('site_settings')->updateOrInsert(['id' => 1], [
            'shop_lat'   => $lat,
            'shop_lng'   => $lng,
            'updated_at' => now(),
        ]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Update Shop Location', "Lat:{$lat}, Lng:{$lng}");
        return redirect()->route('admin.settings.index', ['tab' => 'location'])->with('msg', 'Shop location updated! ✅');
    }

    public function saveDailyCapacity(Request $request)
    {
        $user = session('user');
        DB::table('site_settings')->where('id', 1)->update([
            'daily_max_cakes'    => max(0, (int)$request->input('daily_max_cakes', 0)),
            'lead_1day_max'      => max(0, (int)$request->input('lead_1day_max', 0)),
            'lead_2day_max'      => max(0, (int)$request->input('lead_2day_max', 0)),
            'lead_3day_plus_max' => max(0, (int)$request->input('lead_3day_plus_max', 0)),
            'updated_at'         => now(),
        ]);
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Update Daily Capacity', 'Updated shop daily cake limits');
        return redirect()->route('admin.settings.index', ['tab' => 'capacity'])->with('msg', 'Daily capacity settings saved!');
    }

    public function saveProfile(Request $request)
    {
        $user     = session('user');
        $fullname = trim($request->input('fullname', ''));
        $email    = trim($request->input('email', ''));
        $phone    = trim($request->input('phone', ''));

        if (!$fullname || !$email || !$phone) {
            return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Please complete all fields.');
        }

        $exists = DB::table('users')
            ->where(fn($q) => $q->where('email', $email)->orWhere('phone', $phone))
            ->where('id', '<>', $user['id'])
            ->first();
        if ($exists) return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Email or phone already in use.');

        $data = compact('fullname','email','phone');

        // Profile photo upload for admin
        if ($request->hasFile('profile_photo') && $request->file('profile_photo')->isValid()) {
            $up = $this->saveProfilePhoto($request->file('profile_photo'));
            if ($up) $data['profile_photo'] = $up;
        }

        DB::table('users')->where('id', $user['id'])->update($data);

        $s = session('user');
        $s['fullname'] = $fullname;
        $s['email']    = $email;
        $s['phone']    = $phone;
        if (isset($data['profile_photo'])) $s['profile_photo'] = $data['profile_photo'];
        session(['user' => $s]);

        CakeshopHelper::logActivity($user['id'], 'admin', 'Update Profile', 'Admin updated profile info');
        return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('msg', 'Profile updated.');
    }

    // ── Change Password — Back to Step 1 ────────────────────────
    public function changePasswordBack(Request $request)
    {
        $request->session()->forget(['acp_otp','acp_expires','acp_sent_at','acp_channel','acp_step']);
        return redirect()->route('admin.settings.index', ['tab' => 'account']);
    }

    // ── Change Password — Step 1 POST: Send OTP ──────────────────
    public function changePasswordSendOtp(Request $request)
    {
        $user       = session('user');
        $adminUser  = DB::table('users')->where('id', $user['id'])->first();
        $otpChannel = $request->input('otp_channel', 'email');

        $otp     = (string) random_int(100000, 999999);
        $expires = time() + (10 * 60);

        $request->session()->put('acp_otp',     $otp);
        $request->session()->put('acp_expires', $expires);
        $request->session()->put('acp_sent_at', time());
        $request->session()->put('acp_channel', $otpChannel);
        $request->session()->put('acp_step',    2);

        $sent = false;
        $msg  = '';

        if ($otpChannel === 'sms') {
            $sent = SmsHelper::sendOtp($adminUser->phone, $otp, config('app.name', 'Cake Shop'));
            $maskedPhone = substr($adminUser->phone, 0, 4) . str_repeat('*', max(4, strlen($adminUser->phone) - 7)) . substr($adminUser->phone, -3);
            $msg = $sent
                ? "OTP sent via SMS to {$maskedPhone}. Valid for 10 minutes."
                : "⚠️ SMS failed to send. Please use Email OTP instead.";
        } else {
            $sent = CakeshopHelper::sendOtpEmail($adminUser->email, $otp, 'Change Password');
            $masked = substr($adminUser->email, 0, 2) . str_repeat('*', max(2, strpos($adminUser->email,'@') - 2)) . substr($adminUser->email, strpos($adminUser->email,'@'));
            $msg = $sent
                ? "OTP sent to {$masked}. Check your inbox and spam folder. Valid for 10 minutes."
                : "Email not configured. Please ask the administrator to set up Gmail SMTP.";
        }

        return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('msg', $msg);
    }

    // ── Change Password — Step 2 POST: Verify OTP ────────────────
    public function changePasswordVerifyOtp(Request $request)
    {
        $otpIn   = trim($request->input('otp', ''));
        $session = $request->session();

        if (!$session->get('acp_otp')) {
            $session->forget(['acp_otp','acp_expires','acp_sent_at','acp_channel','acp_step']);
            return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Session expired. Please try again.');
        }
        if (time() > (int)$session->get('acp_expires')) {
            $session->forget(['acp_otp','acp_expires','acp_sent_at','acp_channel','acp_step']);
            return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'OTP expired. Please request a new one.');
        }
        if ($otpIn !== (string)$session->get('acp_otp')) {
            return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Incorrect OTP. Please try again.');
        }

        $session->put('acp_step', 3);
        $session->forget(['acp_otp','acp_expires','acp_sent_at','acp_channel']);
        return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('msg', 'OTP verified! Please set your new password.');
    }

    // ── Change Password — Step 3 POST: Save new password ─────────
    public function changePassword(Request $request)
    {
        $user = session('user');
        $new     = $request->input('new_password', '');
        $confirm = $request->input('confirm_password', '');

        if ($request->session()->get('acp_step') !== 3) {
            return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Please verify OTP first.');
        }

        if ($new !== $confirm) return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Passwords do not match.');
        if (strlen($new) < 8) return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Password must be at least 8 characters.');
        if (!preg_match('/[A-Z]/', $new)) return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Password must contain at least 1 uppercase letter.');
        if (!preg_match('/[0-9]/', $new)) return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Password must contain at least 1 number.');
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $new)) return redirect()->route('admin.settings.index', ['tab' => 'account'])->with('err', 'Password must contain at least 1 special character.');

        DB::table('users')->where('id', $user['id'])->update(['password' => password_hash($new, PASSWORD_DEFAULT)]);
        CakeshopHelper::logActivity($user['id'], 'admin', 'Change Password', 'Admin changed password via OTP');

        $request->session()->forget('acp_step');
        session()->flush();
        return redirect()->route('login')->with('msg', 'Password changed successfully. Please login again.');
    }

    public function createBackup()
    {
        $user       = session('user');
        $backupsDir = storage_path('app/backups');
        if (!is_dir($backupsDir)) mkdir($backupsDir, 0755, true);
        $content = CakeshopHelper::exportSql(config('database.connections.mysql.database'));
        $fname   = 'cakeshop_db_' . date('Y-m-d_H-i-s') . '.sql';
        $path    = $backupsDir . '/' . $fname;
        if (file_put_contents($path, $content) === false) {
            return redirect()->route('admin.settings.index', ['tab' => 'backup'])->with('err', 'Failed to write backup.');
        }
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Backup Database', $fname);
        return redirect()->route('admin.settings.index', ['tab' => 'backup'])->with('msg', "Backup created: {$fname}");
    }

    public function restore(Request $request)
    {
        $user       = session('user');
        $file       = preg_replace('/[^A-Za-z0-9._-]/', '', $request->input('file', ''));
        $backupsDir = storage_path('app/backups');
        $path       = $backupsDir . '/' . $file;
        if (!$file || !is_file($path)) {
            return redirect()->route('admin.settings.index', ['tab' => 'backup'])->with('err', 'Backup file not found.');
        }
        DB::getPdo()->exec(file_get_contents($path));
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Restore Database', $file);
        return redirect()->route('admin.settings.index', ['tab' => 'backup'])->with('msg', "Database restored from: {$file}");
    }

    public function deleteBackup(Request $request)
    {
        $user       = session('user');
        $file       = preg_replace('/[^A-Za-z0-9._-]/', '', $request->input('file', ''));
        $path       = storage_path('app/backups') . '/' . $file;
        if ($file && is_file($path)) {
            unlink($path);
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Delete Backup', $file);
        }
        return redirect()->route('admin.settings.index', ['tab' => 'backup'])->with('msg', 'Backup deleted.');
    }
}
