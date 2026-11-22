<?php

namespace Tests\Unit;

use App\Services\SellerRemittanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerRemittanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('shops', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('seller_id');
            $table->string('shop_name');
            $table->string('shop_slug');
            $table->string('status');
            $table->string('tier')->default('verified');
            $table->string('theme_color')->nullable();
            $table->string('shop_logo')->nullable();
            $table->string('shop_cover')->nullable();
            $table->boolean('commission_enabled')->default(true);
        });
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('fullname');
        });
        Schema::create('riders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
        });
        Schema::create('custom_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_id');
            $table->string('cake_name')->nullable();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('shop_id');
            $table->string('user_id')->nullable();
            $table->string('product_id')->nullable();
            $table->string('track_code')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('fullname')->nullable();
            $table->string('delivery_rider_name')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('rider_remittances', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_id');
            $table->string('shop_id');
            $table->unsignedInteger('rider_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('customer_collected_amount', 10, 2)->nullable();
            $table->decimal('cake_remittance_amount', 10, 2)->nullable();
            $table->decimal('delivery_fee_retained', 10, 2)->nullable();
            $table->string('collection_method')->default('Cash');
            $table->string('remittance_method')->nullable();
            $table->string('status');
            $table->string('reference_number')->nullable();
            $table->text('rider_note')->nullable();
            $table->text('seller_note')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        DB::table('shops')->insert(['id' => 'shop-a', 'seller_id' => 'seller-a', 'shop_name' => 'Test Cake Shop', 'shop_slug' => 'test-cake-shop', 'status' => 'approved']);
        DB::table('users')->insert(['id' => 'buyer', 'fullname' => 'Test Buyer']);
        DB::table('riders')->insert([['id' => 1, 'name' => 'Rider One'], ['id' => 2, 'name' => 'Rider Two']]);
        DB::table('products')->insert(['id' => 'cake', 'name' => 'Pink Cake']);
        foreach ([
            ['id' => 'ORDER-A', 'shop_id' => 'shop-a', 'track_code' => 'TRACK-A', 'rider_id' => 1, 'status' => 'pending', 'customer' => 16, 'cake' => 11, 'fee' => 5],
            ['id' => 'ORDER-B', 'shop_id' => 'shop-a', 'track_code' => 'TRACK-B', 'rider_id' => 2, 'status' => 'submitted', 'customer' => 25, 'cake' => 20, 'fee' => 5],
            ['id' => 'ORDER-C', 'shop_id' => 'shop-a', 'track_code' => 'TRACK-C', 'rider_id' => 1, 'status' => 'confirmed', 'customer' => 35, 'cake' => 30, 'fee' => 5],
            ['id' => 'ORDER-X', 'shop_id' => 'shop-b', 'track_code' => 'TRACK-X', 'rider_id' => 1, 'status' => 'pending', 'customer' => 104, 'cake' => 99, 'fee' => 5],
        ] as $index => $row) {
            DB::table('orders')->insert([
                'id' => $row['id'], 'shop_id' => $row['shop_id'], 'user_id' => 'buyer', 'product_id' => 'cake',
                'track_code' => $row['track_code'], 'status' => 'Delivered', 'payment_status' => 'Paid',
                'payment_method' => 'COD', 'quantity' => 1, 'total_price' => $row['customer'],
                'created_at' => now()->subDays(4 - $index), 'updated_at' => now(),
            ]);
            DB::table('rider_remittances')->insert([
                'order_id' => $row['id'], 'shop_id' => $row['shop_id'], 'rider_id' => $row['rider_id'],
                'amount' => $row['cake'], 'customer_collected_amount' => $row['customer'],
                'cake_remittance_amount' => $row['cake'], 'delivery_fee_retained' => $row['fee'],
                'status' => $row['status'], 'collected_at' => now()->subDays(4 - $index),
                'submitted_at' => $row['status'] === 'submitted' ? now() : null,
                'confirmed_at' => $row['status'] === 'confirmed' ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_dashboard_total_and_summary_use_only_outstanding_cake_cash_for_the_shop(): void
    {
        $service = app(SellerRemittanceService::class);

        $this->assertSame(31.0, $service->outstandingTotal('shop-a'));
        $this->assertSame(99.0, $service->outstandingTotal('shop-b'));
        $this->assertSame([
            'outstanding' => 31.0,
            'waiting' => 11.0,
            'submitted' => 20.0,
            'confirmed' => 30.0,
            'outstanding_count' => 2,
            'record_count' => 3,
        ], $service->summary('shop-a'));
    }

    public function test_status_search_and_rider_filters_are_shop_scoped(): void
    {
        $service = app(SellerRemittanceService::class);
        $query = $service->applyFilters($service->query('shop-a'), [
            'status' => 'submitted',
            'search' => 'rider two',
            'rider_id' => 2,
        ]);

        $this->assertSame(20.0, $service->filteredTotal($query));
        $rows = $service->sort($query, 'newest')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('ORDER-B', $rows->first()->order_id);
        $this->assertSame('Test Buyer', $rows->first()->customer_name);
        $this->assertSame('Rider Two', $rows->first()->rider_name);
    }

    public function test_default_outstanding_filter_excludes_confirmed_history(): void
    {
        $service = app(SellerRemittanceService::class);
        $query = $service->applyFilters($service->query('shop-a'), ['status' => 'outstanding']);

        $this->assertSame(['ORDER-A', 'ORDER-B'], $service->sort($query, 'oldest')->pluck('order_id')->all());
    }

    public function test_seller_page_renders_filtered_rows_totals_and_pagination_controls(): void
    {
        $session = ['user' => ['id' => 'seller-a', 'role' => 'seller', 'fullname' => 'Test Seller']];

        $this->withSession($session)
            ->get(route('seller.remittances'))
            ->assertOk()
            ->assertSee('COD Rider Remittances')
            ->assertSee('ORDER-A')
            ->assertSee('ORDER-B')
            ->assertDontSee('ORDER-C')
            ->assertSee('Filtered cake amount')
            ->assertSee('31.00')
            ->assertSee('10 per page');

        $this->withSession($session)
            ->get(route('seller.remittances', ['status' => 'confirmed']))
            ->assertOk()
            ->assertSee('ORDER-C')
            ->assertDontSee('ORDER-A')
            ->assertSee('30.00');
    }
}
