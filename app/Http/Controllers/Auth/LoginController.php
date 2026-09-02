<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\CakeshopHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    public function showSuperAdmin()
    {
        if (session('user') && in_array(session('user')['role'], ['admin', 'superadmin'], true)) {
            return redirect()->route('superadmin.dashboard');
        }

        return view('auth.login_superadmin');
    }

    public function loginSuperAdmin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ]);

        $user = DB::table('users')
            ->where('username', trim($request->username))
            ->whereIn('role', ['admin', 'superadmin'])
            ->first();

        if (!$user || !password_verify($request->password, $user->password)) {
            return back()->with('error', 'Invalid credentials.')->withInput(['username' => $request->username]);
        }

        if (!(int) $user->is_verified) {
            return back()->with('error', 'Account not verified.')->withInput(['username' => $request->username]);
        }

        $request->session()->regenerate();
        $request->session()->forget('rider');
        $request->session()->put('user', [
            'id' => $user->id,
            'fullname' => $user->fullname,
            'email' => $user->email,
            'phone' => $user->phone,
            'username' => $user->username,
            'role' => $user->role,
            'profile_photo' => $user->profile_photo ?? null,
        ]);

        CakeshopHelper::logActivity($user->id, $user->role, 'Login', 'Logged in via Admin Portal');

        return $user->role === 'superadmin'
            ? redirect()->route('superadmin.dashboard')
            : redirect()->route('admin.dashboard');
    }

    public function show()
    {
        if (session('rider')) {
            return redirect()->route('rider.dashboard');
        }

        if (session('user')) {
            return match (session('user')['role'] ?? '') {
                'seller' => redirect()->route('seller.dashboard'),
                'admin' => redirect()->route('admin.dashboard'),
                'superadmin' => redirect()->route('superadmin.dashboard'),
                'customer' => redirect()->route('customer.orders'),
                default => view('auth.login'),
            };
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username, email, or rider phone is required.',
            'password.required' => 'Password or rider PIN is required.',
        ]);

        $identifier = trim($request->username);
        $password = (string) $request->password;

        $user = DB::table('users')
            ->where(function ($q) use ($identifier) {
                $q->where('username', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();

        if ($user && password_verify($password, $user->password)) {
            if (!(int) $user->is_verified) {
                return back()->with('error', 'Your account is not yet verified. Please wait for approval.')->withInput(['username' => $request->username]);
            }

            $request->session()->regenerate();
            $request->session()->forget('rider');
            $request->session()->put('user', [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'email' => $user->email,
                'phone' => $user->phone,
                'username' => $user->username,
                'role' => $user->role,
                'profile_photo' => $user->profile_photo ?? null,
            ]);

            CakeshopHelper::logActivity($user->id, $user->role, 'Login', ucfirst($user->role) . ' logged in');

            return match ($user->role) {
                'seller' => redirect()->route('seller.dashboard'),
                'admin' => redirect()->route('admin.dashboard'),
                'superadmin' => redirect()->route('superadmin.dashboard'),
                'customer' => redirect()->route('customer.orders'),
                default => redirect()->route('catalog'),
            };
        }

        $rider = $this->riderForLogin($identifier);
        if ($rider) {
            if (!(bool) $rider->is_active) {
                return back()->with('error', 'Rider account is inactive. Contact the seller or admin.')->withInput(['username' => $request->username]);
            }

            if (!Schema::hasColumn('riders', 'login_pin_hash') || empty($rider->login_pin_hash) || !Hash::check($password, $rider->login_pin_hash)) {
                return back()->with('error', 'Invalid login details.')->withInput(['username' => $request->username]);
            }

            $request->session()->regenerate();
            $request->session()->forget('user');
            $request->session()->put('rider', [
                'id' => (int) $rider->id,
                'name' => $rider->name,
                'phone' => $rider->phone,
                'shop_id' => $rider->shop_id,
            ]);

            CakeshopHelper::logActivity($rider->id, 'rider', 'Login', 'Rider logged in');

            if (Schema::hasColumn('riders', 'password_must_change') && (bool) ($rider->password_must_change ?? false)) {
                return redirect()->route('rider.password.setup');
            }

            return redirect()->route('rider.dashboard');
        }

        return back()->with('error', 'Invalid login details.')->withInput(['username' => $request->username]);
    }

    public function logout(Request $request)
    {
        $user = session('user');
        if ($user) {
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Logout', 'Logged out');
        }

        if (session('rider')) {
            CakeshopHelper::logActivity(session('rider')['id'], 'rider', 'Logout', 'Logged out');
        }

        $role = $user['role'] ?? '';
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirect = in_array($role, ['admin', 'superadmin'], true)
            ? redirect()->route('superadmin.login')
            : redirect()->route('login');

        return $redirect
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
                'Clear-Site-Data' => '"cache"',
            ]);
    }

    private function riderForLogin(string $identifier): ?object
    {
        if (!Schema::hasTable('riders')) {
            return null;
        }

        $formats = $this->phoneFormats($identifier);
        if (!$formats) {
            return null;
        }

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
        if ($clean === '') {
            return [];
        }

        if (str_starts_with($clean, '0')) {
            $clean = '63' . substr($clean, 1);
        }

        if (!str_starts_with($clean, '63')) {
            $clean = '63' . $clean;
        }

        return array_values(array_unique(array_filter([
            $phone,
            '+' . $clean,
            $clean,
            strlen($clean) > 2 ? '0' . substr($clean, 2) : null,
        ])));
    }
}
