<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiderStaleSessionTest extends TestCase
{
    private const INVALID_MESSAGE = 'This rider account no longer exists or is inactive. Please sign in with an active rider account.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->boolean('is_verified')->default(true);
        });
        Schema::create('riders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('shop_id')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('login_pin_hash')->nullable();
            $table->boolean('password_must_change')->default(false);
            $table->boolean('is_active')->default(true);
        });
    }

    public function test_login_page_clears_an_inactive_rider_session(): void
    {
        DB::table('riders')->insert(['id' => 1, 'name' => 'Archived Rider', 'is_active' => false]);

        $this->withSession(['rider' => ['id' => 1, 'name' => 'Archived Rider']])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('BerryBase Login')
            ->assertSessionMissing('rider')
            ->assertSessionHas('error', self::INVALID_MESSAGE);
    }

    public function test_dashboard_clears_a_deleted_rider_session_and_redirects_to_login(): void
    {
        $this->withSession(['rider' => ['id' => 404, 'name' => 'Deleted Rider']])
            ->get(route('rider.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('rider')
            ->assertSessionHas('error', self::INVALID_MESSAGE);
    }

    public function test_dashboard_feed_returns_login_destination_for_a_stale_session(): void
    {
        DB::table('riders')->insert(['id' => 2, 'name' => 'Inactive Rider', 'is_active' => false]);

        $this->withSession(['rider' => ['id' => 2]])
            ->getJson(route('rider.dashboard.feed'))
            ->assertUnauthorized()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', self::INVALID_MESSAGE)
            ->assertJsonPath('redirect_url', route('login'))
            ->assertSessionMissing('rider');
    }

    public function test_login_page_keeps_an_active_rider_session(): void
    {
        DB::table('riders')->insert(['id' => 3, 'name' => 'Active Rider', 'is_active' => true]);

        $this->withSession(['rider' => ['id' => 3, 'name' => 'Active Rider']])
            ->get(route('login'))
            ->assertRedirect(route('rider.dashboard'))
            ->assertSessionHas('rider.id', 3);
    }

    public function test_fresh_rider_can_login_after_the_old_session_is_cleared(): void
    {
        DB::table('riders')->insert([
            'id' => 4,
            'shop_id' => 'new-shop',
            'name' => 'Fresh Rider',
            'phone' => '+639171234567',
            'login_pin_hash' => Hash::make('654321'),
            'password_must_change' => true,
            'is_active' => true,
        ]);

        $this->withSession(['rider' => ['id' => 999, 'name' => 'Old Rider']])
            ->get(route('login'))
            ->assertOk()
            ->assertSessionMissing('rider');

        $this->post(route('login.post'), [
            'username' => '09171234567',
            'password' => '654321',
        ])->assertRedirect(route('rider.password.setup'))
            ->assertSessionHas('rider.id', 4);
    }
}
