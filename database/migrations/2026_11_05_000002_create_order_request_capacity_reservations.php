<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('order_request_capacity_reservations')) return;
        Schema::create('order_request_capacity_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_request_id', 12)->unique();
            $table->string('shop_id', 12)->index();
            $table->date('reserved_date')->index();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['shop_id', 'reserved_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_request_capacity_reservations');
    }
};
