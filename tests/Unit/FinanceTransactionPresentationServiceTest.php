<?php

namespace Tests\Unit;

use App\Services\FinanceTransactionPresentationService;
use Tests\TestCase;

class FinanceTransactionPresentationServiceTest extends TestCase
{
    public function test_mixed_receipts_are_totaled_but_kept_separate_from_online_payout(): void
    {
        $transaction = (object) [
            'type' => 'Order earnings',
            'status' => 'available',
            'gross' => 189,
            'net' => 185,
            'online_collected' => 189,
            'cash_collected' => 189,
            'payment_events' => collect([
                (object) ['amount' => 189, 'method' => 'GCash'],
                (object) ['amount' => 189, 'method' => 'COD'],
            ]),
        ];

        $result = app(FinanceTransactionPresentationService::class)->present($transaction);

        $this->assertSame(378.0, $result->customer_payment_total);
        $this->assertSame(189.0, $result->online_payment_total);
        $this->assertSame(189.0, $result->cash_payment_total);
        $this->assertSame(185, $result->net);
        $this->assertSame('Available for online payout', $result->status_label);
    }

    public function test_single_and_legacy_receipts_have_safe_totals(): void
    {
        $service = app(FinanceTransactionPresentationService::class);
        $single = $service->present((object) [
            'type' => 'Customer payment received', 'status' => 'verified - awaiting completion',
            'gross' => 100, 'net' => 0, 'online_collected' => 100, 'cash_collected' => 0,
            'payment_events' => collect([(object) ['amount' => 100]]),
        ]);
        $legacy = $service->present((object) [
            'type' => 'Order earnings', 'status' => 'available', 'gross' => 150, 'net' => 145,
            'payment_events' => collect(),
        ]);

        $this->assertSame(100.0, $single->customer_payment_total);
        $this->assertSame('Payment verified — awaiting completion', $single->status_label);
        $this->assertSame(150.0, $legacy->customer_payment_total);
    }

    public function test_payout_status_labels_follow_the_ledger_lifecycle(): void
    {
        $service = app(FinanceTransactionPresentationService::class);

        $this->assertSame('Payout requested', $service->statusLabel('requested', 'Order earnings', 185));
        $this->assertSame('Payout processing', $service->statusLabel('processing', 'Order earnings', 185));
        $this->assertSame('Paid out', $service->statusLabel('paid', 'Order earnings', 185));
        $this->assertSame('No online payout', $service->statusLabel('available', 'Order earnings', 0));
    }
}
