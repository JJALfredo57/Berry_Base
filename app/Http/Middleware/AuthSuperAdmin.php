<?php
namespace App\Http\Middleware;
use Closure;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;

class AuthSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = session('user');
        if (!$user) {
            CakeshopHelper::logActivity('unknown', 'security', 'Denied Access', 'Unauthenticated request to a Super Admin route', 'route', $request->route()?->getName(), 'denied');
            return redirect()->route('login');
        }
        // Only superadmin role — regular admin cannot access superadmin routes
        if ($user['role'] !== 'superadmin') {
            CakeshopHelper::logActivity($user['id'] ?? 'unknown', $user['role'] ?? 'unknown', 'Denied Access', 'Non-Super Admin attempted to open a Super Admin route', 'route', $request->route()?->getName(), 'denied');
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
