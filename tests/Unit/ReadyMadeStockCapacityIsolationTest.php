<?php

namespace Tests\Unit;

use App\Services\DailyCapacityService;
use App\Services\ProductStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadyMadeStockCapacityIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('shop_id')->nullable();
            $table->string('name');
            $table->integer('available_quantity')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('product_id');
            $table->string('label');
            $table->integer('available_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('shop_id')->nullable();
            $table->integer('daily_max_cakes')->default(0);
            $table->integer('custom_cake_prep_days')->default(3);
            $table->text('custom_capacity_schedule')->nullable();
            $table->timestamps();
        });

        DB::table('products')->insert([
            'id' => 'ready-cake',
            'shop_id' => 'new-shop',
            'name' => 'Strawberry Pink Mini Cake',
            'available_quantity' => 10,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_sizes')->insert([
            [
                'product_id' => 'ready-cake',
                'label' => '6-inch',
                'available_quantity' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => 'ready-cake',
                'label' => '8-inch',
                'available_quantity' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_ready_made_size_with_stock_can_be_reserved_without_custom_capacity(): void
    {
        $capacity = app(DailyCapacityService::class)->validate('new-shop', '2026-11-25', 1);
        $this->assertFalse($capacity['allowed']);
        $this->assertSame('capacity_not_configured', $capacity['status']);

        $items = [['product_id' => 'ready-cake', 'selected_size' => '6-inch', 'quantity' => 1]];
        $stock = app(ProductStockService::class);

        $this->assertTrue($stock->validateItems($items)['ok']);
        $this->assertTrue($stock->reserveItems($items)['ok']);
        $this->assertSame(9, (int) DB::table('product_sizes')->where('product_id', 'ready-cake')->where('label', '6-inch')->value('available_quantity'));
    }

    public function test_ready_made_size_without_stock_remains_blocked(): void
    {
        $result = app(ProductStockService::class)->validateItems([
            ['product_id' => 'ready-cake', 'selected_size' => '8-inch', 'quantity' => 1],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('out of stock', $result['message']);
    }
}
