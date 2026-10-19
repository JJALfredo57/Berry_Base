<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderAccountAccess
{
    public function handle(Request $request, Closure $next)
    {
        $trackCode = $request->route('trackCode');
        $order = $trackCode ? DB::table('orders')->where('track_code', strtoupper($trackCode))->first() : null;
        if (! $trackCode && $id = $request->route('id')) {
            $custom = DB::table('custom_orders')->where('id', $id)->first();
            $order = $custom ? DB::table('orders')->where('id', $custom->order_id)->first() : null;
        }
        if (! $order) {
            abort(404);
        }

        // Historical guest orders keep their existing secret-link access.
        if (empty($order->user_id)) {
            return $next($request);
        }
        $user = $request->session()->get('user');
        $isPaymentReturn = in_array($request->route()->getName(), ['guest.payment_return', 'guest.deposit_return', 'guest.remaining_return'], true);
        if (! $user) {
            // Verify gateway status before requiring sign-in, but never expose the receipt.
            if ($isPaymentReturn) {
                $next($request);
            }
            if ($request->isMethod('GET')) {
                $request->session()->put('customer_intended_url', '/'.$request->path());
            }
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'Please sign in to view your order.', 'redirect_url' => route('login')], 401);
            }

            return redirect()->route('login')->with('msg', 'Please sign in to view your order.');
        }
        if (($user['role'] ?? '') !== 'customer' || (string) $user['id'] !== (string) $order->user_id) {
            if ($isPaymentReturn) {
                $next($request);
            }
            abort(403);
        }

        return $next($request);
    }
}
