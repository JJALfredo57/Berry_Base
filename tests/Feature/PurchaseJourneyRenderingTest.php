<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PurchaseJourneyRenderingTest extends TestCase
{
    public function test_catalog_authentication_cart_and_checkout_destinations_render(): void
    {
        $this->withoutExceptionHandling();
        $this->prepareFixture();

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
