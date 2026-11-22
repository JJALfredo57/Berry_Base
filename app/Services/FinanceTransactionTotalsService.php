<?php

namespace App\Services;

use Illuminate\Support\Collection;

class FinanceTransactionTotalsService
{
    public function summarize(Collection $transactions, Collection $paymentRows, bool $hasUnifiedSnapshot): array
    {
        $orderEarnings = $transactions->where('type', 'Order earnings');
        $businessExpenses = round((float) $transactions
            ->where('type', 'Business expense')
            ->sum(fn ($row) => abs(min(0, (float) ($row->net ?? 0)))), 2);
        $sellerEarnings = round((float) ($hasUnifiedSnapshot
            ? $orderEarnings->sum('total_seller_earnings')
            : $orderEarnings->sum('net')), 2);

        return [
            'customer_payments' => round((float) $paymentRows->sum('amount'), 2),
            'rider_delivery_fees' => round((float) $orderEarnings->sum('rider_delivery_fee'), 2),
            'commission' => round((float) $orderEarnings->sum('commission'), 2),
            'total_seller_earnings' => $sellerEarnings,
            'business_expenses' => $businessExpenses,
            'estimated_profit' => round($sellerEarnings - $businessExpenses, 2),
            'receipts' => (int) $paymentRows->count(),
            'cash_collections' => $hasUnifiedSnapshot ? round((float) $orderEarnings->sum('cash_collected'), 2) : 0.0,
            'cash_to_seller' => $hasUnifiedSnapshot ? round((float) $orderEarnings->sum('cash_to_seller'), 2) : 0.0,
        ];
    }
}
