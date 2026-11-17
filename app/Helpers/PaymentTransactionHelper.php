<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentTransactionHelper
{
    public static function record(
        object $order,
        string $type,
        string $method,
        float $amount,
        ?string $providerReference = null,
        ?array $paymongoAmounts = null
    ): void
    {
        if ($amount <= 0 || !Schema::hasTable('payment_transactions')) return;

        $total = (float) ($order->total_price ?? 0);
        $remaining = str_starts_with($type, 'downpayment')
            ? max(0, $total - $amount)
            : 0;
        // PayMongo calculates pass-on fees only after the customer selects a
        // payment method. Never estimate that fee locally: use the verified
        // Checkout Session payment amounts instead.
        $serviceFee = 0.0;
        $customerPaidAmount = $amount;
        if ($method === 'GCash' && $paymongoAmounts !== null) {
            $serviceFee = round(max(0, (float) ($paymongoAmounts['service_fee'] ?? 0)), 2);
            $customerPaidAmount = round(max(0, (float) ($paymongoAmounts['customer_paid_amount'] ?? $amount)), 2);
            $providerReference ??= $paymongoAmounts['provider_reference'] ?? null;
        } elseif ($method === 'GCash') {
            Log::warning('GCash payment recorded without verified PayMongo fee data.', [
                'order_id' => $order->id ?? null,
                'type' => $type,
                'provider_reference' => $providerReference,
            ]);
        }

        $values = [
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
        ];

        if (Schema::hasColumn('payment_transactions', 'payment_service_fee')) {
            $values['payment_service_fee'] = $serviceFee;
        }
        if (Schema::hasColumn('payment_transactions', 'customer_paid_amount')) {
            $values['customer_paid_amount'] = $customerPaidAmount;
        }

        DB::table('payment_transactions')->updateOrInsert(['order_id' => $order->id, 'type' => $type], $values);
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

    /** Record only the cake cash handed to the seller after seller confirmation. */
    public static function recordConfirmedCashHandover(object $order, float $amount): void
    {
        self::record($order, 'remaining_cash_delivery', 'Cash', max(0, $amount));
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

    /**
     * Extract actual monetary amounts from a verified PayMongo Checkout
     * Session response. PayMongo returns monetary fields in centavos.
     */
    public static function paymongoAmountsFromCheckoutSession(array $checkoutSession): ?array
    {
        $payments = $checkoutSession['data']['attributes']['payments'] ?? [];
        if (!is_array($payments)) return null;

        foreach ($payments as $payment) {
            $attributes = is_array($payment) ? ($payment['attributes'] ?? []) : [];
            if (($attributes['status'] ?? '') !== 'paid') continue;

            $gross = $attributes['amount'] ?? null;
            $net = $attributes['net_amount'] ?? null;
            $fee = $attributes['fee'] ?? null;
            if (!is_numeric($fee) && is_numeric($gross) && is_numeric($net)) {
                $fee = (float) $gross - (float) $net;
            }
            if (!is_numeric($gross) || !is_numeric($fee) || (float) $gross < 0 || (float) $fee < 0) {
                return null;
            }

            return [
                'service_fee' => round((float) $fee / 100, 2),
                'customer_paid_amount' => round((float) $gross / 100, 2),
                'net_amount' => is_numeric($net) ? round((float) $net / 100, 2) : null,
                'provider_reference' => $attributes['reference_number'] ?? null,
            ];
        }

        return null;
    }
}
