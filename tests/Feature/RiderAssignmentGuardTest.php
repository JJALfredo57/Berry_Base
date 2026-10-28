<?php

namespace Tests\Feature;

use App\Services\MobileNotificationService;
use App\Services\RiderAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RiderAssignmentGuardTest extends TestCase
{
    private $notifications;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('riders', function ($t) {
            $t->increments('id');
            $t->string('name');
            $t->string('phone')->nullable();
            $t->boolean('is_active')->default(true);
        });
        Schema::create('orders', function ($t) {
            $t->string('id')->primary();
            $t->string('status')->default('Ready for Rider');
            $t->integer('shop_id')->nullable();
            $t->integer('user_id')->nullable();
            $t->string('guest_name')->nullable();
            $t->string('guest_phone')->nullable();
            $t->string('delivery_address')->nullable();
            $t->string('payment_method')->default('GCash');
            $t->string('fulfillment_type')->default('Delivery');
            $t->decimal('total_price', 10, 2)->default(0);
            $t->integer('rider_id')->nullable();
            $t->string('rider_token')->nullable();
            $t->string('rider_pin')->nullable();
            $t->boolean('rider_sms_sent')->default(false);
            $t->string('rider_assignment_status')->nullable();
            $t->string('rider_assignment_previous_status')->nullable();
            $t->timestamp('rider_assigned_at')->nullable();
            $t->timestamp('rider_assignment_expires_at')->nullable();
            $t->timestamp('rider_accepted_at')->nullable();
            $t->timestamp('rider_declined_at')->nullable();
            $t->string('rider_decline_reason')->nullable();
            $t->timestamp('updated_at')->nullable();
        });

        Schema::create('order_tracking', function ($t) {
            $t->increments('id');
            $t->string('order_id');
            $t->string('status');
            $t->text('notes')->nullable();
            $t->string('sender_role')->nullable();
            $t->string('receiver_role')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        DB::table('riders')->insert([
            ['id' => 1, 'name' => 'Rider One', 'phone' => '09170000001'],
            ['id' => 2, 'name' => 'Rider Two', 'phone' => '09170000002'],
        ]);
        DB::table('orders')->insert(['id' => 'O1', 'status' => 'Ready for Rider']);

        $this->notifications = Mockery::mock(MobileNotificationService::class);
        $this->notifications->shouldReceive('notifyRider')->andReturn(['channel' => 'push']);
        $this->app->instance(MobileNotificationService::class, $this->notifications);
    }

    private function service(): RiderAssignmentService
    {
        return app(RiderAssignmentService::class);
    }

    private function order(): object
    {
        return DB::table('orders')->where('id', 'O1')->first();
    }

    private function rider(int $id): object
    {
        return DB::table('riders')->where('id', $id)->first();
    }

    public function test_second_assignment_is_refused_while_first_is_pending(): void
    {
        $first = $this->service()->assign($this->order(), $this->rider(1));
        $this->assertTrue($first['ok']);

        $second = $this->service()->assign($this->order(), $this->rider(2));
        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('Rider One', $second['message']);
        $this->assertSame(1, (int) $this->order()->rider_id);
    }

    public function test_second_assignment_is_refused_after_acceptance(): void
    {
        $this->service()->assign($this->order(), $this->rider(1));
        DB::table('orders')->where('id', 'O1')->update(['rider_assignment_status' => 'accepted']);

        $this->assertFalse($this->service()->assign($this->order(), $this->rider(2))['ok']);
        $this->assertSame(1, (int) $this->order()->rider_id);
    }

    public function test_cancel_clears_rider_token_and_pin_and_allows_reassign(): void
    {
        $this->service()->assign($this->order(), $this->rider(1));
        $oldToken = $this->order()->rider_token;

        $cancel = $this->service()->cancelPending($this->order(), 'seller');
        $this->assertTrue($cancel['ok']);

        $order = $this->order();
        $this->assertNull($order->rider_id);
        $this->assertNull($order->rider_token);
        $this->assertNull($order->rider_pin);
        $this->assertSame('cancelled', $order->rider_assignment_status);
        $this->assertSame('Ready for Rider', $order->status);

        $again = $this->service()->assign($order, $this->rider(2));
        $this->assertTrue($again['ok']);
        $this->assertNotSame($oldToken, $this->order()->rider_token);
        $this->assertSame(2, (int) $this->order()->rider_id);
    }

    public function test_accepted_assignment_cannot_be_cancelled(): void
    {
        $this->service()->assign($this->order(), $this->rider(1));
        DB::table('orders')->where('id', 'O1')->update(['rider_assignment_status' => 'accepted']);

        $this->assertFalse($this->service()->cancelPending($this->order())['ok']);
        $this->assertSame(1, (int) $this->order()->rider_id);
    }

    public function test_declined_assignment_raises_alert_with_rider_name_and_reason(): void
    {
        $this->notifications->shouldReceive('notifyOrderSellerByPriority')->once();
        $this->service()->assign($this->order(), $this->rider(1));
        $this->service()->decline($this->order(), 'Flat tire');

        $alert = $this->service()->alertInfo('O1');
        $this->assertNotNull($alert);
        $this->assertSame('declined', $alert->state);
        $this->assertSame('Rider One', $alert->rider_name);
        $this->assertSame('Flat tire', $alert->reason);

        $this->assertTrue($this->service()->assign($this->order(), $this->rider(2))['ok']);
        $this->assertNull($this->service()->alertInfo('O1'));
    }

    public function test_expired_assignment_raises_alert_and_notifies_seller(): void
    {
        $this->notifications->shouldReceive('notifyOrderSellerByPriority')->once();
        $this->service()->assign($this->order(), $this->rider(1));
        DB::table('orders')->where('id', 'O1')->update(['rider_assignment_expires_at' => now()->subMinute(), 'shop_id' => 1]);

        $this->assertSame(1, $this->service()->expirePendingAssignments('1'));

        $alert = $this->service()->alertInfo('O1');
        $this->assertSame('expired', $alert->state);
        $this->assertSame('Rider One', $alert->rider_name);
    }

    public function test_no_alert_for_pending_or_unassigned_orders(): void
    {
        $this->assertNull($this->service()->alertInfo('O1'));
        $this->service()->assign($this->order(), $this->rider(1));
        $this->assertNull($this->service()->alertInfo('O1'));
    }

    public function test_declined_assignment_can_be_reassigned(): void
    {
        $this->service()->assign($this->order(), $this->rider(1));
        DB::table('orders')->where('id', 'O1')->update(['rider_assignment_status' => 'declined', 'rider_id' => null]);

        $this->assertTrue($this->service()->assign($this->order(), $this->rider(2))['ok']);
    }
}
