<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('order_discounts')) {
            Schema::table('order_discounts', function (Blueprint $table) {
                if (!Schema::hasColumn('order_discounts', 'funding_source')) {
                    $table->string('funding_source', 20)->nullable()->after('amount')->index();
                }
            });
        }

        if (Schema::hasTable('seller_payout_ledgers')) {
            Schema::table('seller_payout_ledgers', function (Blueprint $table) {
                if (!Schema::hasColumn('seller_payout_ledgers', 'platform_funded_discount')) {
                    $table->decimal('platform_funded_discount', 10, 2)->default(0)->after('gross_amount');
                }
                if (!Schema::hasColumn('seller_payout_ledgers', 'seller_funded_discount')) {
                    $table->decimal('seller_funded_discount', 10, 2)->default(0)->after('platform_funded_discount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seller_payout_ledgers')) {
            Schema::table('seller_payout_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('seller_payout_ledgers', 'seller_funded_discount')) {
                    $table->dropColumn('seller_funded_discount');
                }
                if (Schema::hasColumn('seller_payout_ledgers', 'platform_funded_discount')) {
                    $table->dropColumn('platform_funded_discount');
                }
            });
        }

        if (Schema::hasTable('order_discounts')) {
            Schema::table('order_discounts', function (Blueprint $table) {
                if (Schema::hasColumn('order_discounts', 'funding_source')) {
                    $table->dropColumn('funding_source');
                }
            });
        }
    }
};

