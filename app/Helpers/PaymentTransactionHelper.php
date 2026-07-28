<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentTransactionHelper
{
    public static function record(object $order, string $type, string $method, float $amount, ?string $providerReference = null): void
    {
        if ($amount <= 0 || !Schema::hasTable('payment_transactions')) return;

        $total = (float) ($order->total_price ?? 0);
        $remaining = str_starts_with($type, 'downpayment')
            ? max(0, $total - $amount)
            : 0;

        DB::table('payment_transactions')->updateOrInsert(
            ['order_id' => $order->id, 'type' => $type],
            [
                'track_code'         => strtoupper((string) ($order->track_code ?? '')),
                'guest_phone'        => $order->guest_phone ?? null,
                'method'             => $method,
                'amount'             => $amount,
                'order_total'        => $total,
                'remaining_balance'  => $remaining,
                'payment_status'     => 'paid',
                'provider'           => $method === 'GCash' ? 'PayMongo' : 'Cash',
                'provider_reference' => $providerReference,
                'paid_at'            => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );
    }

    public static function recordFinalCashIfNeeded(object $order, string $finalStatus): void
    {
        if (!Schema::hasTable('payment_transactions')) return;

        $method = strtoupper((string) ($order->payment_method ?? ''));
        $isCash = in_array($method, ['COD', 'COP'], true);
        if (!$isCash) return;

        $total = (float) ($order->total_price ?? 0);
        $depositPaid = ($order->deposit_status ?? '') === 'paid';
        $deposit = $depositPaid ? (float) ($order->deposit_amount ?? 0) : 0;
        $amount = max(0, $total - $deposit);
        if ($amount <= 0) return;

        $isPickup = $finalStatus === 'Picked Up';
        $typePrefix = $depositPaid ? 'remaining_cash' : 'full_cash';
        self::record($order, $typePrefix . ($isPickup ? '_pickup' : '_delivery'), 'Cash', $amount);
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'downpayment_gcash' => 'GCash Downpayment',
            'remaining_gcash' => 'GCash Remaining Balance',
            'full_gcash' => 'GCash Full Payment',
            'remaining_cash_pickup' => 'Cash Pickup Balance',
            'remaining_cash_delivery' => 'Cash Delivery Balance',
            'full_cash_pickup' => 'Cash on Pickup Payment',
            'full_cash_delivery' => 'Cash on Delivery Payment',
            default => 'Payment Receipt',
        };
    }
}
