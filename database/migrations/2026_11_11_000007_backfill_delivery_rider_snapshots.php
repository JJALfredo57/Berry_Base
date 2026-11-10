<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasTable('riders') || !Schema::hasColumn('orders', 'delivery_rider_id') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE orders o JOIN riders r ON r.id = o.rider_id SET o.delivery_rider_id = o.rider_id, o.delivery_rider_name = r.name WHERE o.rider_id IS NOT NULL AND (o.delivery_rider_id IS NULL OR o.delivery_rider_name IS NULL)");

        if (Schema::hasTable('rider_remittances')) {
            DB::statement("UPDATE orders o JOIN rider_remittances rr ON rr.order_id = o.id JOIN riders r ON r.id = rr.rider_id SET o.delivery_rider_id = rr.rider_id, o.delivery_rider_name = r.name WHERE o.rider_id IS NULL AND o.delivery_rider_id IS NULL");
        }
    }

    public function down(): void
    {
        // Historical snapshots are retained for delivery-message authorization.
    }
};
