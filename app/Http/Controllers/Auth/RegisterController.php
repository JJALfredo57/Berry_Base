<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        $step = $request->session()->get('reg_step', 1);
        return view('auth.register', compact('step'));
    }

    public function sendOtp(Request $request)
    {
        $fullname = trim($request->input('fullname', ''));
        $email    = strtolower(trim($request->input('email', '')));
        $phone    = trim($request->input('phone', ''));
        $username = trim($request->input('username', ''));
        $otpChannel = $request->input('otp_channel', 'email'); // 'email' or 'sms'

        if (!$fullname || !$email || !$phone || !$username) {
            return back()->with('error', 'Please complete all fields.')->withInput();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Invalid email address.')->withInput();
        }

        $exists = DB::table('users')
            ->where(function($q) use ($username, $email, $phone) {
                $q->where('username', $username)
                  ->orWhereRaw('LOWER(email) = ?', [$email])
                  ->orWhere('phone', $phone);
            })->first();

        if ($exists) {
            return back()->with('error', 'Username, email, or phone number already exists.')->withInput();
        }

        $otp     = (string) random_int(100000, 999999);
        $expires = time() + (10 * 60);

        $request->session()->put('reg_pending', compact('fullname','email','phone','username','otp','expires','otpChannel'));
        $request->session()->put('reg_step', 2);

        $sent = false;
        $msg  = '';

        if ($otpChannel === 'sms') {
            // Send via SMS
            $sent = SmsHelper::sendOtp($phone, $otp, config('app.name', 'Cake Shop'));
            $maskedPhone = substr($phone, 0, 4) . str_repeat('*', max(4, strlen($phone) - 7)) . substr($phone, -3);
            $msg = $sent
                ? "OTP sent via SMS to {$maskedPhone}. Check your messages. Valid for 10 minutes."
                : "SMS not configured. Please use Email OTP instead.";
        } else {
            // Send via Email
            $sent = CakeshopHelper::sendOtpEmail($email, $otp, 'Account Verification');
            $masked = substr($email, 0, 2) . str_repeat('*', max(2, strpos($email,'@') - 2)) . substr($email, strpos($email,'@'));
            $msg = $sent
                ? "OTP sent to {$masked}. Check your inbox and spam folder. Valid for 10 minutes."
                : "Email not yet configured. Please ask the administrator to set up Gmail SMTP.";
        }

        return redirect()->route('register')->with('msg', $msg);
    }

    public function verify(Request $request)
    {
        $otpIn    = trim($request->input('otp', ''));
        $password = $request->input('password', '');
        $confirm  = $request->input('confirm_password', '');
        $pending  = $request->session()->get('reg_pending');

        if (!$pending) {
            $request->session()->put('reg_step', 1);
            return redirect()->route('register')->with('error', 'Session expired. Please register again.');
        }
        if (time() > (int)$pending['expires']) {
            $request->session()->forget(['reg_pending','reg_step']);
            return redirect()->route('register')->with('error', 'OTP expired. Please try again.');
        }
        if ($otpIn !== (string)$pending['otp']) {
            return redirect()->route('register')->with('error', 'Invalid OTP. Please check and try again.');
        }
        if (strlen($password) < 8) {
            return redirect()->route('register')->with('error', 'Password must be at least 8 characters.');
        }
        if ($password !== $confirm) {
            return redirect()->route('register')->with('error', 'Passwords do not match.');
        }

        // Validate password requirements
        if (!preg_match('/[A-Z]/', $password)) {
            return redirect()->route('register')->with('error', 'Password must contain at least 1 uppercase letter.');
        }
        if (!preg_match('/[0-9]/', $password)) {
            return redirect()->route('register')->with('error', 'Password must contain at least 1 number.');
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $password)) {
            return redirect()->route('register')->with('error', 'Password must contain at least 1 special character.');
        }

        $newId = CakeshopHelper::generateId('users');
        DB::table('users')->insert([
            'id'          => $newId,
            'fullname'    => $pending['fullname'],
            'email'       => $pending['email'],
            'phone'       => $pending['phone'],
            'username'    => $pending['username'],
            'password'    => password_hash($password, PASSWORD_DEFAULT),
            'role'        => 'customer',
            'is_verified' => 1,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity($newId, 'customer', 'Register', 'New customer registered');
        $request->session()->forget(['reg_pending','reg_step']);
        return redirect()->route('login')->with('msg', 'Registration successful! You can now login.');
    }
}
