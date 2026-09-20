<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('site_settings', 'ready_made_prep_days')) {
                $table->unsignedTinyInteger('ready_made_prep_days')->default(0)->after('lead_3day_plus_max');
            }
            if (!Schema::hasColumn('site_settings', 'custom_cake_prep_days')) {
                $table->unsignedTinyInteger('custom_cake_prep_days')->default(3)->after('ready_made_prep_days');
            }
            if (!Schema::hasColumn('site_settings', 'custom_cart_hold_minutes')) {
                $table->unsignedSmallInteger('custom_cart_hold_minutes')->default(15)->after('custom_cake_prep_days');
            }
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('custom_orders', 'review_deadline_at')) {
                $table->timestamp('review_deadline_at')->nullable()->after('review_status');
            }
            if (!Schema::hasColumn('custom_orders', 'review_deadline_status')) {
                $table->string('review_deadline_status', 30)->nullable()->after('review_deadline_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            if (Schema::hasColumn('custom_orders', 'review_deadline_status')) {
                $table->dropColumn('review_deadline_status');
            }
            if (Schema::hasColumn('custom_orders', 'review_deadline_at')) {
                $table->dropColumn('review_deadline_at');
            }
        });

        Schema::table('site_settings', function (Blueprint $table) {
            foreach (['custom_cart_hold_minutes', 'custom_cake_prep_days', 'ready_made_prep_days'] as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
