<?php

namespace Tests\Feature;

use App\Services\FinanceTransactionTotalsService;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FinanceSettlementBreakdownViewTest extends TestCase
{
    public function test_totals_align_with_the_six_amount_columns_and_exclude_transfers_and_expenses(): void
    {
        $transactions = $this->transactions();
        $before = $transactions->toJson();
        $xpath = $this->xpath($this->renderFinance($transactions));
        $table = '//section[@id="settlement-breakdown"]//table';

        $this->assertSame('2', $xpath->evaluate('string('.$table.'/tfoot/tr/th/@colspan)'));
        $this->assertSame('row', $xpath->evaluate('string('.$table.'/tfoot/tr/th/@scope)'));
        $this->assertSame('Total · 2 orders', trim($xpath->evaluate('string('.$table.'/tfoot/tr/th)')));
        $this->assertSame(8, $xpath->query($table.'/thead/tr/th')->length);
        $this->assertSame(2, $xpath->query($table.'/tbody/tr')->length);
        $amounts = $xpath->query($table.'/tfoot/tr/td');
        $this->assertSame(6, $amounts->length);
        foreach (['₱567.00', '₱11.00', '₱356.00', '−₱8.00', '₱381.00', '₱392.00'] as $index => $amount) {
            $this->assertSame($amount, trim($amounts->item($index)->textContent));
            $this->assertStringContainsString('text-end', $amounts->item($index)->getAttribute('class'));
        }
        $this->assertSame($before, $transactions->toJson());
        $this->assertStringContainsString('Payout #10', $xpath->evaluate('string(//section[@id="finance-transactions"])'));
        $this->assertStringNotContainsString('Payout #10', $xpath->evaluate('string('.$table.')'));
        $this->assertStringContainsString('COD remitted', $xpath->evaluate('string('.$table.'/tbody)'));
    }

    public function test_totals_follow_filtered_rows_and_preserve_the_legacy_earnings_fallback(): void
    {
        $transactions = $this->transactions()->take(1);
        unset($transactions->first()->total_seller_earnings);
        $xpath = $this->xpath($this->renderFinance($transactions));
        $footer = '//section[@id="settlement-breakdown"]//tfoot/tr';

        $this->assertSame('Total · 1 order', trim($xpath->evaluate('string('.$footer.'/th)')));
        $amounts = $xpath->query($footer.'/td');
        foreach (['₱378.00', '₱0.00', '₱178.00', '−₱4.00', '₱196.00', '₱196.00'] as $index => $amount) {
            $this->assertSame($amount, trim($amounts->item($index)->textContent));
        }
    }

    public function test_no_order_earnings_leave_the_empty_state_and_zero_totals(): void
    {
        $xpath = $this->xpath($this->renderFinance($this->transactions()->where('type', '!=', 'Order earnings')));
        $table = '//section[@id="settlement-breakdown"]//table';

        $this->assertStringContainsString('No completed orders for this period yet.', $xpath->evaluate('string('.$table.'/tbody)'));
        $this->assertSame('Total · 0 orders', trim($xpath->evaluate('string('.$table.'/tfoot/tr/th)')));
        $amounts = $xpath->query($table.'/tfoot/tr/td');
        $this->assertSame(6, $amounts->length);
        foreach (['₱0.00', '₱0.00', '₱0.00', '−₱0.00', '₱0.00', '₱0.00'] as $index => $amount) {
            $this->assertSame($amount, trim($amounts->item($index)->textContent));
        }
    }

    private function transactions(): Collection
    {
        $defaults = ['date' => '2026-11-23', 'gross' => 0, 'commission' => 0, 'status' => 'available', 'commission_rate' => 2];

        return collect([
            (object) (['type' => 'Order earnings', 'reference' => 'Order #ONLINE-ORDER', 'fulfillment_type' => 'Delivery', 'payment_method' => 'GCash', 'online_collected' => 378, 'cash_to_seller' => 0, 'rider_delivery_fee' => 178, 'commission' => 4, 'net' => 196, 'total_seller_earnings' => 196] + $defaults),
            (object) (['type' => 'Order earnings', 'reference' => 'Order #COD-ORDER', 'fulfillment_type' => 'Delivery', 'payment_method' => 'COD', 'online_collected' => 189, 'cash_to_seller' => 11, 'rider_delivery_fee' => 178, 'commission' => 4, 'net' => 185, 'total_seller_earnings' => 196, 'cod_remittance_label' => 'COD remitted', 'status' => 'paid'] + $defaults),
            (object) (['type' => 'Seller payout', 'reference' => 'Payout #10', 'net' => -196, 'status' => 'requested'] + $defaults),
            (object) (['type' => 'Business expense', 'reference' => 'Ingredients', 'net' => -139] + $defaults),
        ]);
    }

    private function renderFinance(Collection $transactions): string
    {
        return view('seller.finance', [
            'settings' => [], 'from' => '2026-10-29', 'to' => '2026-11-27', 'period' => '', 'fulfillment' => '',
            'transactions' => $transactions, 'summary' => ['available' => 0],
            'gross' => 756, 'onlineCollections' => 567, 'cashCollections' => 189, 'cashToSeller' => 11,
            'totalSellerSales' => 392, 'netEarnings' => 381, 'riderCashFees' => 178, 'riderOnlineFees' => 178,
            'deliveryFund' => 356, 'commission' => 8, 'expenseTotal' => 139,
            'transactionTotals' => app(FinanceTransactionTotalsService::class)->summarize($transactions, collect(), true),
            'chartData' => ['labels' => [], 'gross' => [], 'net' => [], 'expenses' => []],
        ])->render();
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
