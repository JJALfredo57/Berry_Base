<?php

namespace Tests\Feature;

use App\Services\CartService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountRequiredPurchaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Schema::create('users', function (Blueprint $t) {
            $t->string('id')->primary();
            foreach (['fullname', 'username', 'email', 'phone', 'password', 'role'] as $field) {
                $t->string($field);
            }
            $t->boolean('is_verified')->default(true);
            $t->string('profile_photo')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('shop_slug');
            $t->string('status');
            $t->string('tier');
        });
        Schema::create('products', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('shop_id')->nullable();
            $t->string('name');
            $t->decimal('price', 10, 2);
            $t->boolean('is_available')->default(true);
            $t->integer('available_quantity')->default(10);
            $t->timestamp('archived_at')->nullable();
        });
        Schema::create('customer_carts', function (Blueprint $t) {
            $t->id();
            $t->string('user_id')->nullable();
            $t->string('session_id')->nullable();
            $t->string('shop_id')->nullable();
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('customer_cart_items', function (Blueprint $t) {
            $t->id();
            $t->integer('cart_id');
            $t->string('shop_id')->nullable();
            $t->string('product_id');
            $t->string('selected_size')->nullable();
            $t->string('custom_note')->nullable();
            $t->integer('quantity');
            foreach (['unit_price_snapshot', 'final_unit_price_snapshot', 'discount_amount_snapshot'] as $field) {
                $t->decimal($field, 10, 2)->default(0);
            }
            $t->string('discount_label_snapshot')->nullable();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('user_id')->nullable();
            $t->string('track_code');
        });
        DB::table('shops')->insert(['id' => 'SHOP1', 'shop_slug' => 'cake-shop', 'status' => 'approved', 'tier' => 'verified']);
        DB::table('products')->insert(['id' => 'CAKE1', 'name' => 'Chocolate Cake', 'shop_id' => 'SHOP1', 'price' => 500]);
        DB::table('users')->insert([
            'id' => 'CUSTOMER1', 'fullname' => 'Test Customer', 'username' => 'testcustomer',
            'email' => 'customer@example.test', 'phone' => '+639171234567',
            'password' => password_hash('TestPassword1!', PASSWORD_DEFAULT), 'role' => 'customer',
        ]);
    }

    private function loginCustomer()
    {
        return $this->post('/login', ['username' => 'testcustomer', 'password' => 'TestPassword1!']);
    }

    public function test_buy_now_survives_failed_login_and_resumes_without_creating_an_order(): void
    {
        $this->post('/catalog/select', ['product_id' => 'CAKE1', 'quantity' => 2, 'dedication' => 'Happy birthday'])
            ->assertRedirect(route('login'))->assertSessionHas('purchase_intent.data.quantity', 2);
        $this->post('/login', ['username' => 'testcustomer', 'password' => 'wrong'])->assertSessionHas('purchase_intent');
        $this->loginCustomer()->assertRedirect(route('customer.checkout'))
            ->assertSessionHas('checkout.product_id', 'CAKE1')->assertSessionHas('checkout.quantity', 2)
            ->assertSessionMissing('purchase_intent');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customer_cart_items', 0);
    }

    public function test_add_to_cart_requires_login_and_is_not_repeated_on_resume(): void
    {
        $this->post('/cart/add', ['product_id' => 'CAKE1', 'quantity' => 2])->assertRedirect(route('login'));
        $this->assertDatabaseCount('customer_carts', 0);
        $this->loginCustomer()->assertRedirect(route('customer.cart'));
        $this->assertDatabaseHas('customer_cart_items', ['product_id' => 'CAKE1', 'quantity' => 2]);
        $this->get('/purchase/resume')->assertRedirect(route('customer.catalog'));
        $this->assertDatabaseCount('customer_cart_items', 1);
        $this->assertDatabaseHas('customer_carts', ['user_id' => 'CUSTOMER1']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_registration_verifies_signs_in_and_restores_buy_now(): void
    {
        $this->post('/catalog/select', ['product_id' => 'CAKE1', 'quantity' => 1]);
        $this->withSession(['reg_pending' => [
            'fullname' => 'New Customer', 'username' => 'newcustomer', 'email' => 'new@example.test',
            'phone' => '+639181234567', 'otp' => '123456', 'expires' => time() + 600,
        ]])->post('/register/verify-otp', ['otp' => '123456', 'password' => 'TestPassword1!', 'confirm_password' => 'TestPassword1!'])
            ->assertRedirect(route('customer.checkout'))->assertSessionHas('user.role', 'customer')
            ->assertSessionHas('checkout.product_id', 'CAKE1')->assertSessionMissing('reg_pending');
        $this->assertDatabaseHas('users', ['username' => 'newcustomer', 'is_verified' => 1]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_out_of_stock_selection_is_not_placed_after_login(): void
    {
        $this->post('/catalog/select', ['product_id' => 'CAKE1', 'quantity' => 2]);
        DB::table('products')->where('id', 'CAKE1')->update(['available_quantity' => 0]);
        $this->loginCustomer()->assertRedirect(route('customer.catalog'))->assertSessionHas('error')->assertSessionMissing('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_selection_is_not_restored(): void
    {
        $this->post('/catalog/select', ['product_id' => 'CAKE1']);
        $this->travel(31)->minutes();
        $this->loginCustomer()->assertRedirect(route('customer.catalog'))->assertSessionHas('error')->assertSessionMissing('checkout');
    }

    public function test_custom_cake_authentication_keeps_the_selected_shop(): void
    {
        $this->get('/custom-order?shop=cake-shop')->assertRedirect(route('login'));
        $this->loginCustomer()->assertRedirect(route('customer.custom_order', ['shop' => 'cake-shop']));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_new_order_submission_endpoints_are_removed_and_cart_requires_authentication(): void
    {
        foreach (['/checkout/place', '/checkout/send-otp'] as $url) {
            $this->post($url)->assertNotFound();
        }
        $this->post('/custom-order/place')->assertStatus(302);
        $this->post('/custom-order/send-otp')->assertNotFound();
        foreach (['/cart/items/1/update', '/cart/items/1/checkout', '/cart/shops/SHOP1/checkout'] as $url) {
            $this->post($url)->assertRedirect(route('login'));
        }
        $this->post('/order-requests', ['product_id' => 'CAKE1'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customer_carts', 0);
    }

    public function test_json_guest_order_endpoint_is_removed_and_cart_requires_login(): void
    {
        $this->postJson('/checkout/place')->assertNotFound();
        $this->postJson('/cart/add', ['product_id' => 'CAKE1'])->assertUnauthorized()
            ->assertJsonPath('redirect_url', route('login'));
    }

    public function test_guests_can_still_access_legacy_tracking_and_public_feedback(): void
    {
        $this->assertNotNull(route('track.order', ['trackCode' => 'OLDTRACK']));
        $this->get('/track/recover')->assertOk();
        $this->get('/feedback')->assertOk();
    }

    public function test_account_order_tracking_requires_its_owner(): void
    {
        DB::table('orders')->insert(['id' => 'ORDER1', 'user_id' => 'CUSTOMER1', 'track_code' => 'ABCDEFGH']);
        $this->get('/track/ABCDEFGH')->assertRedirect(route('login'));
        $this->withSession(['user' => ['id' => 'OTHER', 'role' => 'customer']])->get('/track/ABCDEFGH')->assertForbidden();
    }

    public function test_visitor_cart_service_does_not_create_a_cart(): void
    {
        $this->assertNull(app(CartService::class)->cart(request(), null));
        $this->assertDatabaseCount('customer_carts', 0);
    }

    public function test_malformed_selection_is_a_validation_error(): void
    {
        $this->postJson('/catalog/select', ['product_id' => ['bad'], 'quantity' => -1])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_existing_cart_combines_matching_items_but_keeps_different_notes_separate(): void
    {
        $this->loginCustomer();
        $this->post('/customer/cart/add', ['product_id' => 'CAKE1', 'quantity' => 2, 'dedication' => 'Alice']);
        $this->post('/cart/add', ['product_id' => 'CAKE1', 'quantity' => 1, 'dedication' => 'Alice']);
        $this->post('/cart/add', ['product_id' => 'CAKE1', 'quantity' => 1, 'dedication' => 'Bob']);
        $this->assertDatabaseCount('customer_cart_items', 2);
        $this->assertDatabaseHas('customer_cart_items', ['quantity' => 3, 'custom_note' => 'Dedication: "Alice"']);
        $this->assertDatabaseHas('customer_cart_items', ['quantity' => 1, 'custom_note' => 'Dedication: "Bob"']);
    }

    public function test_cart_stock_conflict_preserves_existing_quantity(): void
    {
        $this->loginCustomer();
        $this->post('/cart/add', ['product_id' => 'CAKE1', 'quantity' => 8]);
        $this->post('/cart/add', ['product_id' => 'CAKE1', 'quantity' => 3])->assertSessionHas('error');
        $this->assertDatabaseHas('customer_cart_items', ['quantity' => 8]);
    }

    public function test_other_roles_cannot_create_customer_cart_items(): void
    {
        foreach (['seller', 'admin', 'superadmin'] as $role) {
            $this->withSession(['user' => ['id' => 'OTHER', 'role' => $role]])
                ->post('/cart/add', ['product_id' => 'CAKE1'])->assertRedirect(route('login'));
        }
        $this->withSession(['user' => null, 'rider' => ['id' => 1]])
            ->post('/cart/add', ['product_id' => 'CAKE1'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('customer_cart_items', 0);
    }

    public function test_invalid_registration_otp_keeps_selection_and_creates_no_account(): void
    {
        $this->post('/catalog/select', ['product_id' => 'CAKE1']);
        $this->withSession(['reg_pending' => ['otp' => '123456', 'expires' => time() + 600]])
            ->post('/register/verify-otp', ['otp' => '000000'])
            ->assertRedirect(route('register'))->assertSessionHas('purchase_intent')->assertSessionHas('error');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_repeated_concurrent_intent_does_not_add_the_same_item_twice(): void
    {
        $this->post('/cart/add', ['product_id' => 'CAKE1', 'quantity' => 2]);
        $intent = session('purchase_intent');
        $this->loginCustomer();
        $this->withSession(['purchase_intent' => $intent])->get('/purchase/resume')->assertRedirect(route('customer.cart'));
        $this->assertDatabaseCount('customer_cart_items', 1);
        $this->assertDatabaseHas('customer_cart_items', ['quantity' => 2]);
    }

    public function test_reauthentication_preserves_only_the_owning_customers_checkout(): void
    {
        $this->withSession(['checkout' => ['customer_id' => 'CUSTOMER1', 'product_id' => 'CAKE1', 'quantity' => 2]])
            ->get('/customer/checkout')->assertRedirect(route('login'));
        $this->loginCustomer()->assertRedirect(route('customer.checkout'))->assertSessionHas('checkout.quantity', 2);
    }

    public function test_malformed_checkout_and_registration_inputs_return_validation_errors(): void
    {
        $this->postJson('/register', ['fullname' => ['bad']])->assertUnprocessable();
        $this->loginCustomer();
        $this->postJson('/customer/checkout/place', ['selected_size' => ['bad'], 'payment_method' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }
}
