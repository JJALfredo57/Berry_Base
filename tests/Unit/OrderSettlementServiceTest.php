<?php

namespace Tests\Unit;

use App\Services\OrderSettlementService;
use PHPUnit\Framework\TestCase;

class OrderSettlementServiceTest extends TestCase
{
    public function test_partial_cod_uses_full_cake_price_for_seller_rate_commission(): void
    {
        $order = (object) [
            'id' => 'COD-100', 'total_price' => 1100, 'delivery_fee' => 100, 'service_charge' => 0,
            'payment_method' => 'COD', 'fulfillment_type' => 'Delivery', 'payment_status' => 'Paid',
            'deposit_status' => 'paid', 'deposit_amount' => 550, 'online_paid_amount' => 550,
            'remittance_snapshot' => (object) ['status' => 'confirmed', 'customer_collected_amount' => 550, 'delivery_fee_retained' => 100, 'cake_remittance_amount' => 450],
        ];
        $shop = (object) ['commission_enabled' => true, 'commission_rate' => 2];

        $result = (new OrderSettlementService)->snapshot($order, $shop);

        $this->assertSame(20.0, $result['commission_amount']);
        $this->assertSame(530.0, $result['seller_net_amount']);
        $this->assertSame(450.0, $result['cash_to_seller_amount']);
        $this->assertSame(980.0, $result['total_seller_earnings']);
        $this->assertEquals(0.0, $result['rider_online_fee_amount']);
    }

    public function test_full_online_delivery_assigns_delivery_fee_to_rider_not_seller(): void
    {
        $order = (object) [
            'id' => 'ONLINE-100', 'total_price' => 1100, 'delivery_fee' => 100, 'service_charge' => 0,
            'payment_method' => 'GCash', 'fulfillment_type' => 'Delivery', 'payment_status' => 'Paid',
            'deposit_status' => 'paid', 'deposit_amount' => 1100, 'online_paid_amount' => 1100, 'remittance_snapshot' => null,
        ];
        $shop = (object) ['commission_enabled' => true, 'commission_rate' => 2];

        $result = (new OrderSettlementService)->snapshot($order, $shop);

        $this->assertSame(20.0, $result['commission_amount']);
        $this->assertSame(980.0, $result['seller_net_amount']);
        $this->assertSame(100.0, $result['rider_online_fee_amount']);
        $this->assertSame(980.0, $result['total_seller_earnings']);
    }

    public function test_partial_cash_on_pickup_keeps_cash_out_of_the_payout_request(): void
    {
        $order = (object) [
            'id' => 'COP-100', 'total_price' => 1000, 'delivery_fee' => 0, 'service_charge' => 0,
            'payment_method' => 'COP', 'fulfillment_type' => 'Pickup', 'payment_status' => 'Paid',
            'deposit_status' => 'paid', 'deposit_amount' => 500, 'online_paid_amount' => 500,
            'remittance_snapshot' => null,
        ];
        $shop = (object) ['commission_enabled' => true, 'commission_rate' => 2];

        $result = (new OrderSettlementService)->snapshot($order, $shop);

        $this->assertSame(20.0, $result['commission_amount']);
        $this->assertSame(480.0, $result['seller_net_amount']);
        $this->assertSame(500.0, $result['cash_to_seller_amount']);
        $this->assertSame(980.0, $result['total_seller_earnings']);
    }

    public function test_full_online_pickup_has_no_rider_or_cash_collection(): void
    {
        $order = (object) [
            'id' => 'PICKUP-100', 'total_price' => 1000, 'delivery_fee' => 0, 'service_charge' => 0,
            'payment_method' => 'GCash', 'fulfillment_type' => 'Pickup', 'payment_status' => 'Paid',
            'deposit_status' => 'paid', 'deposit_amount' => 1000, 'online_paid_amount' => 1000,
            'remittance_snapshot' => null,
        ];
        $shop = (object) ['commission_enabled' => true, 'commission_rate' => 3];

        $result = (new OrderSettlementService)->snapshot($order, $shop);

        $this->assertSame(30.0, $result['commission_amount']);
        $this->assertSame(970.0, $result['seller_net_amount']);
        $this->assertSame(0.0, $result['cash_collected_amount']);
        $this->assertSame(0.0, $result['rider_online_fee_amount']);
    }
}
