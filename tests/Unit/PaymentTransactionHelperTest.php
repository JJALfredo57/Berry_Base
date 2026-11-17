<?php

namespace Tests\Unit;

use App\Helpers\PaymentTransactionHelper;
use Tests\TestCase;

class PaymentTransactionHelperTest extends TestCase
{
    public function test_it_uses_the_paid_paymongo_payment_amounts_instead_of_a_fixed_rate(): void
    {
        $checkout = [
            'data' => ['attributes' => ['payments' => [
                ['attributes' => ['status' => 'pending', 'amount' => 99999, 'fee' => 9999]],
                ['attributes' => [
                    'status' => 'paid',
                    'amount' => 14317,
                    'net_amount' => 13900,
                    'fee' => 417,
                    'reference_number' => 'pay_actual_fee',
                ]],
            ]]],
        ];

        $amounts = PaymentTransactionHelper::paymongoAmountsFromCheckoutSession($checkout);

        $this->assertSame(4.17, $amounts['service_fee']);
        $this->assertSame(143.17, $amounts['customer_paid_amount']);
        $this->assertSame(139.0, $amounts['net_amount']);
        $this->assertSame('pay_actual_fee', $amounts['provider_reference']);
    }

    public function test_it_derives_the_actual_fee_from_paymongo_gross_and_net_amounts_when_needed(): void
    {
        $checkout = [
            'data' => ['attributes' => ['payments' => [[
                'attributes' => [
                    'status' => 'paid',
                    'amount' => 10350,
                    'net_amount' => 10000,
                ],
            ]]]],
        ];

        $amounts = PaymentTransactionHelper::paymongoAmountsFromCheckoutSession($checkout);

        $this->assertSame(3.5, $amounts['service_fee']);
        $this->assertSame(103.5, $amounts['customer_paid_amount']);
    }

    public function test_it_does_not_create_a_fee_when_paymongo_has_no_paid_payment(): void
    {
        $checkout = ['data' => ['attributes' => ['payments' => [[
            'attributes' => ['status' => 'pending', 'amount' => 10300, 'fee' => 300],
        ]]]]];

        $this->assertNull(PaymentTransactionHelper::paymongoAmountsFromCheckoutSession($checkout));
    }
}
