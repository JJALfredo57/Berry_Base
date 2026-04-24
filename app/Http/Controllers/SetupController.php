<?php
namespace App\Http\Controllers;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public function show(Request $request)
    {
        if (DB::table('users')->whereIn('role',['admin','superadmin'])->exists()) {
            return redirect()->route('superadmin.login')->with('msg', 'System already set up. Please login.');
        }
        $step = $request->session()->get('setup_step', 1);
        return view('setup', compact('step'));
    }

    // ── Step 1 POST: Validate form + send OTP ────────────────────
    public function store(Request $request)
    {
        if (DB::table('users')->whereIn('role',['admin','superadmin'])->exists()) {
            return redirect()->route('login');
        }

        $fullname   = trim($request->input('fullname', ''));
        $email      = trim($request->input('email', ''));
        $phone      = trim($request->input('phone', ''));
        $username   = trim($request->input('username', ''));
        $password   = $request->input('password', '');
        $confirm    = $request->input('confirm_password', '');
        $siteTitle  = trim($request->input('site_title', 'My Cake Shop'));
        $otpChannel = $request->input('otp_channel', 'email');

        // Validate all fields
        if (!$fullname || !$email || !$phone || !$username || !$password) {
            return back()->with('error', 'Please complete all fields.')->withInput();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Invalid email address.')->withInput();
        }
        if (strlen($password) < 8) {
            return back()->with('error', 'Password must be at least 8 characters.')->withInput();
        }
        if ($password !== $confirm) {
            return back()->with('error', 'Passwords do not match.')->withInput();
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return back()->with('error', 'Password must contain at least 1 uppercase letter.')->withInput();
        }
        if (!preg_match('/[0-9]/', $password)) {
            return back()->with('error', 'Password must contain at least 1 number.')->withInput();
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $password)) {
            return back()->with('error', 'Password must contain at least 1 special character.')->withInput();
        }

        // Store pending data in session
        $otp     = (string) random_int(100000, 999999);
        $expires = time() + (10 * 60);

        $request->session()->put('setup_pending', compact(
            'fullname','email','phone','username','password','siteTitle'
        ));
        $request->session()->put('setup_otp',      $otp);
        $request->session()->put('setup_expires',  $expires);
        $request->session()->put('setup_sent_at',  time());
        $request->session()->put('setup_channel',  $otpChannel);
        $request->session()->put('setup_step',     2);

        // Send OTP
        $sent = false;
        $msg  = '';

        if ($otpChannel === 'sms') {
            $sent = SmsHelper::sendOtp($phone, $otp, config('app.name', 'Cake Shop'));
            $maskedPhone = substr($phone, 0, 4) . str_repeat('*', max(4, strlen($phone) - 7)) . substr($phone, -3);
            $msg = $sent
                ? "OTP sent via SMS to {$maskedPhone}. Valid for 10 minutes."
                : "⚠️ SMS failed to send. Please use Email OTP instead.";
        } else {
            $sent = CakeshopHelper::sendOtpEmail($email, $otp, 'Admin Account Verification');
            $masked = substr($email, 0, 2) . str_repeat('*', max(2, strpos($email,'@') - 2)) . substr($email, strpos($email,'@'));
            $msg = $sent
                ? "OTP sent to {$masked}. Check your inbox and spam folder. Valid for 10 minutes."
                : "Email not configured. Please use SMS OTP instead.";
        }

        return redirect()->route('setup')->with('msg', $msg);
    }

    // ── Step 2 POST: Verify OTP + create admin ───────────────────
    public function verify(Request $request)
    {
        if (DB::table('users')->whereIn('role',['admin','superadmin'])->exists()) {
            return redirect()->route('login');
        }

        $otpIn   = trim($request->input('otp', ''));
        $session = $request->session();
        $pending = $session->get('setup_pending');

        if (!$pending || !$session->get('setup_otp')) {
            $session->forget(['setup_pending','setup_otp','setup_expires','setup_sent_at','setup_channel','setup_step']);
            return redirect()->route('setup')->with('error', 'Session expired. Please start again.');
        }
        if (time() > (int)$session->get('setup_expires')) {
            $session->forget(['setup_pending','setup_otp','setup_expires','setup_sent_at','setup_channel','setup_step']);
            return redirect()->route('setup')->with('error', 'OTP expired. Please start again.');
        }
        if ($otpIn !== (string)$session->get('setup_otp')) {
            return redirect()->route('setup')->with('error', 'Incorrect OTP. Please try again.');
        }

        // Create admin account
        $newId = CakeshopHelper::generateId('users');
        DB::table('users')->insert([
            'id'          => $newId,
            'fullname'    => $pending['fullname'],
            'email'       => $pending['email'],
            'phone'       => $pending['phone'],
            'username'    => $pending['username'],
            'password'    => password_hash($pending['password'], PASSWORD_DEFAULT),
            'role'        => 'superadmin',
            'is_verified' => 1,
            'created_at'  => now(),
        ]);

        // Create initial site settings
        if (!DB::table('site_settings')->where('id',1)->exists()) {
            DB::table('site_settings')->insert([
                'id'             => 1,
                'site_title'     => $pending['siteTitle'],
                'tagline'        => 'Online Cake Order System',
                'logo_path'      => '',
                'bg_type'        => 'gradient',
                'bg_color'       => '#fff7fb',
                'gradient_start' => '#fff7fb',
                'gradient_end'   => '#ffe3f1',
                'bg_image_path'  => null,
                'primary_color'  => '#e91e63',
            ]);
        }

        // Create default platform settings
        if (!DB::table('platform_settings')->exists()) {
            DB::table('platform_settings')->insert([
                'platform_name'            => $pending['siteTitle'],
                'commission_rate_basic'    => 0.00,
                'commission_rate_verified' => 0.00,
                'max_products_basic'       => 20,
                'created_at'               => now(),
                            ]);
        }

        CakeshopHelper::logActivity($newId, 'superadmin', 'Setup', 'Initial superadmin account created via OTP verification');
        $session->forget(['setup_pending','setup_otp','setup_expires','setup_sent_at','setup_channel','setup_step']);
        // Clear entire session so no stale data
        $session->flush();
        $session->regenerate();
        return redirect()->route('superadmin.login')->with('msg', 'Setup complete! Please login with your Super Admin account.');
    }

    // ── Back to Step 1 ───────────────────────────────────────────
    public function back(Request $request)
    {
        $request->session()->forget(['setup_pending','setup_otp','setup_expires','setup_sent_at','setup_channel','setup_step']);
        return redirect()->route('setup');
    }
}
