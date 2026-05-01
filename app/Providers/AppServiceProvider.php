<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
    }
}
