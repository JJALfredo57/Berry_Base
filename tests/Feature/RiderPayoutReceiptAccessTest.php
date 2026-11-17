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

        if (!Schema::hasTable('riders')) {
            Schema::create('riders', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('phone')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }
        if (!Schema::hasTable('rider_payout_requests')) {
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
}
