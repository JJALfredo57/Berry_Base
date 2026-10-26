<?php

namespace Tests\Feature;

use App\Services\OrderHistoryPurgeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class OrderHistoryPurgeServiceTest extends TestCase
{
    public function test_purge_removes_order_records_but_keeps_accounts_products_and_unconverted_requests(): void
    {
        $this->createTables();
        DB::table('users')->insert(['id' => 'USER1']);
        DB::table('products')->insert(['id' => 'CAKE1']);
        DB::table('orders')->insert(['id' => 'ORDER1', 'track_code' => 'TRACK1']);
        DB::table('order_addons')->insert(['order_id' => 'ORDER1']);
        DB::table('custom_orders')->insert(['id' => 'CUSTOM1', 'order_id' => 'ORDER1']);
        DB::table('order_requests')->insert([
            ['id' => 'REQ1', 'converted_order_id' => 'ORDER1', 'custom_order_id' => 'CUSTOM1'],
            ['id' => 'REQ2', 'converted_order_id' => null, 'custom_order_id' => null],
        ]);
        DB::table('messages')->insert(['id' => 1, 'order_id' => 'ORDER1']);
        DB::table('message_reactions')->insert(['message_id' => 1]);
        DB::table('mobile_notifications')->insert(['id' => 1, 'order_id' => 'ORDER1']);
        DB::table('customer_payment_qrs')->insert(['id' => 1, 'track_code' => 'TRACK1']);
        DB::table('product_daily_orders')->insert(['product_id' => 'CAKE1', 'date' => '2026-10-27', 'total_ordered' => 1]);

        $deleted = app(OrderHistoryPurgeService::class)->purge();

        $this->assertSame(1, $deleted['orders']);
        $this->assertSame(1, $deleted['order_addons']);
        $this->assertSame(1, $deleted['custom_orders']);
        $this->assertSame(1, $deleted['order_requests']);
        $this->assertSame(1, $deleted['message_reactions']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_addons', 0);
        $this->assertDatabaseCount('custom_orders', 0);
        $this->assertDatabaseCount('order_requests', 1);
        $this->assertDatabaseCount('message_reactions', 0);
        $this->assertDatabaseCount('mobile_notifications', 0);
        $this->assertDatabaseCount('customer_payment_qrs', 0);
        $this->assertDatabaseCount('product_daily_orders', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_purge_removes_financial_records_linked_to_orders_and_refunds(): void
    {
        $this->createTables();
        $this->createFinancialTables();
        Storage::fake(config('filesystems.upload_disk', 'public'));
        Storage::fake('public');
        foreach ([
            'uploads/payout_receipts/payout.jpg',
            'uploads/refund_receipts/refund.jpg',
            'uploads/rider_remittances/remittance.jpg',
        ] as $path) {
            Storage::disk('public')->put($path, 'receipt');
        }
        DB::table('users')->insert(['id' => 'USER1']);
        DB::table('products')->insert(['id' => 'CAKE1']);
        DB::table('orders')->insert(['id' => 'ORDER1', 'track_code' => 'TRACK1']);
        DB::table('seller_payouts')->insert([
            ['id' => 1, 'payout_receipt_path' => Storage::disk('public')->url('uploads/payout_receipts/payout.jpg')],
            ['id' => 2, 'payout_receipt_path' => null],
        ]);
        DB::table('seller_payout_ledgers')->insert([
            ['id' => 1, 'order_id' => 'ORDER1', 'payout_id' => 1],
            ['id' => 2, 'order_id' => 'ORDER1-RF1', 'payout_id' => 2],
        ]);
        DB::table('seller_payout_items')->insert([
            ['id' => 1, 'payout_id' => 1, 'ledger_id' => 1, 'order_id' => 'ORDER1'],
            ['id' => 2, 'payout_id' => 2, 'ledger_id' => 2, 'order_id' => 'ORDER1-RF1'],
        ]);
        DB::table('order_refunds')->insert([
            [
                'id' => 1,
                'order_id' => 'ORDER1',
                'payout_ledger_id' => 2,
                'receipt_path' => Storage::disk('public')->url('uploads/refund_receipts/refund.jpg'),
            ],
        ]);
        DB::table('rider_remittance_batches')->insert(['id' => 1]);
        DB::table('rider_remittances')->insert([
            [
                'id' => 1,
                'order_id' => 'ORDER1',
                'batch_id' => 1,
                'receipt_path' => Storage::disk('public')->url('uploads/rider_remittances/remittance.jpg'),
            ],
        ]);

        $deleted = app(OrderHistoryPurgeService::class)->purge();

        $this->assertSame(2, $deleted['seller_payout_ledgers']);
        $this->assertSame(2, $deleted['seller_payout_items']);
        $this->assertSame(2, $deleted['seller_payouts']);
        $this->assertSame(1, $deleted['rider_remittances']);
        $this->assertSame(1, $deleted['rider_remittance_batches']);
        $this->assertSame(1, $deleted['order_refunds']);
        $this->assertSame(3, $deleted['uploaded_receipt_files']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('seller_payout_ledgers', 0);
        $this->assertDatabaseCount('seller_payout_items', 0);
        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseCount('rider_remittances', 0);
        $this->assertDatabaseCount('rider_remittance_batches', 0);
        $this->assertDatabaseCount('order_refunds', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('products', 1);
        $this->assertFalse(Storage::disk('public')->exists('uploads/payout_receipts/payout.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('uploads/refund_receipts/refund.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('uploads/rider_remittances/remittance.jpg'));
    }

    public function test_purge_refuses_when_payout_group_contains_records_outside_scope(): void
    {
        $this->createTables();
        $this->createFinancialTables();
        DB::table('orders')->insert(['id' => 'ORDER1', 'track_code' => 'TRACK1']);
        DB::table('seller_payouts')->insert(['id' => 1]);
        DB::table('seller_payout_ledgers')->insert([
            ['id' => 1, 'order_id' => 'ORDER1', 'payout_id' => 1],
            ['id' => 2, 'order_id' => 'ORDER2', 'payout_id' => 1],
        ]);
        DB::table('seller_payout_items')->insert([
            ['id' => 1, 'payout_id' => 1, 'ledger_id' => 1, 'order_id' => 'ORDER1'],
            ['id' => 2, 'payout_id' => 1, 'ledger_id' => 2, 'order_id' => 'ORDER2'],
        ]);

        $purge = app(OrderHistoryPurgeService::class);
        $this->assertSame(
            1,
            $purge->preview()['financial_blockers']['seller_payouts_shared_with_unscoped_records']
        );
        $this->expectException(RuntimeException::class);

        try {
            $purge->purge();
        } finally {
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('seller_payout_ledgers', 2);
            $this->assertDatabaseCount('seller_payout_items', 2);
            $this->assertDatabaseCount('seller_payouts', 1);
        }
    }

    public function test_purge_refuses_when_the_reviewed_snapshot_is_stale(): void
    {
        $this->createTables();
        DB::table('orders')->insert(['id' => 'ORDER1', 'track_code' => 'TRACK1']);
        $purge = app(OrderHistoryPurgeService::class);
        $snapshot = $purge->preview()['snapshot'];
        DB::table('orders')->insert(['id' => 'ORDER2', 'track_code' => 'TRACK2']);

        $this->expectException(RuntimeException::class);

        try {
            $purge->purge($snapshot);
        } finally {
            $this->assertDatabaseCount('orders', 2);
        }
    }

    public function test_purge_refuses_when_a_financial_file_is_outside_allowed_receipt_locations(): void
    {
        $this->createTables();
        $this->createFinancialTables();
        DB::table('orders')->insert(['id' => 'ORDER1', 'track_code' => 'TRACK1']);
        DB::table('seller_payouts')->insert([
            'id' => 1,
            'payout_receipt_path' => 'https://files.example.test/uploads/products/product.jpg',
        ]);
        DB::table('seller_payout_ledgers')->insert([
            'id' => 1,
            'order_id' => 'ORDER1',
            'payout_id' => 1,
        ]);

        $purge = app(OrderHistoryPurgeService::class);
        $this->assertSame(1, $purge->preview()['financial_blockers']['unmanaged_receipt_files']);
        $this->expectException(RuntimeException::class);

        try {
            $purge->purge();
        } finally {
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('seller_payouts', 1);
        }
    }

    private function createTables(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('track_code')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
        });
        Schema::create('order_addons', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
        });
        Schema::create('custom_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id')->nullable();
        });
        Schema::create('order_requests', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('converted_order_id')->nullable();
            $table->string('custom_order_id')->nullable();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
        });
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
        });
        Schema::create('mobile_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
        });
        Schema::create('customer_payment_qrs', function (Blueprint $table) {
            $table->id();
            $table->string('track_code')->nullable();
        });
        Schema::create('product_daily_orders', function (Blueprint $table) {
            $table->string('product_id');
            $table->date('date');
            $table->integer('total_ordered');
        });
    }

    private function createFinancialTables(): void
    {
        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('payout_receipt_path')->nullable();
        });
        Schema::create('seller_payout_ledgers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('order_id');
            $table->unsignedBigInteger('payout_id')->nullable();
        });
        Schema::create('seller_payout_items', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('payout_id');
            $table->unsignedBigInteger('ledger_id');
            $table->string('order_id');
        });
        Schema::create('rider_remittance_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
        });
        Schema::create('rider_remittances', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('order_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('receipt_path')->nullable();
        });
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('order_id');
            $table->unsignedBigInteger('payout_ledger_id')->nullable();
            $table->string('receipt_path')->nullable();
        });
    }
}
