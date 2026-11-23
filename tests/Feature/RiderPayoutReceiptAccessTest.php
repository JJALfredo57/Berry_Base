<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiderPayoutReceiptAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('riders')) {
            Schema::create('riders', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('phone')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }
        if (! Schema::hasTable('rider_payout_requests')) {
            Schema::create('rider_payout_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('rider_id');
                $table->decimal('amount', 12, 2);
                $table->string('gcash_name');
                $table->string('gcash_number');
                $table->string('status');
                $table->string('reference_number')->nullable();
                $table->string('receipt_path')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_paid_rider_can_access_only_their_own_receipt(): void
    {
        $riderId = 990001;
        $otherRiderId = 990002;
        DB::table('riders')->updateOrInsert(['id' => $riderId], ['name' => 'Receipt Rider', 'is_active' => true]);
        DB::table('riders')->updateOrInsert(['id' => $otherRiderId], ['name' => 'Other Rider', 'is_active' => true]);
        $payoutId = DB::table('rider_payout_requests')->insertGetId([
            'rider_id' => $riderId,
            'amount' => 306,
            'gcash_name' => 'Receipt Rider',
            'gcash_number' => '+639171234567',
            'status' => 'paid',
            'reference_number' => 'RIDER-306',
            'receipt_path' => 'https://receipt.test/rider-306.png',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake(['https://receipt.test/*' => Http::response('receipt-image', 200, ['Content-Type' => 'image/png'])]);

        $this->withSession(['rider' => ['id' => $riderId]])
            ->getJson(route('rider.payouts.receipts'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $payoutId)
            ->assertJsonPath('data.0.receipt_url', route('rider.payouts.receipt', $payoutId));

        $this->withSession(['rider' => ['id' => $riderId]])
            ->get(route('rider.payouts.receipt', $payoutId))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->withSession(['rider' => ['id' => $otherRiderId]])
            ->get(route('rider.payouts.receipt', $payoutId))
            ->assertNotFound();
    }

    public function test_transaction_history_filters_keep_rider_records_and_paid_receipt_links(): void
    {
        if (! Schema::hasTable('rider_remittances')) {
            Schema::create('rider_remittances', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('rider_id');
                $table->string('order_id');
                $table->string('shop_id');
                $table->decimal('amount', 12, 2);
                $table->string('status');
                $table->string('reference_number')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('rider_remittance_batches')) {
            Schema::create('rider_remittance_batches', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('rider_id');
                $table->string('shop_id');
                $table->decimal('total_amount', 12, 2);
                $table->unsignedInteger('order_count');
                $table->string('status');
                $table->string('reference_number')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('paymongo_paid_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        $riderId = 990011;
        $otherRiderId = 990012;
        DB::table('riders')->insert([
            ['id' => $riderId, 'name' => 'History Rider', 'is_active' => true],
            ['id' => $otherRiderId, 'name' => 'Other Rider', 'is_active' => true],
        ]);
        $paidId = DB::table('rider_payout_requests')->insertGetId([
            'rider_id' => $riderId, 'amount' => 178, 'gcash_name' => 'History Rider',
            'gcash_number' => '+639171234567', 'status' => 'paid', 'reference_number' => 'PAID-178',
            'receipt_path' => 'https://receipt.test/paid.png', 'paid_at' => now()->subDay(),
            'created_at' => now()->subDays(2), 'updated_at' => now(),
        ]);
        DB::table('rider_payout_requests')->insert([
            'rider_id' => $riderId, 'amount' => 25, 'gcash_name' => 'History Rider',
            'gcash_number' => '+639171234567', 'status' => 'requested',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rider_payout_requests')->insert([
            'rider_id' => $otherRiderId, 'amount' => 900, 'gcash_name' => 'Other Rider',
            'gcash_number' => '+639171234567', 'status' => 'paid',
            'receipt_path' => 'https://receipt.test/other.png', 'paid_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rider_remittances')->insert([
            'rider_id' => $riderId, 'order_id' => 'HIST001', 'shop_id' => 'TESTSHOP',
            'amount' => 11, 'status' => 'confirmed', 'reference_number' => 'COD-11',
            'confirmed_at' => now()->subHours(2), 'created_at' => now()->subDay(), 'updated_at' => now(),
        ]);
        DB::table('rider_remittance_batches')->insert([
            'rider_id' => $riderId, 'shop_id' => 'TESTSHOP', 'total_amount' => 35,
            'order_count' => 2, 'status' => 'confirmed', 'reference_number' => 'BATCH-35',
            'confirmed_at' => now()->subHours(3), 'created_at' => now()->subDay(), 'updated_at' => now(),
        ]);

        $rider = $this->withSession(['rider' => ['id' => $riderId]]);
        $rider->getJson(route('rider.payouts.receipts', ['filter' => 'all']))
            ->assertOk()->assertJsonPath('pagination.total', 4)
            ->assertJsonPath('data.0.kind', 'payout')
            ->assertJsonPath('data.0.receipt_url', null);
        $rider->getJson(route('rider.payouts.receipts', ['filter' => 'payout']))
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.1.id', $paidId)
            ->assertJsonPath('data.1.receipt_url', route('rider.payouts.receipt', $paidId));
        $rider->getJson(route('rider.payouts.receipts', ['filter' => 'remittance']))
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.kind', 'remittance')
            ->assertJsonPath('data.0.reference_number', 'COD-11')
            ->assertJsonPath('data.1.reference_number', 'BATCH-35');
        $rider->getJson(route('rider.payouts.receipts', ['filter' => 'unknown']))
            ->assertStatus(422);
        $this->withSession(['rider' => ['id' => $otherRiderId]])
            ->getJson(route('rider.payouts.receipts', ['filter' => 'all']))
            ->assertOk()->assertJsonPath('pagination.total', 1);
    }

    public function test_transaction_history_paginates_all_records(): void
    {
        $riderId = 990021;
        DB::table('riders')->insert(['id' => $riderId, 'name' => 'Paged Rider', 'is_active' => true]);
        for ($index = 0; $index < 7; $index++) {
            DB::table('rider_payout_requests')->insert([
                'rider_id' => $riderId, 'amount' => $index + 1,
                'gcash_name' => 'Paged Rider', 'gcash_number' => '+639171234567',
                'status' => 'requested', 'created_at' => now()->subMinutes($index),
                'updated_at' => now(),
            ]);
        }

        $this->withSession(['rider' => ['id' => $riderId]])
            ->getJson(route('rider.payouts.receipts', ['filter' => 'all', 'page' => 2]))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.total', 7);
        $this->getJson(route('rider.payouts.receipts', ['filter' => 'all', 'page' => 99]))
            ->assertOk()->assertJsonPath('pagination.current_page', 2);
    }
}
