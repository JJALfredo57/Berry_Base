<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_transactions')) return;

        Schema::table('payment_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_transactions', 'payment_service_fee')) {
                $table->decimal('payment_service_fee', 10, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('payment_transactions', 'customer_paid_amount')) {
                $table->decimal('customer_paid_amount', 10, 2)->default(0)->after('payment_service_fee');
            }
        });

        DB::table('payment_transactions')
            ->orderBy('id')
            ->get()
            ->each(function ($tx) {
                $amount = (float) ($tx->amount ?? 0);
                $isGcash = ($tx->method ?? '') === 'GCash' || ($tx->provider ?? '') === 'PayMongo';
                $fee = $isGcash ? round($amount * 0.03, 2) : 0;

                DB::table('payment_transactions')->where('id', $tx->id)->update([
                    'payment_service_fee' => $fee,
                    'customer_paid_amount' => $amount + $fee,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_transactions')) return;

        Schema::table('payment_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('payment_transactions', 'customer_paid_amount')) {
                $table->dropColumn('customer_paid_amount');
            }
            if (Schema::hasColumn('payment_transactions', 'payment_service_fee')) {
                $table->dropColumn('payment_service_fee');
            }
        });
    }
};
