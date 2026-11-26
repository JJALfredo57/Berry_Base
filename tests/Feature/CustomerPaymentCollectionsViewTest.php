<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CustomerPaymentCollectionsViewTest extends TestCase
{
    public function test_collection_totals_align_with_each_amount_column_and_explain_the_gross_total(): void
    {
        $html = $this->renderCollections($this->settlements());
        $xpath = $this->xpath($html);
        $footer = '(//table)[1]/tfoot/tr';

        $this->assertSame('3', $xpath->evaluate('string('.$footer.'/th/@colspan)'));
        $this->assertSame('row', $xpath->evaluate('string('.$footer.'/th/@scope)'));
        $this->assertSame('Total · 2 completed orders', trim($xpath->evaluate('string('.$footer.'/th)')));
        $this->assertSame(8, $xpath->query('(//table)[1]/thead/tr/th')->length);
        $this->assertSame(5, $xpath->query($footer.'/td')->length);
        foreach (['₱567.00', '₱189.00', '₱356.00', '₱11.00', '₱381.00'] as $index => $amount) {
            $this->assertSame($amount, trim($xpath->evaluate('string('.$footer.'/td['.($index + 1).'])')));
            $this->assertStringContainsString('text-end', $xpath->evaluate('string('.$footer.'/td['.($index + 1).']/@class)'));
        }
        $text = $xpath->evaluate('string((//table)[1]/ancestor::section/p)');
        $this->assertStringContainsString('₱567.00 online + ₱189.00 cash = ₱756.00 total customer payments.', $text);
        $this->assertStringContainsString('do not add the rider fee again', $text);
        $this->assertStringContainsString("not the seller's payout balance", $text);
        $this->assertStringContainsString('₱578.00 receipts', $html);
        $this->assertSame(3, $xpath->query('(//table)[2]/tbody/tr')->length);
        $this->assertStringContainsString('₱578.00', $xpath->evaluate('string((//table)[2]/tfoot)'));
    }

    public function test_totals_use_only_the_filtered_collection_rows(): void
    {
        $xpath = $this->xpath($this->renderCollections($this->settlements()->take(1)));

        $this->assertSame('Total · 1 completed order', trim($xpath->evaluate('string((//table)[1]/tfoot/tr/th)')));
        $amounts = $xpath->query('(//table)[1]/tfoot/tr/td');
        foreach (['₱378.00', '₱0.00', '₱178.00', '₱0.00', '₱196.00'] as $index => $amount) {
            $this->assertSame($amount, trim($amounts->item($index)->textContent));
        }
        $this->assertStringContainsString('₱378.00 online + ₱0.00 cash = ₱378.00 total customer payments.', $xpath->evaluate('string((//table)[1]/ancestor::section/p)'));
    }

    public function test_empty_collections_show_zero_totals_and_keep_the_empty_state(): void
    {
        $html = $this->renderCollections(collect());
        $xpath = $this->xpath($html);

        $this->assertStringContainsString('No completed collections match these filters.', $html);
        $this->assertSame('Total · 0 completed orders', trim($xpath->evaluate('string((//table)[1]/tfoot/tr/th)')));
        $amounts = $xpath->query('(//table)[1]/tfoot/tr/td');
        $this->assertSame(5, $amounts->length);
        foreach ($amounts as $amount) {
            $this->assertSame('₱0.00', trim($amount->textContent));
        }
        $this->assertStringContainsString('₱0.00 online + ₱0.00 cash = ₱0.00 total customer payments.', $xpath->evaluate('string((//table)[1]/ancestor::section/p)'));
    }

    private function settlements(): Collection
    {
        return collect([
            (object) ['order_id' => 'ONLINE-ORDER', 'created_at' => '2026-11-23 10:49:00', 'payment_method' => 'GCash', 'fulfillment_type' => 'Delivery', 'online_collected_amount' => 378, 'cash_collected_amount' => 0, 'rider_cash_fee_amount' => 0, 'rider_online_fee_amount' => 178, 'cash_to_seller_amount' => 0, 'seller_net_amount' => 196],
            (object) ['order_id' => 'COD-ORDER', 'created_at' => '2026-11-22 19:22:00', 'payment_method' => 'COD', 'fulfillment_type' => 'Delivery', 'online_collected_amount' => 189, 'cash_collected_amount' => 189, 'rider_cash_fee_amount' => 178, 'rider_online_fee_amount' => 0, 'cash_to_seller_amount' => 11, 'seller_net_amount' => 185],
        ]);
    }

    private function renderCollections(Collection $settlements): string
    {
        $payments = collect([
            (object) ['order_id' => 'ONLINE-ORDER', 'paid_at' => '2026-11-23 10:49:00', 'label' => 'GCash Full Payment', 'method' => 'GCash', 'fulfillment_type' => 'Delivery', 'remaining_balance' => 0, 'amount' => 378],
            (object) ['order_id' => 'COD-ORDER', 'paid_at' => '2026-11-22 19:22:00', 'label' => 'Cash Delivery Balance', 'method' => 'Cash', 'fulfillment_type' => 'Delivery', 'remaining_balance' => 0, 'amount' => 11],
            (object) ['order_id' => 'COD-ORDER', 'paid_at' => '2026-11-22 10:10:00', 'label' => 'GCash Downpayment', 'method' => 'GCash', 'fulfillment_type' => 'Delivery', 'remaining_balance' => 189, 'amount' => 189],
        ]);

        return view('seller.finance_customer_payments', [
            'settings' => [], 'from' => '2026-10-29', 'to' => '2026-11-27',
            'method' => '', 'fulfillment' => '', 'paymentType' => '',
            'settlements' => $settlements, 'payments' => $payments, 'total' => (float) $payments->sum('amount'),
            'collectionTotal' => (float) $settlements->sum(fn ($row) => (float) $row->online_collected_amount + (float) $row->cash_collected_amount),
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
