<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PurchaseJourneyRenderingTest extends TestCase
{
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
        $this->get('/customer/cart')->assertOk()->assertSee('Test Cake');
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
        $this->assertSame('Pending', $cashPickup->status);
        $this->assertSame(0, (int) $cashPickup->deposit_required);
        $this->assertNull($cashPickup->deposit_amount);
        $this->assertNull($cashPickup->deposit_status);
        $this->assertStringContainsString('Pay cash when you pick up', session('msg'));
        $this->post('/customer/checkout/place', $submission)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $trackCode = DB::table('orders')->value('track_code');
        $this->get('/track/'.$trackCode)->assertOk();
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

    public function test_cash_delivery_is_cod_without_an_upfront_deposit(): void
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
        $this->assertSame('Pending', $order->status);
        $this->assertSame(0, (int) $order->deposit_required);
        $this->assertNull($order->deposit_amount);
        $this->assertNull($order->deposit_status);
        $this->assertStringContainsString('Pay cash when your order arrives', session('msg'));
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

    public function test_gcash_checkout_remains_online_payment_without_cash_deposit_state(): void
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
        $this->assertSame('Pending', $order->status);
        $this->assertSame(0, (int) $order->deposit_required);
        $this->assertNull($order->deposit_amount);
        $this->assertNull($order->deposit_status);
        $this->assertSame($order->id, session('last_order_id'));
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
        ]);
        DB::table('shops')->insert(['id' => 'TESTSHOP', 'shop_name' => 'Test Cake Shop', 'shop_slug' => 'test-cake-shop', 'status' => 'approved', 'tier' => 'verified']);
        DB::table('products')->insert(['id' => 'TESTCAKE', 'shop_id' => 'TESTSHOP', 'name' => 'Test Cake', 'price' => 500, 'classification' => 'Cake', 'is_available' => 1, 'available_quantity' => 10]);
        DB::table('site_settings')->insert(['shop_id' => 'TESTSHOP', 'site_title' => 'Test Cake Shop', 'daily_max_cakes' => 30]);
    }
}
