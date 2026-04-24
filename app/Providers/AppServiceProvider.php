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
            $tz = DB::table('site_settings')->value('timezone');
            if ($tz && in_array($tz, timezone_identifiers_list())) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
                Carbon::setTestNow(null); // reset any test now
            }
        } catch (\Exception $e) {
            // Fallback to Asia/Manila if DB not available
            config(['app.timezone' => 'Asia/Manila']);
            date_default_timezone_set('Asia/Manila');
        }
    }
}
