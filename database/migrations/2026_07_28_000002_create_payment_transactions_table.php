<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12)->index();
                $t->string('track_code', 30)->index();
                $t->string('guest_phone', 30)->nullable()->index();
                $t->string('type', 40)->index();
                $t->string('method', 30)->index();
                $t->decimal('amount', 10, 2);
                $t->decimal('order_total', 10, 2)->default(0);
                $t->decimal('remaining_balance', 10, 2)->default(0);
                $t->string('payment_status', 30)->default('paid');
                $t->string('provider', 30)->nullable();
                $t->string('provider_reference', 120)->nullable();
                $t->timestamp('paid_at')->nullable()->index();
                $t->timestamps();
                $t->unique(['order_id', 'type']);
            });
        }

        DB::table('orders')
            ->where(function ($q) {
                $q->where('deposit_status', 'paid')->orWhere('payment_status', 'Paid');
            })
            ->orderBy('created_at')
            ->get()
            ->each(function ($order) {
                $total = (float) ($order->total_price ?? 0);
                $deposit = (float) ($order->deposit_amount ?? 0);
                $paidAt = $order->deposit_paid_at ?? $order->paid_at ?? $order->updated_at ?? now();

                if (($order->deposit_status ?? '') === 'paid' && $deposit > 0 && $deposit < $total) {
                    DB::table('payment_transactions')->updateOrInsert(
                        ['order_id' => $order->id, 'type' => 'downpayment_gcash'],
                        [
                            'track_code' => strtoupper((string) $order->track_code),
                            'guest_phone' => $order->guest_phone,
                            'method' => 'GCash',
                            'amount' => $deposit,
                            'order_total' => $total,
                            'remaining_balance' => max(0, $total - $deposit),
                            'payment_status' => 'paid',
                            'provider' => 'PayMongo',
                            'provider_reference' => null,
                            'paid_at' => $paidAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $method = strtoupper((string) ($order->payment_method ?? ''));
                    if (($order->payment_status ?? '') === 'Paid'
                        && in_array($order->status ?? '', ['Delivered', 'Picked Up'], true)
                        && in_array($method, ['COD', 'COP'], true)) {
                        $isPickup = ($order->status ?? '') === 'Picked Up';
                        DB::table('payment_transactions')->updateOrInsert(
                            ['order_id' => $order->id, 'type' => 'remaining_cash' . ($isPickup ? '_pickup' : '_delivery')],
                            [
                                'track_code' => strtoupper((string) $order->track_code),
                                'guest_phone' => $order->guest_phone,
                                'method' => 'Cash',
                                'amount' => max(0, $total - $deposit),
                                'order_total' => $total,
                                'remaining_balance' => 0,
                                'payment_status' => 'paid',
                                'provider' => 'Cash',
                                'provider_reference' => null,
                                'paid_at' => $order->paid_at ?? $order->delivered_at ?? $paidAt,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                } elseif (($order->payment_status ?? '') === 'Paid' && $total > 0) {
                    DB::table('payment_transactions')->updateOrInsert(
                        ['order_id' => $order->id, 'type' => 'full_gcash'],
                        [
                            'track_code' => strtoupper((string) $order->track_code),
                            'guest_phone' => $order->guest_phone,
                            'method' => ($order->payment_method ?? '') === 'GCash' ? 'GCash' : 'Cash',
                            'amount' => $total,
                            'order_total' => $total,
                            'remaining_balance' => 0,
                            'payment_status' => 'paid',
                            'provider' => ($order->payment_method ?? '') === 'GCash' ? 'PayMongo' : 'Cash',
                            'provider_reference' => null,
                            'paid_at' => $order->paid_at ?? $paidAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
