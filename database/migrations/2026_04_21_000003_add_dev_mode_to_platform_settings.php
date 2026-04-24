<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_settings', 'dev_mode')) {
                $table->boolean('dev_mode')->default(false)->after('platform_primary_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('dev_mode');
        });
    }
};
