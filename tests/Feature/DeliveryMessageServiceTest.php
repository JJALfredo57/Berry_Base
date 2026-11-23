<?php

namespace Tests\Feature;

use App\Services\DeliveryMessageService;
use App\Services\MobileNotificationService;
use App\Http\Controllers\DeliveryMessageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        Schema::create('users', function ($table) {
            $table->string('id')->primary(); $table->string('fullname'); $table->string('profile_photo')->nullable();
        });
        Schema::create('riders', function ($table) {
            $table->increments('id'); $table->string('name'); $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true); $table->boolean('password_must_change')->default(false);
        });

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
        DB::table('users')->insert(['id' => 10, 'fullname' => 'Chat Customer']);
        DB::table('riders')->insert(['id' => 5, 'name' => 'Chat Rider']);
        $notifications = Mockery::mock(MobileNotificationService::class);
        $notifications->shouldReceive('notifyRider')->zeroOrMoreTimes();
        $notifications->shouldReceive('notifyOrderCustomer')->zeroOrMoreTimes();
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

    public function test_inbox_keeps_delivered_history_and_filters_active_chats_without_marking_read(): void
    {
        DB::table('orders')->insert(['id' => 'D2', 'user_id' => 10, 'rider_id' => null, 'delivery_rider_id' => null, 'fulfillment_type' => 'Delivery', 'status' => 'Delivered']);
        DB::table('rider_remittances')->insert(['order_id' => 'D2', 'rider_id' => 5]);
        DB::table('delivery_messages')->insert(['order_id' => 'D2', 'rider_id' => 5, 'customer_user_id' => '10', 'sender_type' => 'customer', 'sender_id' => '10', 'message' => 'Delivered chat history', 'created_at' => now(), 'updated_at' => now()]);

        $this->withSession(['rider' => ['id' => 5]])->get(route('rider.messages'))
            ->assertOk()->assertSee('Delivered chat history')->assertSee('Order #D1')->assertSee('Order #D2');
        $this->get(route('rider.messages', ['filter' => 'active']))->assertOk()->assertSee('Order #D1')->assertDontSee('Order #D2');
        $this->getJson(route('rider.messages', ['summary' => 1]))->assertOk()->assertJsonPath('unread', 1);
        $this->assertNull(DB::table('delivery_messages')->value('read_at'));
        $this->get(route('rider.messages', ['q' => 'D2']))->assertOk()->assertSee('Order #D2')->assertDontSee('Order #D1');
    }

    public function test_reassigned_rider_cannot_list_read_or_send_to_another_riders_conversation(): void
    {
        DB::table('orders')->where('id', 'D1')->update(['rider_id' => 6]);
        DB::table('delivery_messages')->insert(['order_id' => 'D1', 'rider_id' => 5, 'customer_user_id' => '10', 'sender_type' => 'customer', 'sender_id' => '10', 'message' => 'Old assignment', 'created_at' => now(), 'updated_at' => now()]);
        $this->withSession(['rider' => ['id' => 5]])->get(route('rider.messages'))->assertOk()->assertDontSee('Order #D1');
        $this->get(route('rider.messages.show', 'D1'))->assertForbidden();
        $this->getJson(route('rider.delivery_messages.index', 'D1'))->assertForbidden();
        $this->postJson(route('rider.delivery_messages.send', 'D1'), ['message' => 'Not allowed'])->assertForbidden();
        $this->assertSame(1, DB::table('delivery_messages')->count());
    }

    public function test_opening_thread_api_marks_only_incoming_messages_read_and_preserves_customer_access(): void
    {
        DB::table('delivery_messages')->insert([
            ['order_id' => 'D1', 'rider_id' => 5, 'customer_user_id' => '10', 'sender_type' => 'customer', 'sender_id' => '10', 'message' => 'Hello rider', 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => 'D1', 'rider_id' => 5, 'customer_user_id' => '10', 'sender_type' => 'rider', 'sender_id' => '5', 'message' => 'Hello customer', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->withSession(['rider' => ['id' => 5]])->get(route('rider.messages.show', 'D1'))->assertOk()->assertSee('Chat Customer');
        $this->getJson(route('rider.delivery_messages.index', 'D1'))->assertOk()->assertJsonCount(2, 'messages');
        $this->assertNotNull(DB::table('delivery_messages')->where('sender_type', 'customer')->value('read_at'));
        $this->assertNull(DB::table('delivery_messages')->where('sender_type', 'rider')->value('read_at'));
        $this->withSession(['user' => ['id' => 10, 'role' => 'customer']])->getJson(route('customer.delivery_messages.index', 'D1'))->assertOk()->assertJsonCount(2, 'messages');
    }

    public function test_messages_pages_require_active_login_and_validate_filters(): void
    {
        $this->get(route('rider.messages'))->assertRedirect(route('login'));
        $this->get(route('rider.messages.show', 'D1'))->assertRedirect(route('login'));
        $this->withSession(['rider' => ['id' => 5]])->getJson(route('rider.messages', ['filter' => 'invalid']))->assertUnprocessable();
        $this->get(route('rider.messages.show', 'missing'))->assertNotFound();
        DB::table('riders')->where('id', 5)->update(['is_active' => false]);
        $this->get(route('rider.messages'))->assertRedirect(route('login'))->assertSessionMissing('rider');
    }

    public function test_rider_send_validates_payload_and_accepts_text_and_image_only_messages(): void
    {
        Storage::fake('public');
        config(['filesystems.upload_disk' => 'public']);
        $url = route('rider.delivery_messages.send', 'D1');
        $this->withSession(['rider' => ['id' => 5]])->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, ['message' => str_repeat('x', 1001)])->assertUnprocessable();
        $this->postJson($url, ['image' => UploadedFile::fake()->create('bad.pdf', 5, 'application/pdf')])->assertUnprocessable();
        $this->postJson($url, ['message' => 'On my way'])->assertCreated()->assertJsonPath('message.message', 'On my way');
        $this->postJson($url, ['message' => 'On my way'])->assertStatus(429);
        $image = UploadedFile::fake()->createWithContent('delivery.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC1sAAAAASUVORK5CYII='));
        $this->post($url, ['image' => $image], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('message.sender_type', 'rider');
        $this->assertSame(2, DB::table('delivery_messages')->count());
        $this->assertNotNull(DB::table('delivery_messages')->whereNull('message')->value('image_path'));
    }
}
