<?php

namespace Tests\Unit;

use App\Services\FinanceTransactionTotalsService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FinanceTransactionTotalsServiceTest extends TestCase
{
    public function test_totals_separate_earnings_expenses_profit_and_payout_transfers(): void
    {
        $transactions = collect([
            (object) ['type' => 'Order earnings', 'net' => 185, 'total_seller_earnings' => 196, 'rider_delivery_fee' => 178, 'commission' => 4, 'cash_collected' => 189, 'cash_to_seller' => 11],
            (object) ['type' => 'Business expense', 'net' => -24],
            (object) ['type' => 'Business expense', 'net' => -92],
            (object) ['type' => 'Business expense', 'net' => -23],
            (object) ['type' => 'Seller payout', 'net' => -185],
        ]);
        $payments = new Collection([(object) ['amount' => 189], (object) ['amount' => 11]]);

        $this->assertSame([
            'customer_payments' => 200.0,
            'rider_delivery_fees' => 178.0,
            'commission' => 4.0,
            'total_seller_earnings' => 196.0,
            'business_expenses' => 139.0,
            'estimated_profit' => 57.0,
            'receipts' => 2,
            'cash_collections' => 189.0,
            'cash_to_seller' => 11.0,
        ], app(FinanceTransactionTotalsService::class)->summarize($transactions, $payments, true));
    }

    public function test_fulfillment_filtered_order_totals_do_not_invent_expenses(): void
    {
        $transactions = collect([
            (object) ['type' => 'Order earnings', 'net' => 480, 'rider_delivery_fee' => 0, 'commission' => 20],
        ]);

        $totals = app(FinanceTransactionTotalsService::class)->summarize($transactions, collect(), false);

        $this->assertSame(480.0, $totals['total_seller_earnings']);
        $this->assertSame(0.0, $totals['business_expenses']);
        $this->assertSame(480.0, $totals['estimated_profit']);
    }
}
