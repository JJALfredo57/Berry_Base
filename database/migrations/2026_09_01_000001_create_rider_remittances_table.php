<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rider_remittances')) {
            Schema::create('rider_remittances', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12)->unique();
                $t->string('shop_id', 12)->index();
                $t->unsignedBigInteger('rider_id')->nullable()->index();
                $t->decimal('amount', 10, 2);
                $t->string('collection_method', 30)->default('Cash');
                $t->string('remittance_method', 30)->nullable();
                $t->string('status', 30)->default('pending')->index();
                $t->string('reference_number', 120)->nullable();
                $t->string('receipt_path')->nullable();
                $t->text('rider_note')->nullable();
                $t->text('seller_note')->nullable();
                $t->timestamp('collected_at')->nullable()->index();
                $t->timestamp('submitted_at')->nullable()->index();
                $t->timestamp('confirmed_at')->nullable()->index();
                $t->timestamp('rejected_at')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('orders') && Schema::hasTable('rider_remittances')) {
            DB::table('orders')
                ->where('fulfillment_type', 'Delivery')
                ->whereRaw('UPPER(payment_method) = ?', ['COD'])
                ->whereIn('status', ['Delivered', 'Picked Up'])
                ->whereNotNull('shop_id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('rider_remittances as rr')
                        ->whereColumn('rr.order_id', 'orders.id');
                })
                ->orderBy('id')
                ->get()
                ->each(function ($order) {
                    $total = (float) ($order->total_price ?? 0);
                    $deposit = ($order->deposit_status ?? '') === 'paid'
                        ? (float) ($order->deposit_amount ?? 0)
                        : 0;
                    $amount = round(max(0, $total - $deposit), 2);
                    if ($amount <= 0) return;

                    DB::table('rider_remittances')->insert([
                        'order_id' => $order->id,
                        'shop_id' => $order->shop_id,
                        'rider_id' => $order->rider_id,
                        'amount' => $amount,
                        'collection_method' => 'Cash',
                        'status' => $order->settled_at ? 'confirmed' : 'pending',
                        'collected_at' => $order->delivered_at ?? $order->paid_at ?? $order->updated_at ?? now(),
                        'confirmed_at' => $order->settled_at,
                        'seller_note' => $order->settled_at ? 'Backfilled from previously settled COD order.' : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_remittances');
    }
};
