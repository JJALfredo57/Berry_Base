<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_feedback')) return;

        Schema::table('customer_feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_feedback', 'source_role')) {
                $table->string('source_role', 30)->default('customer')->after('user_id')->index();
            }
            if (!Schema::hasColumn('customer_feedback', 'shop_id')) {
                $table->string('shop_id', 12)->nullable()->after('source_role')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_feedback')) return;

        Schema::table('customer_feedback', function (Blueprint $table) {
            if (Schema::hasColumn('customer_feedback', 'shop_id')) $table->dropColumn('shop_id');
            if (Schema::hasColumn('customer_feedback', 'source_role')) $table->dropColumn('source_role');
        });
    }
};
