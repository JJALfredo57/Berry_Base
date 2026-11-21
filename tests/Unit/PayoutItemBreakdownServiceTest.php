<?php

namespace Tests\Unit;

use App\Services\PayoutItemBreakdownService;
use PHPUnit\Framework\TestCase;

class PayoutItemBreakdownServiceTest extends TestCase
{
    public function test_item_net_uses_full_cake_commission_and_keeps_cash_separate(): void
    {
        $ledger = (object) ['cake_amount' => 1000, 'commission_amount' => 20, 'seller_net_amount' => 480, 'cash_to_seller_amount' => 500];
        $items = collect([(object) ['quantity' => 1, 'final_unit_price_snapshot' => 400], (object) ['quantity' => 1, 'final_unit_price_snapshot' => 600]]);
        $rows = (new PayoutItemBreakdownService)->forLedger($ledger, $items);
        $this->assertEquals([192, 288], $rows->pluck('payout_seller_net_amount')->all());
        $this->assertEquals([8, 12], $rows->pluck('payout_commission_amount')->all());
        $this->assertEquals([200, 300], $rows->pluck('payout_cash_to_seller_amount')->all());
    }

    public function test_rounding_never_changes_the_order_total_even_with_missing_prices(): void
    {
        $ledger = (object) ['cake_amount' => 10, 'commission_amount' => .01, 'seller_net_amount' => .02];
        $items = collect(array_fill(0, 3, (object) ['quantity' => 1]));
        $rows = (new PayoutItemBreakdownService)->forLedger($ledger, $items);
        $this->assertEquals(10, $rows->sum('payout_cake_amount'));
        $this->assertEquals(.01, $rows->sum('payout_commission_amount'));
        $this->assertEquals(.02, $rows->sum('payout_seller_net_amount'));
    }
}
