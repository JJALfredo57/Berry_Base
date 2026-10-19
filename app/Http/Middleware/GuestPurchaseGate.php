<?php

namespace App\Http\Middleware;

use App\Services\PurchaseIntentService;
use Closure;
use Illuminate\Http\Request;

class GuestPurchaseGate
{
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route()->getName();
        $role = $request->session()->get('user.role');
        if (($role && $role !== 'customer') || $request->session()->has('rider')) {
            return redirect()->route('login')->with('error', 'Please sign out and use a customer account to order.');
        }

        $actions = ['catalog.select' => 'buy', 'cart.add' => 'cart', 'guest.custom_order' => 'custom', 'order_requests.store' => 'order_request'];
        $service = app(PurchaseIntentService::class);
        if (isset($actions[$route])) {
            if ($role === 'customer' && $route === 'order_requests.store') {
                return $next($request);
            }
            $service->remember($request, $actions[$route]);
            if ($role === 'customer') {
                return $service->resume($request);
            }
        }

        if ($role === 'customer') {
            if ($response = $service->resume($request)) {
                return $response;
            }

            return redirect()->route(match ($route) {
                'guest.checkout' => 'customer.checkout',
                'guest.feedback', 'guest.feedback.store' => 'customer.feedback',
                default => 'customer.cart',
            });
        }

        if (! isset($actions[$route])) {
            $request->session()->put('customer_intended_url', route(
                str_starts_with($route, 'guest.feedback') ? 'customer.feedback' : 'customer.cart', [], false
            ));
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => 'Please log in or create a customer account to continue.', 'redirect_url' => route('login')], 401);
        }

        return redirect()->route('login')->with('msg', 'Log in or create an account to continue. Your cake selection will be kept during sign-in.');
    }
}
