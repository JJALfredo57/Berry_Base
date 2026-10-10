<?php

namespace Tests\Unit;

use App\Services\FulfillmentScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FulfillmentScheduleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-10-10 08:00:00', 'Asia/Manila'));

        if (!Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function ($table) {
                $table->increments('id');
                $table->string('shop_id')->nullable();
                $table->time('shop_open_time')->nullable();
                $table->time('shop_close_time')->nullable();
                $table->integer('ready_made_prep_minutes')->nullable();
                $table->integer('custom_cake_prep_minutes')->nullable();
                $table->integer('pickup_buffer_minutes')->nullable();
                $table->integer('delivery_base_buffer_minutes')->nullable();
                $table->integer('delivery_minutes_per_km')->nullable();
                $table->decimal('shop_lat', 10, 7)->nullable();
                $table->decimal('shop_lng', 10, 7)->nullable();
                $table->timestamps();
            });
        }

        DB::table('site_settings')->truncate();
        DB::table('site_settings')->insert([
            'shop_id' => 'shop-1',
            'shop_open_time' => '09:00',
            'shop_close_time' => '19:00',
            'ready_made_prep_minutes' => 90,
            'custom_cake_prep_minutes' => 0,
            'pickup_buffer_minutes' => 0,
            'delivery_base_buffer_minutes' => 30,
            'delivery_minutes_per_km' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ready_made_cannot_use_exact_shop_opening_time(): void
    {
        $service = app(FulfillmentScheduleService::class);

        $result = $service->validate('shop-1', '2026-10-11', '09:00', 'regular', 'Pickup');

        $this->assertFalse($result['ok']);
        $this->assertSame(105, $result['required_minutes']);
        $this->assertStringContainsString('Earliest available time is 10:45 AM', $result['message']);
    }

    public function test_ready_made_accepts_time_after_opening_prep_and_pickup_buffer(): void
    {
        $service = app(FulfillmentScheduleService::class);

        $result = $service->validate('shop-1', '2026-10-11', '10:45', 'regular', 'Pickup');

        $this->assertTrue($result['ok']);
    }

    public function test_custom_order_uses_minimum_opening_buffer_even_when_saved_pickup_buffer_is_zero(): void
    {
        $service = app(FulfillmentScheduleService::class);

        $tooEarly = $service->validate('shop-1', '2026-10-11', '09:00', 'custom', 'Pickup');
        $valid = $service->validate('shop-1', '2026-10-11', '09:15', 'custom', 'Pickup');

        $this->assertFalse($tooEarly['ok']);
        $this->assertSame(15, $tooEarly['required_minutes']);
        $this->assertTrue($valid['ok']);
    }

    public function test_today_requires_later_of_opening_buffer_and_current_time_buffer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'Asia/Manila'));
        $service = app(FulfillmentScheduleService::class);

        $tooEarly = $service->validate('shop-1', '2026-10-10', '11:30', 'regular', 'Pickup');
        $valid = $service->validate('shop-1', '2026-10-10', '11:45', 'regular', 'Pickup');

        $this->assertFalse($tooEarly['ok']);
        $this->assertStringContainsString('Earliest available time is 11:45 AM', $tooEarly['message']);
        $this->assertTrue($valid['ok']);
    }
}
