<?php

namespace Tests\Feature;

use App\Services\OrderHistoryPurgeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_purge_refuses_when_order_has_payout_history_and_deletes_nothing(): void
    {
        $this->createTables();
        Schema::create('seller_payout_ledgers', function (Blueprint $table) {
            $table->string('order_id');
        });
        DB::table('orders')->insert(['id' => 'ORDER1', 'track_code' => 'TRACK1']);
        DB::table('seller_payout_ledgers')->insert(['order_id' => 'ORDER1']);

        $purge = app(OrderHistoryPurgeService::class);
        $this->assertSame(1, $purge->preview()['financial_rows']['seller_payout_ledgers']);
        $this->expectException(RuntimeException::class);

        try {
            $purge->purge();
        } finally {
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('seller_payout_ledgers', 1);
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
}
