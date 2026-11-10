<?php

namespace Tests\Feature;

use App\Services\DeliveryMessageService;
use App\Services\MobileNotificationService;
use App\Http\Controllers\DeliveryMessageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
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
            $table->string('fulfillment_type'); $table->string('rider_assignment_status')->nullable(); $table->unsignedInteger('delivery_rider_id')->nullable(); $table->string('delivery_rider_name')->nullable(); $table->string('status'); $table->string('track_code')->nullable();
        });
        Schema::create('delivery_messages', function ($table) {
            $table->increments('id'); $table->string('order_id'); $table->unsignedInteger('rider_id'); $table->string('customer_user_id');
            $table->string('sender_type'); $table->string('sender_id'); $table->text('message')->nullable(); $table->text('image_path')->nullable(); $table->timestamp('read_at')->nullable(); $table->timestamps();
        });
        Schema::create('rider_remittances', function ($table) {
            $table->increments('id'); $table->string('order_id'); $table->unsignedInteger('rider_id')->nullable();
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

    public function test_cancelled_delivery_is_not_available(): void
    {
        DB::table('orders')->where('id', 'D1')->update(['status' => 'Cancelled']);
        $this->expectException(HttpException::class);
        app(DeliveryMessageService::class)->customerConversation('D1', ['id' => 10, 'role' => 'customer']);
    }

    public function test_delivered_order_with_rider_snapshot_remains_available(): void
    {
        DB::table('orders')->where('id', 'D1')->update(['status' => 'Delivered', 'delivery_rider_id' => 5, 'delivery_rider_name' => 'Rider One']);

        $order = app(DeliveryMessageService::class)->customerConversation('D1', ['id' => 10, 'role' => 'customer']);

        $this->assertSame('D1', $order->id);
    }

    public function test_delivered_order_can_use_a_remittance_rider_when_no_snapshot_exists(): void
    {
        DB::table('orders')->where('id', 'D1')->update([
            'status' => 'Delivered',
            'rider_id' => null,
            'delivery_rider_id' => null,
            'delivery_rider_name' => null,
        ]);
        DB::table('rider_remittances')->insert(['order_id' => 'D1', 'rider_id' => 5]);

        $service = app(DeliveryMessageService::class);
        $order = $service->customerConversation('D1', ['id' => 10, 'role' => 'customer']);

        $this->assertSame('D1', $order->id);
        $this->assertSame('D1', $service->riderConversation('D1', (object) ['id' => 5])->id);
    }

    public function test_customer_can_send_an_image_only_message_without_a_missing_message_key_error(): void
    {
        session(['user' => ['id' => 10, 'role' => 'customer']]);
        $order = (object) ['id' => 'D1'];
        $messages = Mockery::mock(DeliveryMessageService::class);
        $messages->shouldReceive('customerConversation')->once()->andReturn($order);
        $messages->shouldReceive('send')->once()->with($order, 'customer', '10', '', null)->andReturn((object) ['id' => 1]);

        $response = app(DeliveryMessageController::class)->customerSend(Request::create('/customer/orders/D1/delivery-messages', 'POST'), 'D1', $messages);

        $this->assertSame(201, $response->getStatusCode());
    }
}
