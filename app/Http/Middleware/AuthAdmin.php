<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class AuthAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = session('user');

        if (!$user) {
            return redirect()->route('login');
        }

        if (!in_array($user['role'] ?? null, ['admin', 'superadmin'])) {
            if (($user['role'] ?? null) === 'customer') {
                return redirect()->route('customer.catalog');
            }

            if (($user['role'] ?? null) === 'seller') {
                return redirect()->route('seller.dashboard');
            }

            return redirect()->route('platform.home');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
