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
        $this->assertTrue(str_contains($hub->getContent(), 'Seller Payouts') && str_contains($hub->getContent(), 'Rider Payouts') && !str_contains($hub->getContent(), 'Cancel & Reconcile'), 'Finance hub separation');
        $seller = $admin->get(route('superadmin.payouts.sellers', ['payout_status' => 'requested', 'shop' => 'SHOP']));
        $seller->assertOk()->assertViewHas('payoutAttention', fn ($counts) => $counts['seller_requests'] === 1 && $counts['rider_requests'] === 1);
        $this->assertTrue(str_contains($seller->getContent(), 'SPLITPAGE') && !str_contains($seller->getContent(), 'Cancel & Reconcile'), 'Seller request page without cancel action');
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
