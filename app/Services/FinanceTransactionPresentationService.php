<?php

namespace App\Services;

use Illuminate\Support\Collection;

class FinanceTransactionPresentationService
{
    public function present(object $transaction): object
    {
        $receipts = $transaction->payment_events ?? collect();
        if (! $receipts instanceof Collection) {
            $receipts = collect($receipts);
        }

        $receiptTotal = round((float) $receipts->sum('amount'), 2);
        $transaction->customer_payment_total = $receipts->isNotEmpty()
            ? $receiptTotal
            : round(max(0, (float) ($transaction->gross ?? 0)), 2);
        $transaction->online_payment_total = $receipts->isNotEmpty()
            ? round((float) $receipts->filter(fn ($receipt) => strtoupper((string) ($receipt->method ?? '')) === 'GCASH')->sum('amount'), 2)
            : round(max(0, (float) ($transaction->online_collected ?? 0)), 2);
        $transaction->cash_payment_total = $receipts->isNotEmpty()
            ? round(max(0, $receiptTotal - $transaction->online_payment_total), 2)
            : round(max(0, (float) ($transaction->cash_collected ?? 0)), 2);
        $transaction->status_label = $this->statusLabel(
            (string) ($transaction->status ?? ''),
            (string) ($transaction->type ?? ''),
            (float) ($transaction->net ?? 0)
        );
        $transaction->status_tone = (string) ($transaction->type ?? '') === 'Order earnings'
            && (float) ($transaction->net ?? 0) <= 0
                ? 'light'
                : $this->statusTone((string) ($transaction->status ?? ''));

        return $transaction;
    }

    public function statusLabel(string $status, string $type, float $onlinePayout): string
    {
        $normalized = strtolower(trim($status));

        if ($type === 'Order earnings' && $onlinePayout <= 0) {
            return 'No online payout';
        }

        return match ($normalized) {
            'awaiting_completion' => 'Awaiting order completion',
            'available' => 'Available for online payout',
            'requested' => 'Payout requested',
            'processing' => 'Payout processing',
            'paid' => 'Paid out',
            'refund_reserved' => 'Refund reserved',
            'refunded' => 'Refunded',
            'cash_settled' => 'Cash settled — no online payout',
            'cancelled' => 'Payout cancelled',
            'finance_review' => 'Finance review required',
            'verified - awaiting completion' => 'Payment verified — awaiting completion',
            default => ucfirst(str_replace('_', ' ', $normalized ?: 'Recorded')),
        };
    }

    private function statusTone(string $status): string
    {
        return match (strtolower(trim($status))) {
            'available', 'paid', 'cash_settled' => 'success',
            'requested', 'processing', 'awaiting_completion' => 'warning',
            'refund_reserved', 'refunded', 'cancelled', 'finance_review' => 'danger',
            default => 'light',
        };
    }
}
