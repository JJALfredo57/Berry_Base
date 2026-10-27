<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // orders.id is a 12-character string, so QR rows must store it as a string too.
    public function up(): void
    {
        if (!Schema::hasTable('customer_payment_qrs') || !Schema::hasColumn('customer_payment_qrs', 'order_id')) {
            return;
        }

        Schema::table('customer_payment_qrs', function (Blueprint $table) {
            $table->string('order_id', 12)->change();
        });
    }

    public function down(): void
    {
        // Kept as string: converting back would fail for existing non-numeric order ids.
    }
};
