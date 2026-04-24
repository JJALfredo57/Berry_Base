<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
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

    public function show()
    {
        $uid  = session('user')['id'];
        $user = DB::table('users')->where('id', $uid)->first();
        $orderCount   = DB::table('orders')->where('user_id', $uid)->count();
        $pendingCount = DB::table('orders')->where('user_id', $uid)->where('status','Pending')->count();
        return view('customer.profile', compact('user','orderCount','pendingCount'));
    }

    public function update(Request $request)
    {
        $uid      = session('user')['id'];
        $fullname = trim($request->input('fullname', ''));
        $email    = trim($request->input('email', ''));
        $phone    = trim($request->input('phone', ''));

        if (!$fullname || !$email || !$phone) return back()->with('error', 'Please complete all fields.');

        $exists = DB::table('users')
            ->where(fn($q) => $q->where('email', $email)->orWhere('phone', $phone))
            ->where('id', '<>', $uid)
            ->first();
        if ($exists) return back()->with('error', 'Email or phone already in use by another account.');

        $data = compact('fullname','email','phone');

        if ($request->hasFile('profile_photo')) {
            $up = $this->saveProfilePhoto($request->file('profile_photo'));
            if ($up) $data['profile_photo'] = $up;
        }

        DB::table('users')->where('id', $uid)->update($data);

        $s = session('user');
        $s['fullname'] = $fullname;
        $s['email']    = $email;
        $s['phone']    = $phone;
        if (isset($data['profile_photo'])) $s['profile_photo'] = $data['profile_photo'];
        session(['user' => $s]);

        CakeshopHelper::logActivity($uid, 'customer', 'Update Profile', 'Profile info updated');
        return back()->with('msg', 'Profile updated successfully.');
    }

    // ── Change Password — Step 1: Show OTP channel picker ────────
    public function changePasswordShow(Request $request)
    {
        $step = $request->session()->get('cp_step', 1);
        $uid  = session('user')['id'];
        $user = DB::table('users')->where('id', $uid)->first();
        $orderCount   = DB::table('orders')->where('user_id', $uid)->count();
        $pendingCount = DB::table('orders')->where('user_id', $uid)->where('status','Pending')->count();
        return view('customer.profile', compact('user','orderCount','pendingCount','step'));
    }

    // ── Change Password — Back to Step 1 ────────────────────────
    public function changePasswordBack(Request $request)
    {
        $request->session()->forget(['cp_otp','cp_expires','cp_sent_at','cp_channel','cp_step']);
        return redirect()->route('customer.profile');
    }

    // ── Change Password — Step 1 POST: Send OTP ──────────────────
    public function changePasswordSendOtp(Request $request)
    {
        $uid        = session('user')['id'];
        $user       = DB::table('users')->where('id', $uid)->first();
        $otpChannel = $request->input('otp_channel', 'email');

        $otp     = (string) random_int(100000, 999999);
        $expires = time() + (10 * 60);
        $sentAt  = time();

        $request->session()->put('cp_otp',     $otp);
        $request->session()->put('cp_expires', $expires);
        $request->session()->put('cp_sent_at', $sentAt);
        $request->session()->put('cp_channel', $otpChannel);
        $request->session()->put('cp_step',    2);

        $sent = false;
        $msg  = '';

        if ($otpChannel === 'sms') {
            $sent = SmsHelper::sendOtp($user->phone, $otp, config('app.name', 'Cake Shop'));
            $maskedPhone = substr($user->phone, 0, 4) . str_repeat('*', max(4, strlen($user->phone) - 7)) . substr($user->phone, -3);
            $msg = $sent
                ? "OTP sent via SMS to {$maskedPhone}. Valid for 10 minutes."
                : "⚠️ SMS failed to send. Please use Email OTP instead.";
        } else {
            $sent = CakeshopHelper::sendOtpEmail($user->email, $otp, 'Change Password');
            $masked = substr($user->email, 0, 2) . str_repeat('*', max(2, strpos($user->email,'@') - 2)) . substr($user->email, strpos($user->email,'@'));
            $msg = $sent
                ? "OTP sent to {$masked}. Check your inbox and spam folder. Valid for 10 minutes."
                : "Email not configured. Please ask the administrator to set up Gmail SMTP.";
        }

        return redirect()->route('profile.password.show')->with('msg', $msg);
    }

    // ── Change Password — Step 2 POST: Verify OTP ────────────────
    public function changePasswordVerifyOtp(Request $request)
    {
        $otpIn   = trim($request->input('otp', ''));
        $session = $request->session();

        if (!$session->get('cp_otp')) {
            $session->forget(['cp_otp','cp_expires','cp_sent_at','cp_channel','cp_step']);
            return redirect()->route('profile.password.show')->with('error', 'Session expired. Please try again.');
        }
        if (time() > (int)$session->get('cp_expires')) {
            $session->forget(['cp_otp','cp_expires','cp_sent_at','cp_channel','cp_step']);
            return redirect()->route('profile.password.show')->with('error', 'OTP expired. Please request a new one.');
        }
        if ($otpIn !== (string)$session->get('cp_otp')) {
            return redirect()->route('profile.password.show')->with('error', 'Incorrect OTP. Please try again.');
        }

        $session->put('cp_step', 3);
        $session->forget(['cp_otp','cp_expires','cp_sent_at','cp_channel']);
        return redirect()->route('profile.password.show')->with('msg', 'OTP verified! Please set your new password.');
    }

    // ── Change Password — Step 3 POST: Save new password ─────────
    public function changePassword(Request $request)
    {
        $uid     = session('user')['id'];
        $new     = $request->input('new_password', '');
        $confirm = $request->input('confirm_password', '');

        // Must have completed OTP step
        if ($request->session()->get('cp_step') !== 3) {
            return redirect()->route('profile.password.show')->with('error', 'Please verify OTP first.');
        }

        if (strlen($new) < 8) return back()->with('error', 'Password must be at least 8 characters.');
        if ($new !== $confirm) return back()->with('error', 'Passwords do not match.');
        if (!preg_match('/[A-Z]/', $new)) return back()->with('error', 'Password must contain at least 1 uppercase letter.');
        if (!preg_match('/[0-9]/', $new)) return back()->with('error', 'Password must contain at least 1 number.');
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $new)) return back()->with('error', 'Password must contain at least 1 special character.');

        DB::table('users')->where('id', $uid)->update(['password' => password_hash($new, PASSWORD_DEFAULT)]);
        CakeshopHelper::logActivity($uid, 'customer', 'Change Password', 'Customer changed password via OTP');

        $request->session()->forget('cp_step');
        session()->flush();
        return redirect()->route('login')->with('msg', 'Password changed successfully. Please login again.');
    }
}
