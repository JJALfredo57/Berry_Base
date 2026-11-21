<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_payout_ledgers', function (Blueprint $table) {
            $table->decimal('online_funds_refunded', 12, 2)->default(0);
            $table->decimal('seller_refund_amount', 12, 2)->default(0);
            $table->decimal('commission_refund_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        // Retain the audit breakdown rather than erase financial history.
    }
};
