<?php

namespace Tests\Unit;

use App\Services\FinanceTransactionPresentationService;
use App\Services\SellerBalanceService;
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
                (object) ['amount' => 11, 'method' => 'Cash'],
            ]),
        ];

        $result = app(FinanceTransactionPresentationService::class)->present($transaction);

        $this->assertSame(200.0, $result->customer_payment_total);
        $this->assertSame(189.0, $result->online_payment_total);
        $this->assertSame(11.0, $result->cash_payment_total);
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

    public function test_seller_allocation_labels_do_not_claim_a_rider_transfer_status(): void
    {
        $this->assertSame('Ready for seller payout', FinanceTransactionPresentationService::sellerAllocationLabel('available'));
        $this->assertSame('Seller payout completed', FinanceTransactionPresentationService::sellerAllocationLabel('paid'));
        $this->assertSame('Seller payout processing', FinanceTransactionPresentationService::sellerAllocationLabel('processing'));
    }

    public function test_delivered_order_shows_its_real_payout_blocker_instead_of_waiting_for_delivery(): void
    {
        $this->assertSame('Awaiting seller confirmation of COD cash', SellerBalanceService::awaitingPayoutLabel('Delivery', 'Delivered', 'Awaiting seller confirmation of COD cash'));
        $this->assertSame('Payment balance not settled', SellerBalanceService::awaitingPayoutLabel('Pickup', 'Picked Up', 'Payment balance not settled'));
        $this->assertSame('Awaiting payout eligibility', SellerBalanceService::awaitingPayoutLabel('Delivery', 'Delivered', 'Awaiting Delivery/Pickup'));
        $this->assertSame('Waiting for Delivery', SellerBalanceService::awaitingPayoutLabel('Delivery', 'Out for Delivery', null));
    }
}
