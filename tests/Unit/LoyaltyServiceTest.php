<?php

namespace Tests\Unit;

use App\Services\LoyaltyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoyaltyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('platform_settings');

        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->string('user_id')->primary();
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_points')->default(0);
            $table->decimal('lifetime_spend', 10, 2)->default(0);
            $table->string('tier', 40)->default('Bronze');
            $table->timestamp('tier_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user_id');
            $table->string('order_id')->nullable();
            $table->string('type', 20);
            $table->integer('points');
            $table->integer('balance_after')->default(0);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 40);
            $table->integer('min_lifetime_points')->default(0);
            $table->decimal('points_multiplier', 5, 2)->default(1);
            $table->string('perk_summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 12)->primary();
            $table->string('user_id', 12)->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('service_charge', 8, 2)->default(0);
            $table->string('status', 30)->default('Pending');
            $table->string('payment_status', 30)->default('Unpaid');
            $table->integer('points_earned')->default(0);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id', 12)->index();
            $table->string('product_name', 160);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price_snapshot', 10, 2)->default(0);
            $table->decimal('final_unit_price_snapshot', 10, 2)->default(0);
            $table->timestamps();
        });

        DB::table('loyalty_tiers')->insert([
            ['name' => 'Bronze', 'min_lifetime_points' => 0, 'points_multiplier' => 1, 'perk_summary' => 'Earn rewards on completed orders.', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Silver', 'min_lifetime_points' => 250, 'points_multiplier' => 1.25, 'perk_summary' => 'Earn more points and unlock verified-only promos.', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gold', 'min_lifetime_points' => 750, 'points_multiplier' => 1.5, 'perk_summary' => 'Highest rewards rate and Customer Loyalty Trust benefits.', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_redeeming_points_can_lower_membership_tier(): void
    {
        DB::table('loyalty_accounts')->insert([
            'user_id' => 'USR001',
            'points_balance' => 800,
            'lifetime_points' => 1000,
            'lifetime_spend' => 10000,
            'tier' => 'Gold',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(LoyaltyService::class)->redeemForOrder('USR001', 'ORD001', 300, 30.00);

        $account = DB::table('loyalty_accounts')->where('user_id', 'USR001')->first();

        $this->assertSame(500, (int) $account->points_balance);
        $this->assertSame('Silver', $account->tier);
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => 'USR001',
            'order_id' => 'ORD001',
            'type' => 'redeem',
            'points' => -300,
            'balance_after' => 500,
        ]);
    }

    public function test_membership_overview_syncs_tier_from_current_earned_points(): void
    {
        DB::table('loyalty_accounts')->insert([
            'user_id' => 'USR002',
            'points_balance' => 500,
            'lifetime_points' => 1000,
            'lifetime_spend' => 10000,
            'tier' => 'Gold',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $overview = app(LoyaltyService::class)->membershipOverview('USR002');

        $this->assertSame('Silver', $overview['current_tier']);
        $this->assertSame(250, $overview['points_to_next']);
        $this->assertDatabaseHas('loyalty_accounts', [
            'user_id' => 'USR002',
            'tier' => 'Silver',
        ]);
    }

    public function test_awarding_points_uses_product_item_subtotal(): void
    {
        DB::table('loyalty_accounts')->insert([
            'user_id' => 'USR003',
            'points_balance' => 0,
            'lifetime_points' => 0,
            'lifetime_spend' => 0,
            'tier' => 'Bronze',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'id' => 'ORD003',
            'user_id' => 'USR003',
            'total_price' => 9999,
            'delivery_fee' => 150,
            'service_charge' => 75,
            'status' => 'Delivered',
            'payment_status' => 'Paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => 'ORD003',
                'product_name' => 'Cake A',
                'quantity' => 1,
                'unit_price_snapshot' => 500,
                'final_unit_price_snapshot' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => 'ORD003',
                'product_name' => 'Cake B',
                'quantity' => 2,
                'unit_price_snapshot' => 250,
                'final_unit_price_snapshot' => 250,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(LoyaltyService::class)->awardForCompletedOrder('ORD003');

        $account = DB::table('loyalty_accounts')->where('user_id', 'USR003')->first();

        $this->assertSame(10, (int) $account->points_balance);
        $this->assertSame(10, (int) $account->lifetime_points);
        $this->assertSame(1000.0, (float) $account->lifetime_spend);
        $this->assertSame('Bronze', $account->tier);
        $this->assertDatabaseHas('orders', [
            'id' => 'ORD003',
            'points_earned' => 10,
        ]);
    }
}
