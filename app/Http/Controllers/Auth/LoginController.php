<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    // ── SUPER ADMIN SECRET PORTAL ─────────────────────────────────
    public function showSuperAdmin()
    {
        if (session('user') && in_array(session('user')['role'], ['admin','superadmin'])) {
            return redirect()->route('superadmin.dashboard');
        }
        return view('auth.login_superadmin');
    }

    public function loginSuperAdmin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ],[
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ]);

        $user = DB::table('users')
            ->where('username', trim($request->username))
            ->whereIn('role', ['admin','superadmin'])
            ->first();

        if (!$user || !password_verify($request->password, $user->password)) {
            return back()->with('error','Invalid credentials.')->withInput(['username' => $request->username]);
        }
        if (!(int)$user->is_verified) {
            return back()->with('error','Account not verified.')->withInput(['username' => $request->username]);
        }

        $request->session()->regenerate();
        $request->session()->put('user', [
            'id'            => $user->id,
            'fullname'      => $user->fullname,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'username'      => $user->username,
            'role'          => $user->role,
            'profile_photo' => $user->profile_photo ?? null,
        ]);

        CakeshopHelper::logActivity($user->id, $user->role, 'Login', 'Logged in via Admin Portal');

        return $user->role === 'superadmin'
            ? redirect()->route('superadmin.dashboard')
            : redirect()->route('admin.dashboard');
    }

    // ── SELLER LOGIN (linked from homepage) ───────────────────────
    public function show()
    {
        // Only redirect if current session is a SELLER
        // Superadmin/admin visiting /login should still see seller login page
        if (session('user') && session('user')['role'] === 'seller') {
            return redirect()->route('seller.dashboard');
        }
        // Clear any admin session so they don't accidentally use seller login
        if (session('user') && in_array(session('user')['role'], ['admin','superadmin'])) {
            // Logout admin session silently, show seller login clean
            session()->forget('user');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ],[
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ]);

        $user = DB::table('users')
            ->where('username', trim($request->username))
            ->where('role', 'seller')
            ->first();

        if (!$user) {
            // Check if admin trying to use seller login
            $isAdmin = DB::table('users')
                ->where('username', trim($request->username))
                ->whereIn('role', ['admin','superadmin'])
                ->exists();
            if ($isAdmin) {
                return back()->with('error','Admin accounts use a different login portal.')
                             ->withInput(['username' => $request->username]);
            }
            return back()->with('error','Invalid username or password.')->withInput(['username' => $request->username]);
        }

        if (!password_verify($request->password, $user->password)) {
            return back()->with('error','Invalid username or password.')->withInput(['username' => $request->username]);
        }
        if (!(int)$user->is_verified) {
            return back()->with('error','Your account is not yet verified. Please wait for approval.')->withInput(['username' => $request->username]);
        }

        $request->session()->regenerate();
        $request->session()->put('user', [
            'id'            => $user->id,
            'fullname'      => $user->fullname,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'username'      => $user->username,
            'role'          => $user->role,
            'profile_photo' => $user->profile_photo ?? null,
        ]);

        CakeshopHelper::logActivity($user->id, $user->role, 'Login', 'Seller logged in');
        return redirect()->route('seller.dashboard');
    }

    // ── LOGOUT ────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $user = session('user');
        if ($user) {
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Logout', 'Logged out');
        }
        $role = $user['role'] ?? '';
        $request->session()->flush();
        $request->session()->regenerate();

        if (in_array($role, ['admin','superadmin'])) {
            return redirect()->route('superadmin.login');
        }
        return redirect()->route('login');
    }
}
