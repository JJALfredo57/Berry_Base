<?php

namespace Tests\Unit;

use App\Services\SellerPayoutService;
use Tests\TestCase;

class SellerPayoutServiceTest extends TestCase
{
    public function test_cash_on_pickup_uses_only_the_online_deposit_for_payouts(): void
    {
        $amount = $this->collectedAmount((object) [
            'payment_method' => 'COP',
            'fulfillment_type' => 'Pickup',
            'payment_status' => 'Paid',
            'deposit_status' => 'paid',
            'deposit_amount' => 875,
            'total_price' => 1750,
        ]);

        $this->assertSame(875.0, $amount);
    }

    public function test_full_gcash_uses_the_full_order_total_for_payouts(): void
    {
        $amount = $this->collectedAmount((object) [
            'payment_method' => 'GCash',
            'fulfillment_type' => 'Pickup',
            'payment_status' => 'Paid',
            'deposit_status' => 'paid',
            'deposit_amount' => 875,
            'total_price' => 1750,
        ]);

        $this->assertSame(1750.0, $amount);
    }

    public function test_cash_on_delivery_uses_only_the_online_deposit_when_the_balance_is_cash(): void
    {
        $amount = $this->collectedAmount((object) [
            'payment_method' => 'COD',
            'fulfillment_type' => 'Delivery',
            'payment_status' => 'Paid',
            'deposit_status' => 'paid',
            'deposit_amount' => 875,
            'remittance_status' => 'confirmed',
            'remittance_method' => 'cash',
            'remittance_amount' => 875,
            'total_price' => 1750,
        ]);

        $this->assertSame(875.0, $amount);
    }

    public function test_cash_on_delivery_includes_a_paymongo_remitted_balance(): void
    {
        $amount = $this->collectedAmount((object) [
            'payment_method' => 'COD',
            'fulfillment_type' => 'Delivery',
            'payment_status' => 'Paid',
            'deposit_status' => 'paid',
            'deposit_amount' => 875,
            'remittance_status' => 'confirmed',
            'remittance_method' => 'gcash_paymongo',
            'remittance_amount' => 875,
            'total_price' => 1750,
        ]);

        $this->assertSame(1750.0, $amount);
    }

    private function collectedAmount(object $order): float
    {
        $method = new \ReflectionMethod(SellerPayoutService::class, 'collectedAmount');

        return $method->invoke(app(SellerPayoutService::class), $order);
    }
}
