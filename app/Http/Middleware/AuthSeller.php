<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class AuthSeller
{
    public function handle(Request $request, Closure $next)
    {
        $user = session('user');
        if (!$user) return redirect()->route('login');
        if ($user['role'] !== 'seller') {
            if ($user['role'] === 'admin' || $user['role'] === 'superadmin')
                return redirect()->route('admin.dashboard');
            return redirect()->route('platform.home');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control','no-store,no-cache,must-revalidate,max-age=0');
        return $response;
    }
}
