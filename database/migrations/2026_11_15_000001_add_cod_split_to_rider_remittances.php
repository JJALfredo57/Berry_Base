<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rider_remittances')) return;

        Schema::table('rider_remittances', function (Blueprint $table) {
            if (!Schema::hasColumn('rider_remittances', 'customer_collected_amount')) {
                $table->decimal('customer_collected_amount', 10, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('rider_remittances', 'cake_remittance_amount')) {
                $table->decimal('cake_remittance_amount', 10, 2)->nullable()->after('customer_collected_amount');
            }
            if (!Schema::hasColumn('rider_remittances', 'delivery_fee_retained')) {
                $table->decimal('delivery_fee_retained', 10, 2)->nullable()->after('cake_remittance_amount');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rider_remittances')) return;
        Schema::table('rider_remittances', function (Blueprint $table) {
            foreach (['delivery_fee_retained', 'cake_remittance_amount', 'customer_collected_amount'] as $column) {
                if (Schema::hasColumn('rider_remittances', $column)) $table->dropColumn($column);
            }
        });
    }
};
