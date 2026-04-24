<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_settings') && !Schema::hasColumn('platform_settings', 'platform_primary_color')) {
            Schema::table('platform_settings', function (Blueprint $t) {
                $t->string('platform_primary_color', 7)->default('#7B3A0F')->after('platform_phone');
            });
            // Set the chocolate-brown default matching the logo
            DB::table('platform_settings')->update(['platform_primary_color' => '#7B3A0F']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('platform_settings') && Schema::hasColumn('platform_settings', 'platform_primary_color')) {
            Schema::table('platform_settings', fn(Blueprint $t) => $t->dropColumn('platform_primary_color'));
        }
    }
};
