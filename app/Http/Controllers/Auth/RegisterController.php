<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use App\Services\CustomerIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function __construct(private CustomerIdentityService $identity)
    {
    }

    public function show(Request $request)
    {
        $step = $request->session()->get('reg_step', 1);
        return view('auth.register', compact('step'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fullname' => ['required', 'string', 'max:100', 'regex:/.*\\S.*/'],
            'email' => 'required|string|email:rfc|max:150',
            'phone' => 'required|string|max:30',
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9_]+$/'],
            'otp_channel' => 'nullable|in:email,sms',
        ]);
        $fullname = trim($request->input('fullname', ''));
        $email    = strtolower(trim($request->input('email', '')));
        $phone    = trim($request->input('phone', ''));
        $username = trim($request->input('username', ''));
        $otpChannel = $request->input('otp_channel', 'email'); // 'email' or 'sms'

        if (!$fullname || !$email || !$phone || !$username) {
            return back()->with('error', 'Please complete all fields.')->withInput();
        }
        $phone = $this->identity->normalizePhone($phone);
        if (!$phone) {
            return back()->with('error', 'Please enter a valid Philippine mobile number.')->withInput();
        }

        $exists = $this->identity->userConflict($username, $email, $phone);
        if ($exists) {
            return back()->with('error', $this->identity->conflictMessage($exists, $username, $email, $phone))->withInput();
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

        if (!$sent) {
            $request->session()->forget(['reg_pending','reg_step']);
            return redirect()->route('register')->with('error', $msg)->withInput();
        }

        return redirect()->route('register')->with('msg', $msg);
    }

    public function validateField(Request $request)
    {
        $request->validate([
            'field' => 'required|in:username,email,phone',
            'value' => 'nullable|string|max:150',
        ]);

        $field = (string) $request->input('field');
        $value = trim((string) $request->input('value', ''));

        if ($field === 'username') {
            if (!preg_match('/^[A-Za-z0-9_]{3,60}$/', $value)) {
                return response()->json(['valid' => false, 'message' => 'Use at least 3 letters, numbers, or underscores only.']);
            }
            $conflict = $this->identity->userConflict($value, null, null);
        } elseif ($field === 'email') {
            $value = strtolower($value);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['valid' => false, 'message' => 'Enter a valid email address, such as name@example.com.']);
            }
            $conflict = $this->identity->userConflict(null, $value, null);
        } else {
            $phone = $this->identity->normalizePhone($value);
            if (!$phone) {
                return response()->json(['valid' => false, 'message' => 'Use a valid PH mobile number: 9XXXXXXXXX, 09XXXXXXXXX, or +639XXXXXXXXX.']);
            }
            $conflict = $this->identity->userConflict(null, null, $phone);
            $value = $phone;
        }

        if ($conflict) {
            return response()->json([
                'valid' => false,
                'message' => $this->identity->conflictMessage(
                    $conflict,
                    $field === 'username' ? $value : null,
                    $field === 'email' ? $value : null,
                    $field === 'phone' ? $value : null,
                ),
            ]);
        }

        return response()->json(['valid' => true, 'normalized' => $field === 'phone' ? $value : null]);
    }

    public function showVerify(Request $request)
    {
        if (!$request->session()->has('reg_pending')) {
            $request->session()->put('reg_step', 1);
            return redirect()->route('register')->with('error', 'Please complete your account details first.');
        }

        $request->session()->put('reg_step', 2);
        return $this->show($request);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|max:10',
            'password' => 'nullable|string|max:256',
            'confirm_password' => 'nullable|string|max:256',
        ]);
        $otpIn    = trim((string) $request->input('otp', ''));
        $password = (string) $request->input('password', '');
        $confirm  = (string) $request->input('confirm_password', '');
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

        $exists = $this->identity->userConflict($pending['username'], $pending['email'], $pending['phone']);
        if ($exists) {
            $request->session()->forget(['reg_pending','reg_step']);
            return redirect()->route('register')->with('error', $this->identity->conflictMessage($exists, $pending['username'], $pending['email'], $pending['phone']));
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
        $request->session()->regenerate();
        $request->session()->forget('rider');
        $request->session()->put('user', [
            'id' => $newId,
            'fullname' => $pending['fullname'],
            'email' => $pending['email'],
            'phone' => $pending['phone'],
            'username' => $pending['username'],
            'role' => 'customer',
            'profile_photo' => null,
        ]);
        if ($response = app(\App\Services\PurchaseIntentService::class)->resume($request)) return $response;
        $intended = $request->session()->pull('customer_intended_url');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) return redirect()->to(url($intended));
        return redirect()->route('customer.catalog')->with('msg', 'Your account is ready. You are now signed in.');
    }

    public function back(Request $request)
    {
        $request->session()->forget(['reg_pending','reg_step']);
        return redirect()->route('register');
    }

    public function sendOtp(Request $request)
    {
        return $this->store($request);
    }

    public function verify(Request $request)
    {
        return $this->verifyOtp($request);
    }
}
