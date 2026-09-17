<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Auto-create public/storage symlink on first request after deploy
        // (replaces the need to run `php artisan storage:link` manually)
        if (!is_link(public_path('storage')) && !file_exists(public_path('storage'))) {
            try {
                symlink(storage_path('app/public'), public_path('storage'));
            } catch (\Throwable $e) {}
        }

        // Apply timezone from shop settings globally
        // This affects Carbon::now(), now(), and all Laravel date functions
        try {
            // Short socket timeout prevents hanging during build phase (package:discover)
            // when the DB host is unreachable from the build container
            $prev = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', 5);
            $tz = DB::table('site_settings')->value('timezone');
            ini_set('default_socket_timeout', $prev);
            if ($tz && in_array($tz, timezone_identifiers_list())) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
                Carbon::setTestNow(null);
            }
        } catch (\Exception $e) {
            config(['app.timezone' => 'Asia/Manila']);
            date_default_timezone_set('Asia/Manila');
        }

        View::composer('layouts.app', function ($view) {
            $count = 0;
            try {
                if (!Schema::hasTable('customer_carts') || !Schema::hasTable('customer_cart_items')) {
                    $view->with('topbarCartCount', 0);
                    return;
                }

                $user = session('user');
                $role = $user['role'] ?? null;

                if ($role && $role !== 'customer') {
                    $view->with('topbarCartCount', 0);
                    return;
                }

                $cartQuery = DB::table('customer_carts')->where('status', 'active');
                if ($role === 'customer') {
                    $cartQuery->where('user_id', $user['id'] ?? '');
                } else {
                    $cartQuery->where('session_id', request()->session()->getId())->whereNull('user_id');
                }

                $cart = $cartQuery->orderByDesc('id')->first();
                if ($cart) {
                    $count = (int) DB::table('customer_cart_items')->where('cart_id', $cart->id)->sum('quantity');
                }
            } catch (\Throwable $e) {
                $count = 0;
            }

            $view->with('topbarCartCount', $count);
        });
    }
}
