<?php

namespace Tests\Feature;

use App\Services\OrderRefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PurchaseJourneyRenderingTest extends TestCase
{
    public function test_cancelled_custom_order_is_read_only_for_seller(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('COP');
        DB::table('orders')->where('id', $order->id)->update([
            'order_type' => 'custom',
            'status' => 'Cancelled',
            'cancel_requested' => true,
            'cancel_status' => 'accepted',
        ]);
        DB::table('custom_orders')->insert([
            'id' => 'CANCELLEDCUSTOM', 'order_id' => $order->id, 'shop_id' => $order->shop_id,
            'user_id' => 'TESTBUYER', 'cake_name' => 'Cancelled Custom Cake',
            'review_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $seller = ['user' => ['id' => 'TESTSELLER', 'role' => 'seller', 'fullname' => 'Test Seller']];
        $this->withSession($seller)->get('/seller/dashboard')
            ->assertOk()->assertDontSee('awaiting your review and pricing');
        $this->withSession($seller)->getJson('/seller/sidebar-counts')
            ->assertOk()->assertJsonPath('counts.custom_orders', 0);
        DB::table('orders')->where('id', $order->id)->update(['status' => 'Pending Review']);
        $this->withSession($seller)->getJson('/seller/sidebar-counts')
            ->assertOk()->assertJsonPath('counts.custom_orders', 1);
        $this->withSession($seller)->get('/seller/dashboard')
            ->assertOk()->assertSee('awaiting your review and pricing');
        DB::table('orders')->where('id', $order->id)->update(['status' => 'Cancelled']);
        $this->withSession($seller)->get('/seller/custom-orders?tab=pending')
            ->assertOk()->assertDontSee('Cancelled Custom Cake');
        $this->withSession($seller)->get('/seller/custom-orders?tab=all')
            ->assertOk()->assertSee('Cancelled Custom Cake')
            ->assertSee('Customer cancelled this order')
            ->assertDontSee('Approve this Order');
        $this->withSession($seller)->post('/seller/custom-orders/CANCELLEDCUSTOM/approve', ['admin_price' => 1500])
            ->assertSessionHas('err');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Cancelled']);
        $this->assertDatabaseHas('custom_orders', ['id' => 'CANCELLEDCUSTOM', 'review_status' => 'pending']);
        DB::table('custom_orders')->where('id', 'CANCELLEDCUSTOM')->update(['review_status' => 'rejected']);
        $this->withSession($seller)->get('/seller/custom-orders?tab=rejected')
            ->assertOk()->assertSee('Cancelled Custom Cake')->assertSee('Rejected')
            ->assertDontSee('Customer cancelled this order');
    }

    public function test_unpaid_custom_cancellation_is_immediate_but_paid_requires_review(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('COP');
        DB::table('orders')->where('id', $order->id)->update(['order_type' => 'custom', 'status' => 'Pending Review']);
        $this->post('/customer/orders/'.$order->id.'/cancel-request', ['cancel_reason' => 'Changed plans'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Cancelled', 'cancel_status' => 'accepted']);
        $this->assertDatabaseCount('order_refunds', 0);

        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'Confirmed', 'cancel_requested' => 0, 'cancel_status' => null,
            'deposit_status' => 'paid', 'payment_status' => 'Partial Payment',
        ]);
        $this->post('/customer/orders/'.$order->id.'/cancel-request', [
            'cancel_reason' => 'Changed plans', 'refund_gcash_name' => 'Test Buyer', 'refund_gcash_number' => '09172222222',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Confirmed', 'cancel_status' => 'pending']);
        $this->assertDatabaseCount('order_refunds', 1);
    }

    public function test_customer_deposit_qr_opens_in_a_responsive_modal(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('GCash');

        $this->get('/customer/orders')
            ->assertOk()
            ->assertSee('Show Deposit QR')
            ->assertSee('modal fade customer-qr-modal customer-qr-panel', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-labelledby="customerQrTitle'.$order->id.'"', false)
            ->assertSee('modal-dialog-centered modal-dialog-scrollable', false)
            ->assertSee('Check Payment')
            ->assertSee('Try Again')
            ->assertSee('width:calc(100% - 1rem)', false)
            ->assertSee('window.bootstrap.Modal.getOrCreateInstance(panel).show()', false);
    }

    public function test_login_session_cookie_is_not_expired_when_the_browser_closes(): void
    {
        $this->prepareFixture();
        $response = $this->post('/login', [
            'username' => 'testbuyer',
            'password' => 'TestPassword1!',
        ]);

        $response->assertRedirect(route('customer.orders'));
        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertGreaterThan(time(), $sessionCookie->getExpiresTime());
        $this->assertFalse((bool) config('session.expire_on_close'));
    }

    public function test_catalog_authentication_cart_and_checkout_destinations_render(): void
    {
        $this->prepareFixture();

        $this->get('/catalog')->assertOk()->assertSee('Buy Now');
        $this->get('/shop/test-cake-shop')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 2])->assertRedirect(route('login'));
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])->assertRedirect(route('customer.checkout'));
        $this->get('/customer/checkout')->assertOk()->assertSee('Test Cake')->assertSee('value="COP"', false);
        $this->get('/customer/custom-order?shop=test-cake-shop')->assertOk()->assertSee('value="COP"', false);
        $this->post('/cart/add', ['product_id' => 'TESTCAKE', 'quantity' => 1])->assertRedirect(route('customer.cart'));
        $this->get('/customer/cart')->assertOk()
            ->assertSee('Test Cake')
            ->assertSee('Add More Cakes')
            ->assertSee('cart-select-all-label', false)
            ->assertSee('cart-check cart-item-check', false)
            ->assertSee('cart-checkout-btn', false)
            ->assertSee('customRescheduleSavedAddress', false);
        $this->get('/customer/rewards')->assertOk()
            ->assertSee('Starting Level')
            ->assertSee('No points earned yet.')
            ->assertSee('Available Points');
        $this->get('/shop/test-cake-shop')->assertOk();
        $this->assertDatabaseCount('orders', 0);

        $submission = [
            'fulfillment_type' => 'Pickup', 'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:00', 'payment_method' => 'COD', '_submit_token' => 'test-purchase-once',
            'latitude' => '', 'longitude' => '',
        ];
        $this->postJson('/customer/checkout/place', array_merge($submission, ['payment_method' => 'invalid']))
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->post('/customer/checkout/place', $submission)->assertRedirect();
        $this->assertFalse(session()->has('error'), session('error') ?? '');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['user_id' => 'TESTBUYER', 'product_id' => 'TESTCAKE', 'quantity' => 2]);
        $cashPickup = DB::table('orders')->where('product_id', 'TESTCAKE')->first();
        $this->assertSame('COP', $cashPickup->payment_method);
        $this->assertSame('Awaiting Deposit', $cashPickup->status);
        $this->assertSame(1, (int) $cashPickup->deposit_required);
        $this->assertEquals(500.0, (float) $cashPickup->deposit_amount);
        $this->assertSame('pending', $cashPickup->deposit_status);
        $this->assertStringContainsString('50% GCash deposit', session('msg'));
        $this->assertStringContainsString('remaining balance is due in cash at pickup', session('msg'));
        $this->post('/customer/checkout/place', $submission)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $trackCode = DB::table('orders')->value('track_code');
        $this->get('/track/'.$trackCode)
            ->assertOk()
            ->assertSee('Pay 50% Deposit via GCash')
            ->assertSee('PHP 500.00');
        $legacy = (array) DB::table('orders')->first();
        $legacy['id'] = 'LEGACYORDER';
        $legacy['track_code'] = 'LEGACY01';
        $legacy['user_id'] = null;
        DB::table('orders')->insert($legacy);
        $this->post('/logout')->assertRedirect();
        $this->get('/track/LEGACY01')->assertOk();
        $this->get('/track/'.$trackCode)->assertRedirect(route('login'));
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])->assertRedirect(url('/track/'.$trackCode));
    }

    public function test_cash_delivery_requires_a_fifty_percent_gcash_deposit(): void
    {
        $this->prepareFixture();
        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 1]);
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])
            ->assertRedirect(route('customer.checkout'));

        $this->post('/customer/checkout/place', [
            'fulfillment_type' => 'Delivery',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:00',
            'payment_method' => 'COP',
            'delivery_zone' => 'Poblacion',
            'address' => 'House 12, Rizal Street, Poblacion, Bautista, Near school',
            'latitude' => '15.8095',
            'longitude' => '120.4988',
        ])->assertRedirect();

        $this->assertFalse(session()->has('error'), session('error') ?? '');
        $order = DB::table('orders')->first();
        $this->assertSame('COD', $order->payment_method);
        $this->assertSame('Delivery', $order->fulfillment_type);
        $this->assertSame('Awaiting Deposit', $order->status);
        $this->assertSame(1, (int) $order->deposit_required);
        $this->assertEquals(round((float) $order->total_price * 0.5, 2), (float) $order->deposit_amount);
        $this->assertSame('pending', $order->deposit_status);
        $this->assertStringContainsString('50% GCash deposit', session('msg'));
        $this->assertStringContainsString('remaining balance is due in cash at delivery', session('msg'));
    }

    public function test_custom_cash_pickup_is_normalized_and_kept_pending_seller_review(): void
    {
        $this->prepareFixture();
        $this->withSession(['user' => [
            'id' => 'TESTBUYER',
            'role' => 'customer',
            'fullname' => 'Test Buyer',
        ]]);
        $submission = [
            'shop_slug' => 'test-cake-shop',
            'cake_name' => 'Birthday Cake',
            'quantity' => 1,
            'fulfillment_type' => 'Pickup',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'time_slot' => '14:00',
            'payment_method' => 'COD',
        ];

        $this->postJson('/customer/custom-order/place', array_merge($submission, ['payment_method' => 'invalid']))
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $this->post('/customer/custom-order/place', $submission)->assertRedirect();
        $this->assertFalse(session()->has('error'), session('error') ?? '');
        $order = DB::table('orders')->first();
        $this->assertSame('COP', $order->payment_method);
        $this->assertSame('Pickup', $order->fulfillment_type);
        $this->assertSame('Pending Review', $order->status);
        $this->assertSame('Unpaid', $order->payment_status);
    }

    public function test_custom_cash_delivery_is_saved_as_cod_and_remains_pending_review(): void
    {
        $this->prepareFixture();
        $this->withSession(['user' => [
            'id' => 'TESTBUYER',
            'role' => 'customer',
            'fullname' => 'Test Buyer',
        ]]);

        $this->post('/customer/custom-order/place', [
            'shop_slug' => 'test-cake-shop',
            'cake_name' => 'Birthday Cake',
            'quantity' => 1,
            'fulfillment_type' => 'Delivery',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'time_slot' => '14:00',
            'payment_method' => 'COP',
            'delivery_zone' => 'Poblacion',
            'address' => 'House 12, Rizal Street, Poblacion, Bautista, Near school',
            'latitude' => '15.8095',
            'longitude' => '120.4988',
        ])->assertRedirect();

        $this->assertFalse(session()->has('error'), session('error') ?? '');
        $order = DB::table('orders')->first();
        $this->assertSame('COD', $order->payment_method);
        $this->assertSame('Delivery', $order->fulfillment_type);
        $this->assertSame('Pending Review', $order->status);
        $this->assertSame('Unpaid', $order->payment_status);
    }

    public function test_gcash_checkout_requires_full_payment_before_seller_confirmation(): void
    {
        $this->prepareFixture();
        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 1]);
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])
            ->assertRedirect(route('customer.checkout'));

        $this->post('/customer/checkout/place', [
            'fulfillment_type' => 'Pickup',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:00',
            'payment_method' => 'GCash',
        ])->assertRedirect();

        $this->assertFalse(session()->has('error'), session('error') ?? '');
        $order = DB::table('orders')->first();
        $this->assertSame('GCash', $order->payment_method);
        $this->assertSame('Awaiting Deposit', $order->status);
        $this->assertSame(1, (int) $order->deposit_required);
        $this->assertEquals((float) $order->total_price, (float) $order->deposit_amount);
        $this->assertSame('pending', $order->deposit_status);
        $this->assertStringContainsString('full amount via GCash', session('msg'));
        $this->assertSame($order->id, session('last_order_id'));
        $this->get('/track/'.$order->track_code)
            ->assertOk()
            ->assertSee('Pay Full Amount via GCash');
    }

    public function test_full_payment_no_show_queues_only_the_amount_above_fifty_percent_for_refund(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('GCash');
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'Pickup',
            'deposit_status' => 'paid',
            'payment_status' => 'Paid',
            'deposit_amount' => $order->total_price,
            'paid_at' => now(),
        ]);
        $order = DB::table('orders')->where('id', $order->id)->first();

        $refunds = app(OrderRefundService::class);
        $refund = $refunds->recordNoShow($order, 'Customer missed the agreed pickup time.', 'seller');

        $this->assertNotNull($refund);
        $this->assertEquals(round((float) $order->total_price * 0.5, 2), (float) $refund->refund_amount);
        $this->assertEquals((float) $order->total_price, (float) $refund->payment_amount_paid);
        $this->assertSame('No Show', DB::table('orders')->where('id', $order->id)->value('status'));

        $noShowOrder = DB::table('orders')->where('id', $order->id)->first();
        $refunds->markRefunded($noShowOrder, $refund, 'receipt.jpg', 'REF-TEST-1', 'Refund issued.', 'admin', 'TESTADMIN', 'Test Buyer', '+639172222222');

        $this->assertSame('No Show', DB::table('orders')->where('id', $order->id)->value('status'));
        $this->assertSame('refunded', DB::table('order_refunds')->where('id', $refund->id)->value('status'));
    }

    public function test_no_show_retains_a_paid_fifty_percent_deposit_without_opening_a_refund(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('COP');
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'Pickup',
            'deposit_status' => 'paid',
            'payment_status' => 'Partial Payment',
        ]);
        $order = DB::table('orders')->where('id', $order->id)->first();

        $refund = app(OrderRefundService::class)
            ->recordNoShow($order, 'Customer missed the agreed pickup time.', 'seller');

        $this->assertNull($refund);
        $this->assertSame('No Show', DB::table('orders')->where('id', $order->id)->value('status'));
        $this->assertDatabaseCount('order_refunds', 0);
    }

    public function test_rider_cannot_record_a_delivery_no_show_while_a_refund_request_is_pending(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('GCash');
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'Out for Delivery',
            'rider_token' => 'TEST-RIDER-TOKEN',
            'rider_assignment_status' => 'accepted',
            'deposit_status' => 'paid',
            'payment_status' => 'Paid',
            'deposit_amount' => $order->total_price,
            'paid_at' => now(),
            'cancel_status' => 'pending',
        ]);
        DB::table('order_refunds')->insert([
            'order_id' => $order->id,
            'shop_id' => $order->shop_id,
            'track_code' => $order->track_code,
            'refund_amount' => $order->total_price,
            'payment_amount_paid' => $order->total_price,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/rider/'.$order->id.'/TEST-RIDER-TOKEN/issue', [
            'issue_type' => 'no_show',
            'note' => 'Customer missed the agreed delivery time.',
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'Resolve the existing cancellation or refund request before recording a no-show.');

        $this->assertSame('Out for Delivery', DB::table('orders')->where('id', $order->id)->value('status'));
    }

    public function test_seller_pickup_screen_exposes_the_paid_no_show_action(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('COP');
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'Pickup',
            'deposit_status' => 'paid',
            'payment_status' => 'Partial Payment',
        ]);

        $this->withSession(['user' => ['id' => 'TESTSELLER', 'role' => 'seller']])
            ->get('/seller/orders')
            ->assertOk()
            ->assertSee('Customer no-show')
            ->assertSee('Record No-Show')
            ->assertSee('pickup time and contact attempts');
    }

    public function test_rider_delivery_screen_exposes_no_show_reporting(): void
    {
        $this->prepareFixture();
        $riderId = DB::table('riders')->insertGetId([
            'shop_id' => 'TESTSHOP',
            'name' => 'Test Rider',
            'phone' => '09171111111',
            'is_active' => true,
        ]);
        $order = $this->createRegularOrder('COD');
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'Out for Delivery',
            'fulfillment_type' => 'Delivery',
            'rider_id' => $riderId,
            'rider_token' => 'TEST-RIDER-TOKEN',
            'rider_assignment_status' => 'accepted',
            'deposit_status' => 'paid',
            'payment_status' => 'Partial Payment',
        ]);

        $this->get('/rider/'.$order->id.'/TEST-RIDER-TOKEN')
            ->assertOk()
            ->assertSee('Customer No-Show')
            ->assertSee('arrival time and contact attempts');
    }

    public function test_rider_dashboard_shows_pending_request_and_feed_detects_changes(): void
    {
        $this->prepareFixture();
        $riderId = DB::table('riders')->insertGetId([
            'shop_id' => 'TESTSHOP',
            'name' => 'Juan Rider',
            'phone' => '09172222222',
            'is_active' => true,
        ]);
        $session = ['rider' => ['id' => $riderId]];
        $order = $this->createRegularOrder('COD');

        $this->withSession($session)->getJson('/rider/dashboard/feed')
            ->assertOk()
            ->assertJsonPath('pending_count', 0);
        $emptySignature = $this->withSession($session)->getJson('/rider/dashboard/feed')->json('signature');

        DB::table('orders')->where('id', $order->id)->update([
            'fulfillment_type' => 'Delivery',
            'status' => 'Ready for Rider',
            'rider_id' => $riderId,
            'rider_token' => 'DASH-TOKEN',
            'rider_assignment_status' => 'pending',
            'rider_assignment_expires_at' => now()->addMinutes(10),
        ]);

        $feed = $this->withSession($session)->getJson('/rider/dashboard/feed')->assertOk();
        $feed->assertJsonPath('pending_count', 1);
        $this->assertNotSame($emptySignature, $feed->json('signature'));

        $this->withSession($session)->get('/rider/dashboard')
            ->assertOk()
            ->assertSee('Needs your response')
            ->assertSee('Accept delivery')
            ->assertSee('Respond within')
            ->assertSee('id="liveRegion"', false);
    }

    public function test_rider_pin_opens_order_while_assignment_is_pending(): void
    {
        $this->prepareFixture();
        $riderId = DB::table('riders')->insertGetId([
            'shop_id' => 'TESTSHOP',
            'name' => 'Juan Rider',
            'phone' => '09173333333',
            'is_active' => true,
        ]);
        $order = $this->createRegularOrder('COD');
        DB::table('orders')->where('id', $order->id)->update([
            'fulfillment_type' => 'Delivery',
            'status' => 'Ready for Rider',
            'rider_id' => $riderId,
            'rider_token' => 'PINTOKEN1',
            'rider_pin' => 'A3K7X2MQ',
            'rider_assignment_status' => 'pending',
            'rider_assignment_expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/rider/pin', ['pin' => 'a3k7x2mq'])
            ->assertRedirect(route('rider.show', [$order->id, 'PINTOKEN1']));
    }

    public function test_delivery_checkout_saves_default_address_when_checked(): void
    {
        $this->prepareFixture();
        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 1]);
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!']);

        $this->post('/customer/checkout/place', [
            'fulfillment_type' => 'Delivery',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:00',
            'payment_method' => 'COD',
            'address' => '12 Rizal St, Barangay Test, Test City',
            'delivery_zone' => 'Test',
            'latitude' => '14.5995',
            'longitude' => '120.9842',
            'save_default_address' => 'on',
        ])->assertRedirect();

        $this->assertNull(session('error'), (string) session('error'));
        $this->assertSame(1, DB::table('user_addresses')->count());
        $this->assertSame('12 Rizal St, Barangay Test, Test City', DB::table('user_addresses')->value('full_address'));
    }

    public function test_rider_dashboard_feed_requires_rider_session(): void
    {
        $this->prepareFixture();
        $this->getJson('/rider/dashboard/feed')->assertStatus(401);
    }

    public function test_registered_customer_cannot_accept_a_custom_price_before_seller_approval(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('COP');
        DB::table('orders')->where('id', $order->id)->update([
            'order_type' => 'custom',
            'status' => 'Pending Review',
        ]);
        DB::table('custom_orders')->insert([
            'id' => 'CUSTOMPENDING',
            'order_id' => $order->id,
            'shop_id' => $order->shop_id,
            'user_id' => 'TESTBUYER',
            'cake_name' => 'Pending Custom Cake',
            'review_status' => 'pending',
            'admin_price' => 650,
            'price_confirmed' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['user' => ['id' => 'TESTBUYER', 'role' => 'customer', 'fullname' => 'Test Buyer']])
            ->post('/customer/custom-orders/CUSTOMPENDING/accept-price')
            ->assertRedirect()
            ->assertSessionHas('err', 'The seller must approve and set the final price before you can accept it.');

        $this->assertSame('pending', DB::table('custom_orders')->where('id', 'CUSTOMPENDING')->value('price_confirmed'));
    }

    public function test_guest_cannot_accept_a_custom_price_before_seller_approval(): void
    {
        $this->prepareFixture();
        $order = $this->createRegularOrder('COP');
        DB::table('orders')->where('id', $order->id)->update([
            'user_id' => null,
            'order_type' => 'custom',
            'status' => 'Pending Review',
            'guest_name' => 'Guest Buyer',
            'guest_phone' => '09174445555',
        ]);
        DB::table('custom_orders')->insert([
            'id' => 'GUESTPENDING',
            'order_id' => $order->id,
            'shop_id' => $order->shop_id,
            'guest_name' => 'Guest Buyer',
            'guest_phone' => '09174445555',
            'cake_name' => 'Pending Guest Cake',
            'review_status' => 'pending',
            'admin_price' => 650,
            'price_confirmed' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['user' => null, 'guest_track_code' => $order->track_code])
            ->post('/custom-order/GUESTPENDING/accept-price')
            ->assertRedirect()
            ->assertSessionHas('err', 'The seller must approve and set the final price before you can accept it.');

        $this->assertSame('pending', DB::table('custom_orders')->where('id', 'GUESTPENDING')->value('price_confirmed'));
    }

    public function test_custom_cart_checkout_payment_overrides_draft_method_and_normalizes_cash(): void
    {
        $this->prepareFixture();
        $item = (object) [
            'meta' => json_encode([
                'cart_type' => 'custom_cake',
                'shop_id' => 'TESTSHOP',
                'cake_name' => 'Cart Custom Cake',
                'schedule_date' => now()->addDays(10)->toDateString(),
                'schedule_time' => '14:00',
                'time_slot' => '14:00',
                'fulfillment_type' => 'Pickup',
                'fulfillment_hold_expires_at' => now()->addMinutes(10)->toDateTimeString(),
                'estimated_total' => 1200,
                'payment_method' => 'GCash',
            ]),
            'shop_id' => 'TESTSHOP',
            'quantity' => 1,
            'final_unit_price_snapshot' => 1200,
            'product_id' => 'TESTCAKE',
        ];

        $result = app(\App\Services\CustomCartOrderService::class)->createFromCartItem($item, [
            'user_id' => 'TESTBUYER',
            'customer_name' => 'Test Buyer',
            'payment_method' => 'COD',
        ]);

        $this->assertTrue($result['ok'], $result['message'] ?? '');
        $order = DB::table('orders')->where('id', $result['order_id'])->first();
        $this->assertSame('COP', $order->payment_method);
        $this->assertSame('Pending Review', $order->status);
        $this->assertSame('Unpaid', $order->payment_status);
    }

    public function test_landing_feedback_and_role_based_themes_render(): void
    {
        $this->prepareFixture();
        $this->get('/')->assertOk()
            ->assertSee('Browse our handcrafted cakes')
            ->assertDontSee('Forgot tracking code?')
            ->assertDontSee(route('track.recover'));

        $this->withSession(['user' => [
            'id' => 'TESTBUYER',
            'role' => 'customer',
            'fullname' => 'Test Buyer',
        ]])->get('/')->assertOk()->assertSee('My Orders')->assertDontSee('Forgot tracking code?');

        $this->withSession(['user' => null])->get('/feedback')->assertOk();

        Route::get('/_test/theme-layout', fn () => view('layouts.app'))->name('test.theme-layout');
        DB::table('platform_settings')->where('id', 1)->update(['platform_primary_color' => '#2468AC']);
        DB::table('site_settings')->where('id', 1)->update([
            'primary_color' => '#13579B',
            'bg_type' => 'color',
            'bg_color' => '#F4E6D2',
        ]);
        DB::table('shops')->where('id', 'TESTSHOP')->update([
            'seller_id' => 'TESTSELLER',
            'theme_color' => '#BD3812',
        ]);

        $this->get('/')->assertOk()->assertSee('--choc         : #2468AC;');
        $this->withSession(['user' => ['id' => 'TESTADMIN', 'role' => 'superadmin']])
            ->get('/_test/theme-layout')->assertOk()->assertSee('--primary:        #2468AC;');
        $this->withSession(['user' => ['id' => 'TESTADMIN', 'role' => 'admin']])
            ->get('/_test/theme-layout')->assertOk()
            ->assertSee('--primary:        #13579B;')
            ->assertSee('body { background: #F4E6D2; }');
        DB::table('site_settings')->where('id', 1)->update([
            'bg_type' => 'gradient',
            'gradient_start' => '#E5F1FF',
            'gradient_end' => '#FFF0D9',
        ]);
        $this->withSession(['user' => ['id' => 'TESTSELLER', 'role' => 'seller']])
            ->get('/_test/theme-layout')->assertOk()
            ->assertSee('--primary:        #BD3812;')
            ->assertSee('body { background: linear-gradient(135deg, #E5F1FF 0%, #FFF0D9 100%); }');
    }

    public function test_delivery_coverage_options_and_stored_custom_draft_are_shop_scoped(): void
    {
        $this->prepareFixture();
        DB::table('shops')->where('id', 'TESTSHOP')->update([
            'city' => 'Bautista',
            'address' => 'Bautista, Pangasinan',
        ]);
        DB::table('psgc_provinces')->insert([
            ['code' => '0155000000', 'name' => 'Pangasinan'],
            ['code' => '0369000000', 'name' => 'Tarlac'],
        ]);
        DB::table('psgc_cities_municipalities')->insert([
            ['code' => '0155110000', 'name' => 'Bautista', 'province_code' => '0155000000', 'province_name' => 'Pangasinan'],
            ['code' => '0369160000', 'name' => 'Camiling', 'province_code' => '0369000000', 'province_name' => 'Tarlac'],
        ]);
        DB::table('psgc_barangays')->insert([
            ['code' => '015511001', 'name' => 'Poblacion East', 'city_municipality_code' => '0155110000', 'city_municipality_name' => 'Bautista', 'province_code' => '0155000000', 'province_name' => 'Pangasinan'],
            ['code' => '036916001', 'name' => 'Poblacion A', 'city_municipality_code' => '0369160000', 'city_municipality_name' => 'Camiling', 'province_code' => '0369000000', 'province_name' => 'Tarlac'],
        ]);
        DB::table('delivery_zones')->insert([
            'shop_id' => 'TESTSHOP', 'barangay' => 'Poblacion East',
            'zone_address' => 'Poblacion East, Bautista, Pangasinan, Philippines',
            'lat' => 15.8100, 'lng' => 120.5000, 'is_active' => 1,
        ]);
        DB::table('site_settings')->where('shop_id', 'TESTSHOP')->update(['delivery_coverage_radius' => 5000]);

        $service = app(\App\Services\DeliveryLocationValidationService::class);
        $coverage = $service->coverageOptions('TESTSHOP');
        $this->assertTrue($coverage['strict']);
        $this->assertSame('Pangasinan', $coverage['provinces'][0]['name']);
        $this->assertSame('Bautista', $coverage['provinces'][0]['cities'][0]['name']);
        $this->assertSame('Poblacion East', $coverage['provinces'][0]['cities'][0]['barangays'][0]['name']);

        DB::table('delivery_zones')->insert([
            'shop_id' => 'TESTSHOP', 'barangay' => 'Poblacion A',
            'zone_address' => 'Poblacion A, Camiling, Tarlac, Philippines',
            'lat' => 15.6900, 'lng' => 120.4100, 'is_active' => 1,
        ]);
        $multiProvinceCoverage = $service->coverageOptions('TESTSHOP');
        $this->assertTrue($multiProvinceCoverage['strict']);
        $this->assertCount(2, $multiProvinceCoverage['provinces']);

        DB::table('delivery_zones')->insert([
            'shop_id' => 'TESTSHOP', 'barangay' => 'Legacy Sitio Name',
            'zone_address' => 'Legacy Sitio Name, Bautista, Pangasinan, Philippines',
            'lat' => 15.8200, 'lng' => 120.5100, 'is_active' => 1,
        ]);
        DB::table('delivery_zones')->insert([
            'shop_id' => 'TESTSHOP', 'barangay' => 'Map Drawn Legacy Zone',
            'zone_address' => null,
            'lat' => 15.8250, 'lng' => 120.5150, 'is_active' => 1,
        ]);
        $partialCoverage = $service->coverageOptions('TESTSHOP');
        $this->assertFalse($partialCoverage['strict']);
        $this->assertTrue($partialCoverage['province_strict']);
        $this->assertTrue($partialCoverage['city_strict']);
        $this->assertCount(2, $partialCoverage['provinces']);

        $covered = $service->validateStoredDelivery([
            'fulfillment_type' => 'Delivery',
            'address' => 'House 12 Rizal Street Barangay Poblacion East Bautista Pangasinan',
            'latitude' => 15.8101, 'longitude' => 120.5001, 'delivery_zone' => 'Poblacion East',
        ], 'TESTSHOP');
        $this->assertTrue($covered['ok']);
        $quote = $service->quoteStoredDelivery([
            'fulfillment_type' => 'Delivery',
            'address' => 'House 12 Rizal Street Barangay Poblacion East Bautista Pangasinan',
            'latitude' => 15.8101, 'longitude' => 120.5001, 'delivery_zone' => 'Poblacion East',
        ], 'TESTSHOP');
        $this->assertTrue($quote['ok']);
        $this->assertSame('Poblacion East', $quote['zone']);
        $this->assertGreaterThanOrEqual(0, $quote['delivery_fee']);

        $outside = $service->validateStoredDelivery([
            'fulfillment_type' => 'Delivery',
            'address' => 'House 99 Remote Street Barangay Far Away Other Province',
            'latitude' => 16.5000, 'longitude' => 121.5000, 'delivery_zone' => 'Far Away',
        ], 'TESTSHOP');
        $this->assertFalse($outside['ok']);

        $pickup = $service->validateStoredDelivery(['fulfillment_type' => 'Pickup'], 'TESTSHOP');
        $this->assertTrue($pickup['ok']);

        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 1]);
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])
            ->assertRedirect(route('customer.checkout'));
        $this->get('/customer/checkout')->assertOk()
            ->assertSee('Seller delivery coverage')
            ->assertSee('Pickup</strong> is available to customers from any location.', false)
            ->assertSee('"strict":false', false)
            ->assertSee('"province_strict":true', false)
            ->assertSee('Pangasinan')
            ->assertSee('Tarlac');
    }

    public function test_surprise_delivery_forces_sender_first_and_uses_recipient_as_backup(): void
    {
        $this->prepareFixture();
        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 1]);
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])
            ->assertRedirect(route('customer.checkout'));
        $this->get('/customer/checkout')->assertOk()
            ->assertDontSee('Contact Rule')
            ->assertSee('Contact sender first.');

        $this->post('/customer/checkout/place', [
            'fulfillment_type' => 'Delivery',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:00',
            'payment_method' => 'GCash',
            'delivery_zone' => 'Poblacion',
            'address' => 'House 12 Rizal Street Poblacion Bautista Pangasinan near school',
            'latitude' => '15.8095',
            'longitude' => '120.4988',
            'is_surprise_delivery' => '1',
            'recipient_name' => 'Gift Recipient',
            'recipient_phone' => '09181234567',
            'surprise_contact_policy' => 'recipient_ok',
        ])->assertRedirect();

        $this->assertNull(session('error'), (string) session('error'));
        $order = DB::table('orders')->first();
        $this->assertSame('sender_first', $order->surprise_contact_policy);
        $contact = app(\App\Services\SurpriseContactService::class)->details($order);
        $this->assertSame('+639172222222', $contact['primary_phone']);
        $this->assertSame('09181234567', $contact['backup_phone']);

        $withoutRecipientPhone = app(\App\Services\SurpriseContactService::class)->details((object) [
            'user_id' => 'TESTBUYER', 'recipient_name' => 'Gift Recipient', 'recipient_phone' => null,
        ]);
        $this->assertSame('+639172222222', $withoutRecipientPhone['primary_phone']);
        $this->assertNull($withoutRecipientPhone['backup_phone']);
    }

    private function prepareFixture(): void
    {
        // The seller-document CHECK migration contains PostgreSQL-only SQL.
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (str_contains($file, 'add_upgrade_permit_to_seller_documents_enum')) continue;
            (require $file)->up();
        }
        DB::table('users')->insert([
            ['id' => 'TESTADMIN', 'fullname' => 'Test Admin', 'username' => 'testadmin', 'email' => 'admin@example.test', 'phone' => '+639171111111', 'password' => password_hash('TestPassword1!', PASSWORD_DEFAULT), 'role' => 'superadmin', 'is_verified' => 1],
            ['id' => 'TESTBUYER', 'fullname' => 'Test Buyer', 'username' => 'testbuyer', 'email' => 'buyer@example.test', 'phone' => '+639172222222', 'password' => password_hash('TestPassword1!', PASSWORD_DEFAULT), 'role' => 'customer', 'is_verified' => 1],
            ['id' => 'TESTSELLER', 'fullname' => 'Test Seller', 'username' => 'testseller', 'email' => 'seller@example.test', 'phone' => '+639173333333', 'password' => password_hash('TestPassword1!', PASSWORD_DEFAULT), 'role' => 'seller', 'is_verified' => 1],
        ]);
        DB::table('shops')->insert(['id' => 'TESTSHOP', 'seller_id' => 'TESTSELLER', 'shop_name' => 'Test Cake Shop', 'shop_slug' => 'test-cake-shop', 'status' => 'approved', 'tier' => 'verified']);
        DB::table('products')->insert(['id' => 'TESTCAKE', 'shop_id' => 'TESTSHOP', 'name' => 'Test Cake', 'price' => 500, 'classification' => 'Cake', 'is_available' => 1, 'available_quantity' => 10]);
        DB::table('site_settings')->insert(['shop_id' => 'TESTSHOP', 'site_title' => 'Test Cake Shop', 'daily_max_cakes' => 30]);
    }

    private function createRegularOrder(string $paymentMethod): object
    {
        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 1]);
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])
            ->assertRedirect(route('customer.checkout'));

        $this->post('/customer/checkout/place', [
            'fulfillment_type' => 'Pickup',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:00',
            'payment_method' => $paymentMethod,
        ])->assertRedirect();

        $this->assertFalse(session()->has('error'), session('error') ?? '');
        return DB::table('orders')->first();
    }
}
