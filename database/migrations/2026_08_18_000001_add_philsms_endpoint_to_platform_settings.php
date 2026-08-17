<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_settings') && !Schema::hasColumn('platform_settings', 'philsms_endpoint')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->string('philsms_endpoint')->nullable()->after('philsms_sender');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('platform_settings') && Schema::hasColumn('platform_settings', 'philsms_endpoint')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->dropColumn('philsms_endpoint');
            });
        }
    }
};
