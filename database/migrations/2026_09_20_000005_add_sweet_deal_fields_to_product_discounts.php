<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('product_discounts')) {
            return;
        }

        Schema::table('product_discounts', function (Blueprint $table) {
            if (!Schema::hasColumn('product_discounts', 'deal_badge_label')) {
                $table->string('deal_badge_label', 50)->nullable()->after('label');
            }
            if (!Schema::hasColumn('product_discounts', 'deal_note')) {
                $table->string('deal_note', 120)->nullable()->after('deal_badge_label');
            }
            if (!Schema::hasColumn('product_discounts', 'best_enjoyed_by')) {
                $table->timestamp('best_enjoyed_by')->nullable()->after('deal_note');
            }
            if (!Schema::hasColumn('product_discounts', 'deal_quantity_limit')) {
                $table->unsignedInteger('deal_quantity_limit')->nullable()->after('best_enjoyed_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_discounts')) {
            return;
        }

        Schema::table('product_discounts', function (Blueprint $table) {
            if (Schema::hasColumn('product_discounts', 'deal_quantity_limit')) {
                $table->dropColumn('deal_quantity_limit');
            }
            if (Schema::hasColumn('product_discounts', 'best_enjoyed_by')) {
                $table->dropColumn('best_enjoyed_by');
            }
            if (Schema::hasColumn('product_discounts', 'deal_note')) {
                $table->dropColumn('deal_note');
            }
            if (Schema::hasColumn('product_discounts', 'deal_badge_label')) {
                $table->dropColumn('deal_badge_label');
            }
        });
    }
};
