<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class AuthSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = session('user');
        if (!$user) return redirect()->route('login');
        // Only superadmin role — regular admin cannot access superadmin routes
        if ($user['role'] !== 'superadmin') {
            if ($user['role'] === 'admin') return redirect()->route('admin.dashboard');
            return redirect()->route('platform.home');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
