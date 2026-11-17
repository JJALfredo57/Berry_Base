<?php

namespace Tests\Unit;

use App\Services\DailyCapacityService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DailyCapacityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-18 08:00:00', 'Asia/Manila'));
        if (!Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('shop_id')->nullable();
                $table->timestamps();
            });
        }
        foreach (['daily_max_cakes', 'custom_cake_prep_days', 'custom_capacity_schedule'] as $column) {
            if (!Schema::hasColumn('site_settings', $column)) {
                Schema::table('site_settings', function (Blueprint $table) use ($column) {
                    $column === 'custom_capacity_schedule' ? $table->text($column)->nullable() : $table->integer($column)->nullable();
                });
            }
        }
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->increments('id');
                $table->string('shop_id')->nullable();
                $table->date('schedule_date')->nullable();
                $table->integer('quantity')->default(1);
                $table->string('status')->nullable();
            });
        }

        DB::table('site_settings')->truncate();
        DB::table('orders')->delete();
        DB::table('site_settings')->insert([
            'shop_id' => 'shop-capacity',
            'daily_max_cakes' => 10,
            'custom_cake_prep_days' => 3,
            'custom_capacity_schedule' => json_encode(['0' => 3, '1' => 0, '2' => 5]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_zero_relative_capacity_uses_the_seller_default_capacity(): void
    {
        $snapshot = app(DailyCapacityService::class)->snapshot('shop-capacity', '2026-11-22');

        $this->assertSame(10, $snapshot['max']);
        $this->assertSame(10, $snapshot['remaining']);
    }

    public function test_custom_capacity_rejects_a_made_to_order_quantity_above_the_date_limit(): void
    {
        $result = app(DailyCapacityService::class)->validate('shop-capacity', '2026-11-21', 4);

        $this->assertFalse($result['allowed']);
        $this->assertSame(3, $result['remaining']);
    }
}
