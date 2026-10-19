<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseJourneyRenderingTest extends TestCase
{
    public function test_catalog_authentication_cart_and_checkout_destinations_render(): void
    {
        $this->withoutExceptionHandling();
        // The existing seller-document CHECK migration uses PostgreSQL-only SQL.
        // It does not affect purchasing; use all other migrations in this SQLite fixture.
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

        $this->get('/catalog')->assertOk()->assertSee('Buy Now');
        $this->get('/shop/test-cake-shop')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->post('/catalog/select', ['product_id' => 'TESTCAKE', 'quantity' => 2])->assertRedirect(route('login'));
        $this->post('/login', ['username' => 'testbuyer', 'password' => 'TestPassword1!'])->assertRedirect(route('customer.checkout'));
        $this->get('/customer/checkout')->assertOk()->assertSee('Test Cake');
        $this->get('/customer/custom-order?shop=test-cake-shop')->assertOk();
        $this->post('/cart/add', ['product_id' => 'TESTCAKE', 'quantity' => 1])->assertRedirect(route('customer.cart'));
        $this->get('/customer/cart')->assertOk()->assertSee('Test Cake');
        $this->get('/shop/test-cake-shop')->assertOk();
        $this->assertDatabaseCount('orders', 0);

        $submission = [
            'fulfillment_type' => 'Pickup', 'schedule_date' => now()->addDays(5)->toDateString(),
            'schedule_time' => '14:00', 'payment_method' => 'COD', '_submit_token' => 'test-purchase-once',
            'latitude' => '', 'longitude' => '',
        ];
        $this->post('/customer/checkout/place', $submission)->assertRedirect();
        $this->assertFalse(session()->has('error'), session('error') ?? '');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['user_id' => 'TESTBUYER', 'product_id' => 'TESTCAKE', 'quantity' => 2]);
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
}
