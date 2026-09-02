<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ForgotPasswordController extends Controller
{
    public function show(Request $request)
    {
        $step = $request->session()->get('fp_step', 1);
        return view('auth.forgot_password', compact('step'));
    }

    public function sendOtp(Request $request)
    {
        $accountType = $request->input('account_type', 'user');
        if ($accountType === 'rider') {
            return $this->sendRiderOtp($request);
        }

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
        $request->session()->put('fp_account_type', 'user');
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
                $msg = "⚠️ SMS failed to send. Check your PhilSMS API token, Sender ID setup, endpoint, or account credits. (No email fallback during testing)";
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
        if ($request->session()->get('fp_account_type') === 'rider') {
            return $this->verifyRiderOtp($request, $otpIn);
        }

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
        if ($request->session()->get('fp_account_type') === 'rider') {
            return $this->resetRiderPassword($request);
        }

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

        $request->session()->forget(['fp_email','fp_channel','fp_account_type','fp_step']);
        return redirect()->route('login')->with('msg', 'Password reset successful! You can now login.');
    }

    public function back(Request $request)
    {
        $request->session()->forget(['fp_email','fp_phone','fp_rider_id','fp_channel','fp_account_type','fp_step']);
        return redirect()->route('forgot.show');
    }

    private function sendRiderOtp(Request $request)
    {
        if (!Schema::hasTable('riders') || !Schema::hasColumn('riders', 'password_reset_otp')) {
            return back()->with('error', 'Rider password reset is not ready yet. Please run the latest database migration.');
        }

        $phone = trim((string) $request->input('phone', ''));
        if ($phone === '') return back()->with('error', 'Please enter your rider phone number.')->withInput();

        $rider = $this->riderForPhone($phone);
        if (!$rider) return back()->with('error', 'No rider account found with that phone number.')->withInput();
        if (!(bool) $rider->is_active) return back()->with('error', 'Rider account is inactive. Contact the seller or admin.')->withInput();

        $otp = (string) random_int(100000, 999999);
        DB::table('riders')->where('id', $rider->id)->update([
            'password_reset_otp' => $otp,
            'password_reset_expires_at' => now()->addMinutes(10),
            'updated_at' => now(),
        ]);

        $request->session()->put('fp_account_type', 'rider');
        $request->session()->put('fp_rider_id', $rider->id);
        $request->session()->put('fp_phone', $rider->phone);
        $request->session()->put('fp_channel', 'sms');
        $request->session()->put('fp_step', 2);

        $sent = SmsHelper::sendOtp($rider->phone, $otp, config('app.name', 'Cake Shop'));
        $msg = $sent
            ? 'OTP sent via SMS to ' . $this->maskPhone((string) $rider->phone) . '. Valid for 10 minutes.'
            : 'SMS failed to send. Check PhilSMS settings or ask the seller/admin to reset your rider PIN.';

        return redirect()->route('forgot.show')->with('msg', $msg);
    }

    private function verifyRiderOtp(Request $request, string $otpIn)
    {
        $riderId = $request->session()->get('fp_rider_id');
        if (!$riderId) {
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'Session expired. Please try again.');
        }

        $rider = DB::table('riders')->where('id', $riderId)->first();
        if (!$rider || empty($rider->password_reset_otp)) {
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'No OTP found. Please request a new one.');
        }
        if (now()->gt(\Carbon\Carbon::parse($rider->password_reset_expires_at))) {
            DB::table('riders')->where('id', $rider->id)->update(['password_reset_otp' => null, 'password_reset_expires_at' => null]);
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'OTP has expired. Please request a new one.');
        }
        if ($otpIn !== $rider->password_reset_otp) {
            return redirect()->route('forgot.show')->with('error', 'Incorrect OTP. Please check and try again.');
        }

        $request->session()->put('fp_step', 3);
        return redirect()->route('forgot.show')->with('msg', 'OTP verified! Please set your new rider password.');
    }

    private function resetRiderPassword(Request $request)
    {
        $riderId = $request->session()->get('fp_rider_id');
        $new = (string) $request->input('password', '');
        $confirm = (string) $request->input('confirm_password', '');
        $error = $this->passwordValidationError($new, $confirm);
        if ($error) return back()->with('error', $error);

        if (!$riderId) {
            $request->session()->put('fp_step', 1);
            return redirect()->route('forgot.show')->with('error', 'Session expired. Please start again.');
        }

        $updates = [
            'login_pin_hash' => Hash::make($new),
            'login_pin_set_at' => now(),
            'password_must_change' => false,
            'password_changed_at' => now(),
            'password_reset_otp' => null,
            'password_reset_expires_at' => null,
            'updated_at' => now(),
        ];
        $updates = collect($updates)
            ->filter(fn ($value, $column) => Schema::hasColumn('riders', $column))
            ->all();

        DB::table('riders')->where('id', $riderId)->update($updates);
        CakeshopHelper::logActivity($riderId, 'rider', 'Reset Password', 'Rider password reset via SMS OTP');

        $request->session()->forget(['fp_email','fp_phone','fp_rider_id','fp_channel','fp_account_type','fp_step']);
        return redirect()->route('login')->with('msg', 'Rider password reset successful. You can now login with your phone and new password.');
    }

    private function riderForPhone(string $phone): ?object
    {
        $formats = $this->phoneFormats($phone);
        if (!$formats) return null;

        return DB::table('riders')
            ->where(function ($q) use ($formats) {
                foreach ($formats as $format) {
                    $q->orWhere('phone', $format);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function phoneFormats(string $phone): array
    {
        $clean = preg_replace('/\D/', '', $phone);
        if ($clean === '') return [];
        if (str_starts_with($clean, '0')) $clean = '63' . substr($clean, 1);
        if (!str_starts_with($clean, '63')) $clean = '63' . $clean;

        return array_values(array_unique(array_filter([
            $phone,
            '+' . $clean,
            $clean,
            strlen($clean) > 2 ? '0' . substr($clean, 2) : null,
        ])));
    }

    private function passwordValidationError(string $password, string $confirm): ?string
    {
        if (strlen($password) < 8) return 'Password must be at least 8 characters.';
        if ($password !== $confirm) return 'Passwords do not match.';
        if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least 1 uppercase letter.';
        if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least 1 number.';
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?`~]/', $password)) return 'Password must contain at least 1 special character.';

        return null;
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

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 6) return '***';

        return substr($phone, 0, 4) . str_repeat('*', max(3, $len - 7)) . substr($phone, -3);
    }
}
