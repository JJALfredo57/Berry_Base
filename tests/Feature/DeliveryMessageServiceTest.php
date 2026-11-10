<?php

namespace Tests\Feature;

use App\Services\DeliveryMessageService;
use App\Services\MobileNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeliveryMessageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('orders', function ($table) {
            $table->string('id')->primary(); $table->string('user_id')->nullable(); $table->unsignedInteger('rider_id')->nullable();
            $table->string('fulfillment_type'); $table->string('rider_assignment_status')->nullable(); $table->string('status'); $table->string('track_code')->nullable();
        });
        Schema::create('delivery_messages', function ($table) {
            $table->increments('id'); $table->string('order_id'); $table->unsignedInteger('rider_id'); $table->string('customer_user_id');
            $table->string('sender_type'); $table->string('sender_id'); $table->text('message')->nullable(); $table->text('image_path')->nullable(); $table->timestamp('read_at')->nullable(); $table->timestamps();
        });
        DB::table('orders')->insert(['id' => 'D1', 'user_id' => 10, 'rider_id' => 5, 'fulfillment_type' => 'Delivery', 'rider_assignment_status' => 'accepted', 'status' => 'Out for Delivery']);
        $notifications = Mockery::mock(MobileNotificationService::class);
        $notifications->shouldReceive('notifyRider')->zeroOrMoreTimes();
        $this->app->instance(MobileNotificationService::class, $notifications);
    }

    public function test_owner_can_send_to_current_accepted_rider(): void
    {
        $service = app(DeliveryMessageService::class);
        $order = $service->customerConversation('D1', ['id' => 10, 'role' => 'customer']);
        $message = $service->send($order, 'customer', '10', 'Please ring the bell.', null);

        $this->assertSame('customer', $message->sender_type);
        $this->assertSame('Please ring the bell.', $message->message);
    }

    public function test_other_customer_and_reassigned_rider_cannot_access(): void
    {
        $service = app(DeliveryMessageService::class);
        try {
            $service->customerConversation('D1', ['id' => 11, 'role' => 'customer']);
            $this->fail('Expected customer authorization failure.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        DB::table('orders')->where('id', 'D1')->update(['rider_id' => 6]);
        try {
            $service->riderConversation('D1', (object) ['id' => 5]);
            $this->fail('Expected rider authorization failure.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_closed_delivery_is_not_available(): void
    {
        DB::table('orders')->where('id', 'D1')->update(['status' => 'Delivered']);
        $this->expectException(HttpException::class);
        app(DeliveryMessageService::class)->customerConversation('D1', ['id' => 10, 'role' => 'customer']);
    }
}
