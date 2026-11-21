<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_payout_ledgers', function (Blueprint $table) {
            $table->unsignedTinyInteger('allocation_version')->default(0);
            $table->string('blocked_reason')->nullable();
            $table->string('source_order_id', 40)->nullable()->index();
        });
        Schema::create('seller_balance_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('shop_id', 40)->index();
            $table->string('order_id', 40)->nullable()->index();
            $table->unsignedBigInteger('payout_id')->nullable()->index();
            $table->string('type', 40);
            $table->decimal('amount', 12, 2);
            $table->string('status', 40);
            $table->text('note');
            $table->timestamp('created_at');
        });
        Schema::table('rider_delivery_earnings', function (Blueprint $table) {
            $table->unsignedBigInteger('payout_id')->nullable()->index();
        });
        // Data is reconciled by payouts:sync after a reviewed preview. Do not
        // rewrite transferred money or run a financial backfill during DDL.
        DB::table('platform_settings')->update(['payout_hold_days' => 0, 'payout_minimum_amount' => 0, 'payout_mode' => 'manual']);
    }

    public function down(): void
    {
        // Financial audit data and transfer history deliberately survive rollback.
        // Redeploying old code must not re-enable already retired holding rules.
    }
};
