<?php

namespace Tests\Feature;

use App\Helpers\PaymentTransactionHelper;
use App\Services\OrderRefundService;
use App\Services\RiderEarningService;
use App\Services\RiderPayoutService;
use App\Services\SellerBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SellerPayoutJourneyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling([NotFoundHttpException::class]);
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (str_contains($file, 'add_upgrade_permit_to_seller_documents_enum')) {
                continue;
            }
            (require $file)->up();
        }
        foreach (['SELLER' => 'seller', 'BUYER' => 'customer', 'ADMIN' => 'superadmin'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'fullname' => 'Test '.$id, 'username' => strtolower($id), 'email' => strtolower($id).'@example.test',
                'phone' => '+639171234567', 'password' => bcrypt('Test12345!'), 'role' => $role, 'is_verified' => 1]);
        }
        DB::table('shops')->insert(['id' => 'SHOP', 'seller_id' => 'SELLER', 'shop_name' => 'Test Cake Shop', 'shop_slug' => 'test-cakes', 'status' => 'approved', 'tier' => 'verified',
            'commission_rate' => 2, 'commission_enabled' => 1, 'payout_method' => 'gcash', 'payout_institution' => 'GCash', 'payout_account_name' => 'Test Seller',
            'payout_account_number' => '09171234567', 'payout_details_verified' => 1, 'payout_paused' => 0]);
        DB::table('products')->insert(['id' => 'CAKE', 'shop_id' => 'SHOP', 'name' => 'Chocolate Cake', 'price' => 1000, 'classification' => 'Cake', 'is_available' => 1]);
    }

    private function order(string $id, string $status = 'Pending', string $method = 'COP', string $fulfillment = 'Pickup', float $online = 500): object
    {
        DB::table('orders')->insert(['id' => $id, 'shop_id' => 'SHOP', 'user_id' => 'BUYER', 'product_id' => 'CAKE', 'track_code' => 'TRACK'.$id,
            'status' => $status, 'payment_status' => in_array($status, ['Delivered', 'Picked Up']) ? 'Paid' : 'Partial Payment',
            'payment_method' => $method, 'fulfillment_type' => $fulfillment, 'total_price' => $fulfillment === 'Delivery' ? 1100 : 1000,
            'delivery_fee' => $fulfillment === 'Delivery' ? 100 : 0, 'deposit_status' => 'paid', 'deposit_amount' => $online, 'created_at' => now(), 'updated_at' => now()]);
        $order = DB::table('orders')->where('id', $id)->first();
        PaymentTransactionHelper::record($order, $online < 1000 ? 'downpayment_gcash' : 'full_gcash', 'GCash', $online, 'PAY-'.$id,
            ['service_fee' => 0, 'customer_paid_amount' => $online]);

        return DB::table('seller_payout_ledgers')->where('order_id', $id)->first();
    }

    private function seller(): static
    {
        return $this->withSession(['user' => ['id' => 'SELLER', 'fullname' => 'Test Seller', 'role' => 'seller']]);
    }

    public function test_commission_analytics_shows_commission_without_gross_sales(): void
    {
        $this->order('COMMISSIONONLY', 'Picked Up', 'GCash', 'Pickup', 1000);
        DB::table('orders')->where('id', 'COMMISSIONONLY')->update(['paid_at' => now()]);

        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $response = $admin->get(route('superadmin.commissions', ['period' => 'today']))->assertOk();

        $response->assertSee('Commission in period')
            ->assertSee('Verified commission')
            ->assertSee('Paid Orders')
            ->assertSee("label: 'Commission'", false)
            ->assertDontSee('Gross Sales')
            ->assertDontSee('grossSales');
        $this->assertSame(['labels', 'commission', 'orders'], array_keys($response->viewData('chartData')));
        $this->assertEquals(20, $response->viewData('totalCommission'));
        $this->assertEquals(1, $response->viewData('paidOrdersMonth'));
        $this->assertCount(1, $response->viewData('topShops'));
    }

    public function test_finance_breakdowns_link_every_metric_and_reconcile_with_overview(): void
    {
        $this->order('FINANCE123', 'Picked Up');
        DB::table('payment_transactions')->where('order_id', 'FINANCE123')
            ->update(['payment_service_fee' => 14.54, 'customer_paid_amount' => 514.54]);
        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $overview = $admin->get(route('superadmin.payouts', ['period' => 'today']))->assertOk();
        $metrics = [
            'customer-funds' => 500, 'customer-service-fee' => 14.54, 'customer-checkout-total' => 514.54,
            'refunds-sent' => 0, 'seller-in-process' => 480, 'awaiting-completion' => 0,
            'ready-payout' => 480, 'refund-reserved' => 0, 'transfer-pending' => 0,
            'paid-to-sellers' => 0, 'verified-commission' => 20, 'seller-awaiting-release' => 480,
            'rider-awaiting-release' => 0, 'reconciled-seller-paid' => 0, 'rider-fees-paid' => 0,
            'recorded-commission' => 20,
        ];
        foreach ($metrics as $metric => $expected) {
            $url = route('superadmin.payouts.breakdown', ['metric' => $metric, 'period' => 'today']);
            $overview->assertSee($url, false);
            $admin->get($url)->assertOk()->assertViewHas('report', fn ($report) => abs($report['total'] - $expected) < 0.001)
                ->assertSee('Total across all matching records')
                ->assertSee('Visible page subtotal (signed rows)')
                ->assertDontSee('Total for all matching records')
                ->assertSee('id="financeOrderModal"', false);
        }
        $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'customer-funds', 'period' => 'today', 'search' => 'NO-MATCH']))
            ->assertOk()->assertViewHas('report', fn ($report) => $report['total'] === 0.0);
        $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'customer-funds', 'from' => '2020-01-01', 'to' => '2020-01-31']))
            ->assertOk()->assertViewHas('report', fn ($report) => $report['total'] === 0.0);
        $this->seller()->get(route('superadmin.payouts.breakdown', ['metric' => 'customer-funds']))->assertRedirect();
        $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']])
            ->get(route('superadmin.payouts.breakdown', ['metric' => 'not-a-metric']))->assertNotFound();
    }

    public function test_finance_breakdown_rows_are_compact_and_modal_contains_cake_snapshots(): void
    {
        $this->order('CAKEDETAIL1', 'Picked Up');
        $this->order('LEGACYCAKE1', 'Picked Up');
        DB::table('order_items')->insert([
            'order_id' => 'CAKEDETAIL1', 'shop_id' => 'SHOP', 'product_id' => 'CAKE',
            'product_name' => 'Chocolate Celebration Cake', 'quantity' => 2,
            'selected_size' => '8 inches', 'final_unit_price_snapshot' => 245,
            'custom_note' => 'Happy birthday', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_addons')->insert([
            'order_id' => 'CAKEDETAIL1', 'addon_name' => 'Candles', 'addon_price' => 20,
            'created_at' => now(),
        ]);
        DB::table('custom_orders')->insert([
            'id' => 'CUSTOMCAKE1', 'order_id' => 'CAKEDETAIL1', 'shop_id' => 'SHOP',
            'cake_name' => 'Birthday design', 'flavor' => 'Strawberry', 'size' => '8 inches',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $response = $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'customer-funds', 'period' => 'today']))->assertOk();
        $response->assertSee('data-order-detail="fb-detail-', false)
            ->assertSee('document.body.appendChild(modal)', false)
            ->assertSee('<section class="fb-proof">', false)
            ->assertSee('<details class="fb-disclosure fb-cake-details">', false)
            ->assertSee('<details class="fb-disclosure fb-audit">', false)
            ->assertSee('Chocolate Celebration Cake × 2')
            ->assertSee('Size: 8 inches')
            ->assertSee('Cake amount: ₱490.00')
            ->assertSee('Custom cake specifications')
            ->assertSee('Flavor: Strawberry')
            ->assertSee('Candles')
            ->assertSee('Item-level price snapshot is unavailable for this older order')
            ->assertDontSee('<div class="fb-formula"', false)
            ->assertDontSee('Total for all matching records');
        $this->assertCount(2, $response->viewData('report')['cakeDetails']);
    }

    public function test_finance_modal_explains_the_selected_metric_before_full_audit(): void
    {
        $this->order('RIDERPROOF1', 'Delivered', 'GCash', 'Delivery', 1100);
        DB::table('rider_delivery_earnings')->insert([
            'rider_id' => 1, 'order_id' => 'RIDERPROOF1', 'amount' => 100,
            'source' => 'online_payment', 'status' => 'available', 'event_key' => 'rider-proof-1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);

        $rider = $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'rider-awaiting-release', 'period' => 'today']))->assertOk();
        $this->assertMatchesRegularExpression('/<section class="fb-proof">(.*?)<\/section>/s', $rider->getContent());
        preg_match('/<section class="fb-proof">(.*?)<\/section>/s', $rider->getContent(), $riderProof);
        $this->assertStringContainsString('Rider allocation', $riderProof[1]);
        $this->assertStringContainsString('Already paid to rider', $riderProof[1]);
        $this->assertStringContainsString('Rider earning', $riderProof[1]);
        $this->assertStringNotContainsString('Seller awaiting', $riderProof[1]);
        $this->assertStringNotContainsString('Platform funding', $riderProof[1]);
        $this->assertStringContainsString('Complete audit breakdown', $rider->getContent());
        preg_match('/<div class="fb-detail-grid">(.*?)<\/div><\/div>/s', $rider->getContent(), $riderAudit);
        $this->assertNotEmpty($riderAudit);
        $this->assertStringContainsString('Rider awaiting', $riderAudit[1]);
        $this->assertStringNotContainsString('Platform funding', $riderAudit[1]);
        $this->assertStringNotContainsString('Held funds', $riderAudit[1]);
        $this->assertStringNotContainsString('Difference', $riderAudit[1]);
        $this->assertStringNotContainsString('₱0.00', $riderAudit[1]);

        $checkout = $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'customer-checkout-total', 'period' => 'today']))->assertOk();
        preg_match('/<section class="fb-proof">(.*?)<\/section>/s', $checkout->getContent(), $checkoutProof);
        $this->assertStringContainsString('GCash payment plus the customer-paid service fee', $checkoutProof[1]);
        $this->assertStringContainsString('GCash payment', $checkoutProof[1]);
        $this->assertStringNotContainsString('Seller allocation', $checkoutProof[1]);
        preg_match('/<div class="fb-detail-grid">(.*?)<\/div><\/div>/s', $checkout->getContent(), $checkoutAudit);
        $this->assertNotEmpty($checkoutAudit);
        $this->assertStringNotContainsString('Customer-paid service fee', $checkoutAudit[1]);

        $commission = $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'recorded-commission', 'period' => 'today']))->assertOk();
        preg_match('/<section class="fb-proof">(.*?)<\/section>/s', $commission->getContent(), $commissionProof);
        $this->assertStringContainsString('Seller ledger commission', $commissionProof[1]);
        $this->assertStringNotContainsString('Rider awaiting', $commissionProof[1]);

        DB::table('seller_payout_ledgers')->where('order_id', 'RIDERPROOF1')->increment('commission_amount');
        $negativeDifference = $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'rider-awaiting-release', 'period' => 'today']))->assertOk();
        preg_match('/<div class="fb-detail-grid">(.*?)<\/div><\/div>/s', $negativeDifference->getContent(), $negativeAudit);
        $this->assertNotEmpty($negativeAudit);
        $this->assertStringContainsString('<span>Difference</span><strong>−₱1.00</strong>', $negativeAudit[1]);
    }

    public function test_finance_activity_tables_total_the_rows_shown_in_each_column(): void
    {
        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $tables = [
            'cash-history' => ['columns' => 6, 'label_span' => 5, 'amounts' => [5 => 1]],
            'payments' => ['columns' => 7, 'label_span' => 5, 'amounts' => [5 => 1]],
            'commission' => ['columns' => 8, 'label_span' => 3, 'amounts' => [3 => 1, 4 => 2, 5 => 3, 6 => 4, 7 => 5]],
        ];
        $assertTotals = function ($response, int $expectedRows) use ($tables): void {
            $document = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="UTF-8"?>'.$response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($document);

            foreach ($tables as $panel => $table) {
                $rows = $xpath->query("//*[@id='finance-panel-{$panel}']//tbody/tr[count(td) = {$table['columns']}]");
                $footer = $xpath->query("//*[@id='finance-panel-{$panel}']//tfoot/tr/*");
                $this->assertCount($expectedRows, $rows);
                $this->assertCount(count($table['amounts']) + ($panel === 'payments' ? 2 : 1), $footer);
                $this->assertSame((string) $table['label_span'], $footer->item(0)->getAttribute('colspan'));
                $this->assertSame('Total shown ('.$expectedRows.' '.($expectedRows === 1 ? 'row' : 'rows').')', trim($footer->item(0)->textContent));

                foreach ($table['amounts'] as $bodyColumn => $footerColumn) {
                    $sum = 0.0;
                    foreach ($rows as $row) {
                        $text = $row->getElementsByTagName('td')->item($bodyColumn)->textContent;
                        $sum += (float) str_replace([',', '₱', '−', ' '], ['', '', '-', ''], trim($text));
                    }
                    $this->assertStringContainsString('text-end', $footer->item($footerColumn)->getAttribute('class'));
                    $this->assertSame('₱'.number_format($sum, 2), trim($footer->item($footerColumn)->textContent));
                }
            }
        };

        $assertTotals($admin->get(route('superadmin.payouts', ['period' => 'today']))->assertOk(), 0);

        $this->order('TOTALROW1', 'Picked Up');
        PaymentTransactionHelper::recordFinalCashIfNeeded(DB::table('orders')->where('id', 'TOTALROW1')->first(), 'Picked Up');
        $assertTotals($admin->get(route('superadmin.payouts', ['period' => 'today']))->assertOk(), 1);

        $this->order('TOTALROW2', 'Picked Up', 'COP', 'Pickup', 600);
        PaymentTransactionHelper::recordFinalCashIfNeeded(DB::table('orders')->where('id', 'TOTALROW2')->first(), 'Picked Up');
        $assertTotals($admin->get(route('superadmin.payouts', ['period' => 'today']))->assertOk(), 2);
    }

    public function test_ready_payout_breakdown_shows_per_shop_recovery_netting(): void
    {
        $this->order('NETTING123', 'Picked Up');
        DB::table('seller_payout_ledgers')->insert([
            'order_id' => 'RECOVERY123', 'shop_id' => 'SHOP', 'seller_id' => 'SELLER',
            'source_order_id' => 'NETTING123',
            'status' => 'available', 'seller_net_amount' => -600, 'commission_amount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $overview = $admin->get(route('superadmin.payouts', ['period' => 'today']))->assertOk();
        $overview->assertViewHas('summary', fn ($summary) => $summary['available'] === 0.0);
        $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'ready-payout', 'period' => 'today']))
            ->assertOk()->assertViewHas('report', fn ($report) => $report['total'] === 0.0 && $report['nettingAdjustment'] === 120.0)
            ->assertSee('Per-shop zero-floor adjustment');
        $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'ready-payout', 'period' => 'today', 'search' => 'NETTING123']))
            ->assertOk()->assertViewHas('report', fn ($report) => $report['paginated']->total() === 2 && $report['total'] === 0.0);
    }

    public function test_refund_breakdown_uses_payment_cohort_and_traces_completed_refund(): void
    {
        $this->order('REFUNDCASE1', 'Picked Up');
        DB::table('order_refunds')->insert([
            'order_id' => 'REFUNDCASE1', 'shop_id' => 'SHOP', 'refund_amount' => 50,
            'status' => 'refunded', 'reference_number' => 'REF-50001',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $admin->get(route('superadmin.payouts', ['period' => 'today']))->assertOk()
            ->assertViewHas('paymentSummary', fn ($summary) => $summary['refunds_sent'] === 50.0);
        $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'refunds-sent', 'period' => 'today', 'search' => 'REF-50001']))
            ->assertOk()->assertViewHas('report', fn ($report) => $report['total'] === 50.0)
            ->assertSee('REF-50001')->assertSee('Linked source records');
        $admin->get(route('superadmin.payouts.breakdown', ['metric' => 'refunds-sent', 'period' => 'today', 'status' => 'Pending']))
            ->assertOk()->assertViewHas('report', fn ($report) => $report['total'] === 0.0);
    }

    public function test_awaiting_payout_labels_follow_pickup_and_delivery(): void
    {
        $pickup = $this->order('PICKUPWAIT', 'Pickup', 'GCash', 'Pickup');
        $this->order('DELIVERYWAIT', 'Out for Delivery', 'GCash', 'Delivery');
        DB::table('seller_payout_ledgers')->where('id', $pickup->id)
            ->update(['blocked_reason' => 'Awaiting Delivery/Pickup']);
        DB::table('seller_balance_events')->where('order_id', 'PICKUPWAIT')
            ->update(['note' => 'Awaiting Delivery/Pickup']);

        $this->seller()->get('/seller/payouts')->assertOk()
            ->assertSee('Waiting for Pickup')->assertSee('Waiting for Delivery')
            ->assertDontSee('Awaiting Delivery/Pickup');
        $this->assertDatabaseHas('seller_payout_ledgers', [
            'order_id' => 'DELIVERYWAIT', 'status' => 'awaiting_completion', 'blocked_reason' => 'Waiting for Delivery',
        ]);
    }

    public function test_paid_order_is_only_in_payout_history_not_order_selection(): void
    {
        $ledger = $this->order('PAIDORDER', 'Picked Up', 'GCash', 'Pickup', 1000);
        $this->seller()->post('/seller/payouts/request', ['ledger_ids' => [$ledger->id]])->assertSessionHas('msg');
        $payoutId = DB::table('seller_payouts')->value('id');
        DB::table('seller_payouts')->where('id', $payoutId)->update(['status' => 'paid']);
        DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update(['status' => 'paid']);

        $this->seller()->get('/seller/payouts')->assertOk()
            ->assertViewHas('ledgers', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('payouts', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $payoutId)
            ->assertSee('Payout #'.$payoutId)->assertSee('Order #PAIDORDER')
            ->assertSee('history_status=paid', false);
        $this->seller()->get('/seller/payouts?state=paid')->assertOk()
            ->assertViewHas('ledgers', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('payouts', fn ($rows) => $rows->total() === 1 && $rows->first()->status === 'paid');
    }

    public function test_seller_filters_selection_history_and_refund_receipts_work_together(): void
    {
        $ready = $this->order('READY', 'Picked Up');
        $pending = $this->order('PENDING');
        DB::table('order_items')->insert([
            ['order_id' => 'READY', 'product_name' => 'Chocolate Slice', 'quantity' => 1, 'final_unit_price_snapshot' => 400],
            ['order_id' => 'READY', 'product_name' => 'Berry Slice', 'quantity' => 1, 'final_unit_price_snapshot' => 600],
        ]);
        $response = $this->seller()->get('/seller/payouts');
        $response->assertOk()->assertSee('Ready to Request')->assertSee('Net Online Balance')->assertSee('Filter transactions')
            ->assertSee('Select all eligible orders on this page')->assertSee('Proportional per-item breakdown')->assertSee('₱192.00')->assertDontSee('Clearing');
        $this->seller()->get('/seller/payouts?state=available&search=Chocolate')->assertOk()
            ->assertViewHas('ledgers', fn ($rows) => $rows->total() === 1 && $rows->first()->order_id === 'READY');
        $this->seller()->post('/seller/payouts/request', ['ledger_ids' => [$pending->id]])->assertSessionHas('err');
        $this->assertDatabaseCount('seller_payouts', 0);
        $this->seller()->post('/seller/payouts/request', ['ledger_ids' => [$ready->id]])->assertSessionHas('msg');
        $id = DB::table('seller_payouts')->value('id');
        $this->seller()->get('/seller/payouts?state=transfer_pending')->assertOk()->assertSee('Payout #'.$id)
            ->assertSee('Order #READY')->assertSee('₱480.00');
        $this->seller()->post('/seller/payouts/request', ['ledger_ids' => [$ready->id]])->assertSessionHas('err');
        $this->seller()->post('/seller/payouts/'.$id.'/cancel')->assertSessionHas('msg');
        $this->assertDatabaseHas('seller_payouts', ['id' => $id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('seller_payout_items', 1);
        $refundId = DB::table('order_refunds')->insertGetId(['order_id' => 'READY', 'shop_id' => 'SHOP', 'track_code' => 'TRACKREADY',
            'refund_amount' => 500, 'payment_amount_paid' => 500, 'status' => 'refunded', 'reference_number' => 'REFUND-123', 'receipt_path' => 'https://receipt.test/refund.png',
            'created_at' => now(), 'updated_at' => now()]);
        Http::fake(['https://receipt.test/refund.png' => Http::response('refund-proof', 200, ['Content-Type' => 'image/png'])]);
        $this->seller()->get('/seller/payouts')->assertOk()->assertSee('REFUND-123')->assertSee('Download Refund Receipt');
        $this->seller()->get(route('seller.payouts.refund_receipt', ['kind' => 'cancellation', 'refundId' => $refundId]))->assertOk()->assertSee('refund-proof');
        DB::table('shops')->insert(['id' => 'OTHER', 'seller_id' => 'OTHERSELLER', 'shop_name' => 'Other Shop', 'shop_slug' => 'other-shop']);
        $this->withSession(['user' => ['id' => 'OTHERSELLER', 'role' => 'seller']])->get(route('seller.payouts.refund_receipt', ['kind' => 'cancellation', 'refundId' => $refundId]))->assertNotFound();
    }

    public function test_admin_sees_selected_order_proof_but_only_seller_can_cancel_a_blocked_request(): void
    {
        $ledger = $this->order('ADMINORDER', 'Picked Up');
        $this->seller()->post('/seller/payouts/request', ['ledger_ids' => [$ledger->id]])->assertSessionHas('msg');
        $id = DB::table('seller_payouts')->value('id');
        DB::table('orders')->where('id', 'ADMINORDER')->update(['cancel_status' => 'pending']);
        $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']])
            ->get(route('superadmin.payouts', ['search' => 'ADMINORDER', 'shop' => 'SHOP']))->assertOk()->assertSee('Blocked:')
            ->assertSee('Order allocations')->assertDontSee('Cancel & Reconcile')->assertDontSee('hold period');
        $this->post('/admin/payouts/'.$id.'/cancel', ['confirmed_untransferred' => '1'])->assertNotFound();
        $this->assertDatabaseHas('seller_payouts', ['id' => $id, 'status' => 'requested']);
        $this->seller()->post(route('seller.payouts.cancel', $id))->assertSessionHas('msg');
        $this->assertDatabaseHas('seller_payout_ledgers', ['id' => $ledger->id, 'status' => 'refund_reserved', 'payout_id' => null]);
        $this->assertDatabaseHas('seller_payouts', ['id' => $id, 'status' => 'cancelled']);
    }

    public function test_admin_seller_and_rider_pages_keep_action_dots_until_records_are_resolved(): void
    {
        $ledger = $this->order('SPLITPAGE', 'Picked Up');
        $this->seller()->post('/seller/payouts/request', ['ledger_ids' => [$ledger->id]])->assertSessionHas('msg');
        $riderId = DB::table('riders')->insertGetId(['shop_id' => 'SHOP', 'name' => 'Test Rider', 'phone' => '09170001111', 'gcash_name' => 'Test Rider', 'gcash_number' => '09170001111', 'created_at' => now(), 'updated_at' => now()]);
        $riderPayoutId = DB::table('rider_payout_requests')->insertGetId(['rider_id' => $riderId, 'amount' => 75, 'gcash_name' => 'Test Rider', 'gcash_number' => '09170001111', 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('rider_delivery_earnings')->insert(['rider_id' => $riderId, 'order_id' => 'SPLITPAGE', 'amount' => 75, 'source' => 'delivery', 'status' => 'requested', 'payout_id' => $riderPayoutId, 'event_key' => 'split-page-payout', 'created_at' => now(), 'updated_at' => now()]);
        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);

        $hub = $admin->get(route('superadmin.payouts'));
        $hub->assertOk();
        $this->assertTrue(str_contains($hub->getContent(), 'Seller Payouts') && str_contains($hub->getContent(), 'Rider Payouts') && ! str_contains($hub->getContent(), 'Cancel & Reconcile'), 'Finance hub separation');
        $hub->assertDontSee('Order Reconciliation Audit');
        $seller = $admin->get(route('superadmin.payouts.sellers', ['payout_status' => 'requested', 'shop' => 'SHOP']));
        $seller->assertOk()->assertViewHas('payoutAttention', fn ($counts) => $counts['seller_requests'] === 1 && $counts['rider_requests'] === 1);
        $this->assertTrue(str_contains($seller->getContent(), 'SPLITPAGE') && ! str_contains($seller->getContent(), 'Cancel & Reconcile'), 'Seller request page without cancel action');
        $seller->assertSee('data-payout-detail="seller-payout-', false)->assertSee('name="payout_receipt"', false)->assertSee('adminPayoutDetailDialog');
        $admin->get(route('superadmin.payouts.riders', ['status' => 'requested', 'rider' => $riderId]))
            ->assertOk()->assertSee('Test Rider')->assertSee('₱75.00')->assertSee('data-payout-detail="rider-payout-', false)
            ->assertSee('name="receipt"', false)->assertSee('adminPayoutDetailDialog')->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $admin->get(route('superadmin.payouts.riders', ['search' => 'no-such-rider']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 0);
        $admin->get(route('superadmin.payouts.riders', ['status' => 'recovery', 'rider' => $riderId]))
            ->assertOk()->assertDontSee('Needs recovery')->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $admin->post('/admin/rider-payouts/'.$riderId.'/recover')->assertNotFound();
        $payoutId = DB::table('seller_payouts')->value('id');
        $admin->post('/admin/payouts/'.$payoutId.'/cancel', ['confirmed_untransferred' => '1'])->assertNotFound();
        $this->seller()->post(route('seller.payouts.cancel', $payoutId))->assertSessionHas('msg');
        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $admin->get(route('superadmin.payouts'))->assertViewHas('payoutAttention', fn ($counts) => $counts['seller_requests'] === 0 && $counts['rider_requests'] === 1);
        DB::table('shops')->where('id', 'SHOP')->update(['payout_account_name' => null]);
        $admin->get(route('superadmin.payouts'))->assertViewHas('payoutAttention', fn ($counts) => $counts['seller_details'] === 1 && $counts['seller'] === 1);
    }

    public function test_admin_payout_history_and_rider_requests_keep_details_in_the_shared_dialog(): void
    {
        $ledger = $this->order('HISTORY', 'Picked Up');
        $sellerPayoutId = DB::table('seller_payouts')->insertGetId([
            'shop_id' => 'SHOP', 'seller_id' => 'SELLER', 'mode' => 'manual', 'status' => 'paid', 'net_amount' => 185,
            'payout_method' => 'gcash', 'payout_account_name' => 'Test Seller', 'payout_account_number' => '09171234567',
            'reference_number' => 'SELLER-REF-123', 'payout_receipt_path' => 'https://receipt.test/seller.png',
            'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('seller_payout_items')->insert([
            'payout_id' => $sellerPayoutId, 'ledger_id' => $ledger->id, 'order_id' => 'HISTORY',
            'net_amount' => 185, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $riderId = DB::table('riders')->insertGetId([
            'shop_id' => 'SHOP', 'name' => 'Test Rider', 'phone' => '09170001111',
            'gcash_name' => 'Test Rider', 'gcash_number' => '09170001111', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $riderPayoutId = DB::table('rider_payout_requests')->insertGetId([
            'rider_id' => $riderId, 'amount' => 75, 'gcash_name' => 'Test Rider', 'gcash_number' => '09170001111',
            'status' => 'paid', 'reference_number' => 'RIDER-REF-456', 'receipt_path' => 'https://receipt.test/rider.png',
            'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $admin = $this->withSession(['user' => ['id' => 'ADMIN', 'fullname' => 'Test Admin', 'role' => 'superadmin']]);
        $admin->get(route('superadmin.payouts.sellers', ['payout_status' => 'paid']))
            ->assertOk()->assertSee('data-payout-detail="seller-history-payout-'.$sellerPayoutId.'"', false)
            ->assertSee('SELLER-REF-123')->assertSee('#HISTORY')->assertSee('View Receipt')
            ->assertSee('payoutReceiptViewerModal')->assertSee(route('superadmin.payouts.receipt_download', $sellerPayoutId), false)
            ->assertDontSee('payout-history-admin-table');
        $admin->get(route('superadmin.payouts.riders', ['status' => 'paid', 'rider' => $riderId]))
            ->assertOk()->assertSee('Rider Payout History')
            ->assertSee('data-payout-detail="rider-payout-'.$riderPayoutId.'"', false)
            ->assertSee('RIDER-REF-456')->assertSee('View receipt')
            ->assertSee(route('superadmin.rider_payouts.receipt_download', $riderPayoutId), false);
    }

    public function test_refund_recovery_is_proportional_idempotent_and_never_rewrites_paid_history(): void
    {
        $ledger = $this->order('PAID', 'Picked Up', 'GCash', 'Pickup', 1000);
        DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update(['status' => 'paid']);
        $order = DB::table('orders')->where('id', 'PAID')->first();
        $service = app(OrderRefundService::class);
        $one = $service->createSellerRecovery($order, 500, 'RETURN1', 'Return #1');
        $retry = $service->createSellerRecovery($order, 500, 'RETURN1', 'Return #1');
        $two = $service->createSellerRecovery($order, 1000, 'RETURN2', 'Return #2');
        $this->assertSame($one, $retry);
        $this->assertEquals(-490, DB::table('seller_payout_ledgers')->where('id', $one)->value('seller_net_amount'));
        $this->assertEquals(-490, DB::table('seller_payout_ledgers')->where('id', $two)->value('seller_net_amount'));
        $this->assertEquals(980, DB::table('seller_payout_ledgers')->where('id', $ledger->id)->value('seller_net_amount'));
        $this->assertDatabaseCount('seller_balance_events', 3);
    }

    public function test_rider_online_allocation_is_not_duplicated_or_reopened_by_retries(): void
    {
        DB::table('riders')->insert(['id' => 1, 'name' => 'Test Rider', 'phone' => '+639171234560', 'is_active' => 1]);
        $this->order('ONLINE', 'Delivered', 'GCash', 'Delivery', 1100);
        DB::table('orders')->where('id', 'ONLINE')->update(['rider_id' => 1]);
        $online = DB::table('orders')->where('id', 'ONLINE')->first();
        app(RiderEarningService::class)->earn($online, 'online_payment');
        DB::table('rider_delivery_earnings')->where('order_id', 'ONLINE')->update(['status' => 'paid']);
        app(RiderEarningService::class)->earn($online, 'online_payment');
        $this->assertDatabaseCount('rider_delivery_earnings', 1);
        $this->assertDatabaseHas('rider_delivery_earnings', ['order_id' => 'ONLINE', 'amount' => 100, 'status' => 'paid']);
        $this->order('COD', 'Delivered', 'COD', 'Delivery', 550);
        DB::table('orders')->where('id', 'COD')->update(['rider_id' => 1]);
        app(RiderEarningService::class)->earn(DB::table('orders')->where('id', 'COD')->first(), 'online_payment');
        $this->assertDatabaseCount('rider_delivery_earnings', 1);
    }

    public function test_signed_remaining_payment_webhook_is_incremental_and_idempotent(): void
    {
        $this->order('WEBHOOK');
        DB::table('orders')->where('id', 'WEBHOOK')->update(['paymongo_link_id' => 'cs_remaining']);
        config(['paymongo.webhook_secret' => 'test-only-secret']);
        $payload = json_encode(['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'data' => [
            'id' => 'cs_remaining', 'attributes' => ['payments' => [['attributes' => ['status' => 'paid', 'amount' => 50000, 'net_amount' => 50000, 'fee' => 0]]]],
        ]]]]);
        $timestamp = time();
        $signature = 't='.$timestamp.',te='.hash_hmac('sha256', $timestamp.'.'.$payload, 'test-only-secret');
        $this->call('POST', route('paymongo.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $signature], $payload)->assertOk();
        $this->call('POST', route('paymongo.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $signature], $payload)->assertOk();
        $this->assertEquals(1000, DB::table('payment_transactions')->where('order_id', 'WEBHOOK')->sum('amount'));
        $this->assertDatabaseCount('payment_transactions', 2);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => 'WEBHOOK', 'type' => 'remaining_gcash', 'amount' => 500]);
        $this->assertDatabaseHas('seller_payout_ledgers', ['order_id' => 'WEBHOOK', 'seller_net_amount' => 980, 'commission_amount' => 20]);
    }

    public function test_existing_platform_funded_discount_remains_a_separate_seller_credit(): void
    {
        $this->order('VOUCHER', 'Picked Up', 'GCash', 'Pickup', 900);
        DB::table('orders')->where('id', 'VOUCHER')->update(['total_price' => 900]);
        DB::table('order_discounts')->insert(['order_id' => 'VOUCHER', 'source_type' => 'voucher', 'funding_source' => 'platform', 'amount' => 100]);
        app(SellerBalanceService::class)->syncOrder('VOUCHER');
        $this->assertDatabaseHas('seller_payout_ledgers', ['order_id' => 'VOUCHER', 'platform_funded_discount' => 100,
            'cake_amount' => 1000, 'commission_amount' => 20, 'seller_net_amount' => 980, 'online_collected_amount' => 900]);
    }

    public function test_rider_transfer_only_uses_its_linked_earnings(): void
    {
        DB::table('riders')->insert(['id' => 1, 'name' => 'Test Rider', 'is_active' => 1]);
        $ids = [];
        foreach ([100, 50] as $amount) {
            $id = DB::table('rider_payout_requests')->insertGetId(['rider_id' => 1, 'amount' => $amount, 'gcash_name' => 'Test Rider', 'gcash_number' => '09171234567', 'status' => 'requested']);
            DB::table('rider_delivery_earnings')->insert(['rider_id' => 1, 'order_id' => 'RIDER'.$id, 'event_key' => 'E'.$id, 'amount' => $amount, 'source' => 'online_payment', 'status' => 'requested', 'payout_id' => $id]);
            $ids[] = $id;
        }
        $request = DB::table('rider_payout_requests')->where('id', $ids[0])->first();
        $rows = DB::transaction(fn () => app(RiderPayoutService::class)->allocations($request));
        $this->assertCount(1, $rows);
        $this->assertEquals(100, $rows->sum('amount'));
        $this->assertSame($ids[0], $rows->first()->payout_id);
    }

    public function test_rider_request_links_exact_earnings_and_rejects_duplicate_requests(): void
    {
        $riderId = DB::table('riders')->insertGetId(['shop_id' => 'SHOP', 'name' => 'Payout Rider', 'phone' => '09170003333', 'is_active' => 1]);
        foreach ([100, 50] as $amount) {
            DB::table('rider_delivery_earnings')->insert(['rider_id' => $riderId, 'order_id' => 'RIDER'.$amount,
                'event_key' => 'request-'.$amount, 'amount' => $amount, 'source' => 'delivery', 'status' => 'available']);
        }
        DB::table('users')->where('id', 'ADMIN')->update(['phone' => null]);

        $request = ['gcash_name' => 'Payout Rider', 'gcash_number' => '09170003333', 'confirmation' => '1'];
        $this->withSession(['rider' => ['id' => $riderId]])->post(route('rider.payouts.request'), $request)->assertSessionHas('msg');
        $payout = DB::table('rider_payout_requests')->where('rider_id', $riderId)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(150, $payout->amount);
        $this->assertSame(2, DB::table('rider_delivery_earnings')->where('rider_id', $riderId)
            ->where('status', 'requested')->where('payout_id', $payout->id)->count());

        DB::table('rider_delivery_earnings')->insert(['rider_id' => $riderId, 'order_id' => 'RIDER25',
            'event_key' => 'request-25', 'amount' => 25, 'source' => 'delivery', 'status' => 'available']);
        $this->withSession(['rider' => ['id' => $riderId]])->post(route('rider.payouts.request'), $request)->assertSessionHas('err');
        $this->assertSame(1, DB::table('rider_payout_requests')->where('rider_id', $riderId)->count());
        $this->assertDatabaseHas('rider_delivery_earnings', ['event_key' => 'request-25', 'status' => 'available', 'payout_id' => null]);
    }

    public function test_rider_transfer_rejects_missing_or_mismatched_allocations(): void
    {
        DB::table('riders')->insert(['id' => 1, 'name' => 'Test Rider', 'is_active' => 1]);
        $id = DB::table('rider_payout_requests')->insertGetId(['rider_id' => 1, 'amount' => 100,
            'gcash_name' => 'Test Rider', 'gcash_number' => '09171234567', 'status' => 'requested']);
        try {
            DB::transaction(fn () => app(RiderPayoutService::class)->allocations(DB::table('rider_payout_requests')->where('id', $id)->first()));
            $this->fail('A payout without earnings must not be transferable.');
        } catch (\DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        DB::table('rider_delivery_earnings')->insert(['rider_id' => 1, 'order_id' => 'MISMATCH',
            'event_key' => 'mismatch', 'amount' => 90, 'source' => 'delivery', 'status' => 'requested', 'payout_id' => $id]);
        try {
            DB::transaction(fn () => app(RiderPayoutService::class)->allocations(DB::table('rider_payout_requests')->where('id', $id)->first()));
            $this->fail('A mismatched payout amount must not be transferable.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('do not match', $e->getMessage());
        }
        $this->assertDatabaseHas('rider_payout_requests', ['id' => $id, 'status' => 'requested']);
    }
}
