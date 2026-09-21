<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'processing_mode')) {
                $table->string('processing_mode', 20)->nullable()->after('checkout_group_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'processing_mode')) return;

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('processing_mode');
        });
    }
};
