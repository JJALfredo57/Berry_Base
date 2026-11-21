<?php

namespace Tests\Feature;

use App\Services\SelectedPayoutService;
use App\Services\SellerBalanceService;
use App\Services\SellerPayoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SelectedSellerPayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('platform_settings', function ($t) {
            $t->increments('id');
            $t->string('payout_mode')->default('manual');
        });
        Schema::create('shops', function ($t) {
            $t->string('id')->primary();
            $t->string('seller_id');
            $t->decimal('commission_rate')->default(2);
            $t->boolean('commission_enabled')->default(true);
            $t->string('payout_method')->default('gcash');
            $t->string('payout_institution')->default('GCash');
            $t->string('payout_account_name')->default('Test Seller');
            $t->string('payout_account_number')->default('09123456789');
            $t->boolean('payout_details_verified')->default(true);
            $t->boolean('payout_paused')->default(false);
        });
        Schema::create('orders', function ($t) {
            $t->string('id')->primary();
            $t->string('shop_id');
            $t->string('status')->default('Pending');
            $t->string('payment_status')->default('Partial Payment');
            $t->string('payment_method')->default('COP');
            $t->string('fulfillment_type')->default('Pickup');
            $t->decimal('total_price')->default(1000);
            $t->decimal('delivery_fee')->default(0);
            $t->decimal('service_charge')->default(0);
            $t->string('deposit_status')->default('paid');
            $t->decimal('deposit_amount')->default(500);
            $t->string('cancel_status')->nullable();
        });
        Schema::create('seller_payout_ledgers', function ($t) {
            $t->increments('id');
            $t->string('order_id')->unique();
            $t->string('shop_id');
            $t->string('seller_id')->nullable();
            foreach (['gross_amount', 'platform_funded_discount', 'seller_funded_discount', 'cake_amount', 'delivery_fund_amount', 'commission_base', 'commission_rate', 'commission_amount', 'seller_net_amount', 'online_collected_amount', 'cash_collected_amount', 'cash_to_seller_amount', 'rider_cash_fee_amount', 'rider_online_fee_amount', 'total_seller_earnings', 'remaining_balance'] as $c) {
                $t->decimal($c, 12, 2)->default(0);
            }
            $t->string('status');
            $t->integer('allocation_version')->default(0);
            foreach (['online_funds_refunded', 'seller_refund_amount', 'commission_refund_amount'] as $field) {
                $t->decimal($field, 12, 2)->default(0);
            }
            $t->string('blocked_reason')->nullable();
            $t->string('source_order_id')->nullable();
            $t->timestamp('release_at')->nullable();
            $t->integer('payout_id')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_transactions', function ($t) {
            $t->increments('id');
            $t->string('order_id');
            $t->string('method')->default('GCash');
            $t->string('payment_status')->default('paid');
            $t->decimal('amount');
        });
        Schema::create('order_refunds', function ($t) {
            $t->increments('id');
            $t->string('order_id');
            $t->string('status');
            $t->decimal('refund_amount')->default(500);
        });
        Schema::create('rider_remittances', function ($t) {
            $t->increments('id');
            $t->string('order_id');
            $t->string('status');
            $t->decimal('customer_collected_amount')->default(550);
            $t->decimal('cake_remittance_amount')->default(450);
            $t->decimal('delivery_fee_retained')->default(100);
        });
        Schema::create('seller_payouts', function ($t) {
            $t->increments('id');
            foreach (['shop_id', 'seller_id', 'mode', 'status', 'payout_method', 'payout_institution', 'payout_account_name', 'payout_account_number'] as $c) {
                $t->string($c);
            }
            foreach (['gross_amount', 'commission_amount', 'net_amount'] as $c) {
                $t->decimal($c, 12, 2);
            }
            $t->text('admin_note')->nullable();
            $t->timestamp('requested_at');
            $t->timestamps();
        });
        Schema::create('seller_payout_items', function ($t) {
            $t->increments('id');
            $t->integer('payout_id');
            $t->integer('ledger_id');
            $t->string('order_id');
            $t->decimal('net_amount');
            $t->timestamps();
        });
        Schema::create('seller_balance_events', function ($t) {
            $t->increments('id');
            $t->string('shop_id');
            $t->string('order_id')->nullable();
            $t->integer('payout_id')->nullable();
            $t->string('type');
            $t->decimal('amount');
            $t->string('status');
            $t->text('note');
            $t->timestamp('created_at');
        });
        DB::table('shops')->insert(['id' => 'SHOP1', 'seller_id' => 'SELLER1']);
    }

    private function order(string $id, array $values = [], float $online = 500): object
    {
        DB::table('orders')->insert($values + ['id' => $id, 'shop_id' => 'SHOP1']);
        DB::table('payment_transactions')->insert(['order_id' => $id, 'amount' => $online]);
        app(SellerBalanceService::class)->syncOrder($id);

        return DB::table('seller_payout_ledgers')->where('order_id', $id)->first();
    }

    private function request(array $ids): ?int
    {
        return app(SellerPayoutService::class)->createPayoutForShop('SHOP1', 'manual', null, 'seller', 'seller', 'SELLER1', $ids);
    }

    public function test_verified_deposit_is_visible_but_not_selectable_until_completed(): void
    {
        $ledger = $this->order('PENDING');
        $this->assertSame('awaiting_completion', $ledger->status);
        $this->assertEquals(480, $ledger->seller_net_amount);
        $summary = app(SellerPayoutService::class)->summaryForShop('SHOP1');
        $this->assertEquals(480, $summary['net_online']);
        $this->assertEquals(0, $summary['available']);
        $this->expectException(\DomainException::class);
        $this->request([$ledger->id]);
    }

    public function test_completed_order_has_no_hold_or_minimum_and_duplicate_request_is_rejected(): void
    {
        $ledger = $this->order('PICKUP', ['status' => 'Picked Up', 'payment_status' => 'Paid']);
        $this->assertSame('available', $ledger->status);
        $this->assertNull($ledger->release_at);
        $id = $this->request([$ledger->id]);
        $this->assertNotNull($id);
        $this->assertEquals(480, DB::table('seller_payouts')->value('net_amount'));
        $this->expectException(\DomainException::class);
        $this->request([$ledger->id]);
    }

    public function test_only_selected_orders_are_reserved_and_other_sellers_cannot_be_selected(): void
    {
        $one = $this->order('ONE', ['status' => 'Picked Up', 'payment_status' => 'Paid']);
        $two = $this->order('TWO', ['status' => 'Picked Up', 'payment_status' => 'Paid']);
        $this->request([$one->id]);
        $this->assertSame('available', DB::table('seller_payout_ledgers')->where('id', $two->id)->value('status'));
        DB::table('shops')->insert(['id' => 'SHOP2', 'seller_id' => 'SELLER2']);
        $other = $this->order('OTHER', ['shop_id' => 'SHOP2', 'status' => 'Picked Up', 'payment_status' => 'Paid']);
        $this->expectException(\DomainException::class);
        $this->request([$other->id]);
    }

    public function test_cod_deposit_does_not_deduct_cash_rider_fee_and_requires_handover_confirmation(): void
    {
        $ledger = $this->order('COD', ['payment_method' => 'COD', 'fulfillment_type' => 'Delivery', 'total_price' => 1100, 'delivery_fee' => 100, 'deposit_amount' => 550], 550);
        $this->assertEquals(530, $ledger->seller_net_amount);
        $this->assertEquals(0, $ledger->rider_online_fee_amount);
        DB::table('orders')->where('id', 'COD')->update(['status' => 'Delivered', 'payment_status' => 'Paid']);
        app(SellerBalanceService::class)->syncOrder('COD');
        $this->assertSame('awaiting_completion', DB::table('seller_payout_ledgers')->value('status'));
        DB::table('rider_remittances')->insert(['order_id' => 'COD', 'status' => 'confirmed']);
        app(SellerBalanceService::class)->syncOrder('COD');
        $this->assertSame('available', DB::table('seller_payout_ledgers')->value('status'));
        $this->assertEquals(980, DB::table('seller_payout_ledgers')->value('total_seller_earnings'));
    }

    public function test_additional_payment_is_incremental_and_rate_is_frozen(): void
    {
        $this->order('ONLINE', ['payment_method' => 'GCash', 'total_price' => 1100, 'delivery_fee' => 100], 550);
        DB::table('shops')->update(['commission_rate' => 9]);
        DB::table('payment_transactions')->insert(['order_id' => 'ONLINE', 'amount' => 550]);
        app(SellerBalanceService::class)->syncOrder('ONLINE');
        app(SellerBalanceService::class)->syncOrder('ONLINE');
        $this->assertSame(1, DB::table('seller_payout_ledgers')->count());
        $this->assertEquals(980, DB::table('seller_payout_ledgers')->value('seller_net_amount'));
        $this->assertEquals(20, DB::table('seller_payout_ledgers')->value('commission_amount'));
    }

    public function test_refund_after_request_blocks_transfer_and_cancel_preserves_history(): void
    {
        $ledger = $this->order('REFUND', ['status' => 'Picked Up', 'payment_status' => 'Paid']);
        $id = $this->request([$ledger->id]);
        DB::table('order_refunds')->insert(['order_id' => 'REFUND', 'status' => 'pending_seller_approval']);
        try {
            DB::transaction(fn () => app(SelectedPayoutService::class)->assertTransferEligible($id));
            $this->fail('Transfer should be blocked.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('blocked', $e->getMessage());
        }
        app(SelectedPayoutService::class)->cancel($id, 'SHOP1');
        $this->assertSame('cancelled', DB::table('seller_payouts')->value('status'));
        $this->assertSame(1, DB::table('seller_payout_items')->count());
        $this->assertSame('refund_reserved', DB::table('seller_payout_ledgers')->value('status'));
        DB::table('order_refunds')->update(['status' => 'refunded']);
        app(SellerBalanceService::class)->syncOrder('REFUND');
        $this->assertEquals(0, DB::table('seller_payout_ledgers')->value('seller_net_amount'));
        $this->assertEquals(0, DB::table('seller_payout_ledgers')->value('commission_amount'));
    }

    public function test_recovery_is_not_bypassed_by_selecting_another_order(): void
    {
        $ledger = $this->order('RECOVER', ['status' => 'Picked Up', 'payment_status' => 'Paid']);
        DB::table('seller_payout_ledgers')->insert(['order_id' => 'OLD-RF', 'shop_id' => 'SHOP1', 'status' => 'available', 'seller_net_amount' => -100]);
        $this->request([$ledger->id]);
        $this->assertEquals(380, DB::table('seller_payouts')->value('net_amount'));
        $this->assertEquals(2, DB::table('seller_payout_items')->count());
    }

    public function test_paid_history_is_not_recalculated(): void
    {
        $ledger = $this->order('PAID', ['status' => 'Picked Up', 'payment_status' => 'Paid']);
        $id = $this->request([$ledger->id]);
        DB::table('seller_payouts')->where('id', $id)->update(['status' => 'paid']);
        DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update(['status' => 'paid']);
        DB::table('shops')->update(['commission_rate' => 20]);
        app(SellerBalanceService::class)->syncOrder('PAID');
        $this->assertEquals(480, DB::table('seller_payout_ledgers')->value('seller_net_amount'));
    }

    public function test_resolved_partial_refund_reverses_only_its_share_and_preserves_original_commission_basis(): void
    {
        $ledger = $this->order('PARTIAL', ['status' => 'Picked Up', 'payment_status' => 'Paid', 'payment_method' => 'GCash'], 1000);
        DB::table('order_refunds')->insert(['order_id' => 'PARTIAL', 'status' => 'refunded', 'refund_amount' => 250]);
        app(SellerBalanceService::class)->syncOrder('PARTIAL');
        $row = DB::table('seller_payout_ledgers')->where('id', $ledger->id)->first();
        $this->assertEquals(735, $row->seller_net_amount);
        $this->assertEquals(1000, $row->commission_base);
        $this->assertEquals(15, $row->commission_amount);
        $this->assertEquals(5, $row->commission_refund_amount);
        $this->assertEquals(245, $row->seller_refund_amount);
        $this->assertSame('available', $row->status);
        $id = $this->request([$row->id]);
        DB::transaction(fn () => app(SelectedPayoutService::class)->assertTransferEligible($id));
        $this->assertEquals(735, DB::table('seller_payouts')->value('net_amount'));
    }

    public function test_assigned_seller_rate_is_used_and_missing_verified_receipts_never_create_balance(): void
    {
        DB::table('shops')->update(['commission_rate' => 5]);
        $ledger = $this->order('FIVE');
        $this->assertEquals(50, $ledger->commission_amount);
        $this->assertEquals(450, $ledger->seller_net_amount);
        DB::table('orders')->insert(['id' => 'UNVERIFIED', 'shop_id' => 'SHOP1', 'payment_status' => 'Paid']);
        app(SellerBalanceService::class)->syncOrder('UNVERIFIED');
        $this->assertDatabaseMissing('seller_payout_ledgers', ['order_id' => 'UNVERIFIED']);
    }

    public function test_changed_payout_account_blocks_existing_transfer(): void
    {
        $ledger = $this->order('ACCOUNT', ['status' => 'Picked Up', 'payment_status' => 'Paid']);
        $id = $this->request([$ledger->id]);
        DB::table('shops')->update(['payout_account_number' => '09999999999']);
        $this->expectException(\DomainException::class);
        DB::transaction(fn () => app(SelectedPayoutService::class)->assertTransferEligible($id));
    }
}
