<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForgotPasswordController extends Controller
{
    public function show(Request $request)
    {
        $step = $request->session()->get('fp_step', 1);
        return view('auth.forgot_password', compact('step'));
    }

    public function sendOtp(Request $request)
    {
        $email      = strtolower(trim($request->input('email', '')));
        $otpChannel = $request->input('otp_channel', 'email');

        if (!$email) return back()->with('error', 'Please enter your email.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return back()->with('error', 'Please enter a valid email address.')->withInput();

        $user = DB::table('users')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->select('id', 'role', 'email', 'phone', 'fullname')
            ->first();

        if (!$user) return back()->with('error', 'No account found with that email address.')->withInput();

        $otp     = (string) random_int(100000, 999999);
        $expires = now()->addMinutes(10)->format('Y-m-d H:i:s');

        DB::table('password_resets')->where('user_id', $user->id)->delete();
        DB::table('password_resets')->insert([
            'user_id'    => $user->id,
            'otp_code'   => $otp,
            'expires_at' => $expires,
            'created_at' => now(),
        ]);

        $request->session()->put('fp_email',   $user->email);
        $request->session()->put('fp_channel', $otpChannel);
        $request->session()->put('fp_step',    2);

        $sent = false;
        $msg  = '';

        if ($otpChannel === 'sms' && $user->phone) {
            $sent = SmsHelper::sendOtp($user->phone, $otp, config('app.name', 'Cake Shop'));
            $maskedPhone = substr($user->phone, 0, 4) . str_repeat('*', max(4, strlen($user->phone) - 7)) . substr($user->phone, -3);
            if ($sent) {
                $msg = "OTP sent via SMS to {$maskedPhone}. Check your messages. Valid for 10 minutes.";
            } else {
                // No fallback — show exact error so we can debug
                $msg = "⚠️ SMS failed to send. Check your UniSMS API key, Sender ID setup, or account credits. (No email fallback during testing)";
            }
        } else {
            $sent = CakeshopHelper::sendOtpEmail($user->email, $otp, 'Password Reset');
            $msg  = $sent
                ? "OTP sent to " . $this->maskEmail($user->email) . ". Check inbox and spam folder. Valid for 10 minutes."
                : "Email not configured. Please ask administrator to set up Gmail SMTP.";
        }

        return redirect()->route('forgot.show')->with('msg', $msg);
    }

    public function verifyOtp(Request $request)
    {
        $otpIn = trim($request->input('otp', ''));
        $email = $request->session()->get('fp_email', '');

        if (!$email) {
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'Session expired. Please try again.');
        }

        $user = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($email)])->select('id')->first();
        if (!$user) {
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'Account not found.');
        }

        $reset = DB::table('password_resets')->where('user_id', $user->id)->orderByDesc('id')->first();
        if (!$reset) {
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'No OTP found. Please request a new one.');
        }
        if (now()->gt(\Carbon\Carbon::parse($reset->expires_at))) {
            $request->session()->put('fp_step', 1);
            DB::table('password_resets')->where('user_id', $user->id)->delete();
            return redirect()->route('forgot.show')->with('error', 'OTP has expired. Please request a new one.');
        }
        if ($otpIn !== $reset->otp_code) {
            return redirect()->route('forgot.show')->with('error', 'Incorrect OTP. Please check and try again.');
        }

        $request->session()->put('fp_step', 3);
        return redirect()->route('forgot.show')->with('msg', 'OTP verified! Please set your new password.');
    }

    public function reset(Request $request)
    {
        $email   = $request->session()->get('fp_email', '');
        $new     = $request->input('password', '');
        $confirm = $request->input('confirm_password', '');

        if (!$email) {
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'Session expired. Please start again.');
        }
        if (strlen($new) < 8)    return back()->with('error', 'Password must be at least 8 characters.');
        if ($new !== $confirm)   return back()->with('error', 'Passwords do not match.');
        if (!preg_match('/[A-Z]/', $new)) return back()->with('error', 'Password must contain at least 1 uppercase letter.');
        if (!preg_match('/[0-9]/', $new)) return back()->with('error', 'Password must contain at least 1 number.');
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $new)) return back()->with('error', 'Password must contain at least 1 special character.');

        DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($email)])->update(['password' => password_hash($new, PASSWORD_DEFAULT)]);
        $user = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($email)])->select('id','role')->first();

        if ($user) {
            DB::table('password_resets')->where('user_id', $user->id)->delete();
            CakeshopHelper::logActivity($user->id, $user->role, 'Reset Password', 'Password reset via OTP');
        }

        $request->session()->forget(['fp_email','fp_step']);
        return redirect()->route('login')->with('msg', 'Password reset successful! You can now login.');
    }

    public function back(Request $request)
    {
        $request->session()->forget(['fp_email','fp_channel','fp_step']);
        return redirect()->route('forgot.show');
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***@***';
        $local  = $parts[0];
        $domain = $parts[1];
        $len    = strlen($local);
        if ($len <= 2) return '**@' . $domain;
        return substr($local, 0, 2) . str_repeat('*', max(2, $len - 2)) . '@' . $domain;
    }
}
