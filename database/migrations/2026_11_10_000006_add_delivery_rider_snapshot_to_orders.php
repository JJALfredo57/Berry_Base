<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_rider_id')) {
                $table->unsignedBigInteger('delivery_rider_id')->nullable()->index();
            }
            if (!Schema::hasColumn('orders', 'delivery_rider_name')) {
                $table->string('delivery_rider_name', 160)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_rider_name')) {
                $table->dropColumn('delivery_rider_name');
            }
            if (Schema::hasColumn('orders', 'delivery_rider_id')) {
                $table->dropColumn('delivery_rider_id');
            }
        });
    }
};
