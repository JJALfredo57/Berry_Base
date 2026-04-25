<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->string('id', 12)->primary();
                $t->string('fullname', 100);
                $t->string('email', 150)->unique();
                $t->string('phone', 20)->nullable();
                $t->string('username', 60)->nullable()->unique();
                $t->string('password');
                $t->enum('role', ['admin','customer','seller','superadmin'])->default('customer');
                $t->boolean('is_verified')->default(false);
                $t->string('profile_photo')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $t) {
                $t->string('email')->index();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->nullable();
                $t->string('barangay', 100);
                $t->decimal('fee', 8, 2)->default(0);
                $t->string('zone_type', 30)->nullable();
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('cake_addon_categories')) {
            Schema::create('cake_addon_categories', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->nullable();
                $t->string('name', 80);
                $t->string('icon', 20)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('cake_addons')) {
            Schema::create('cake_addons', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->nullable();
                $t->unsignedBigInteger('category_id')->nullable();
                $t->string('name', 100);
                $t->text('description')->nullable();
                $t->decimal('price', 10, 2)->default(0);
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('custom_order_options')) {
            Schema::create('custom_order_options', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->nullable();
                $t->string('type', 50)->nullable();
                $t->string('label', 100);
                $t->decimal('price', 10, 2)->default(0);
                $t->text('description')->nullable();
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $t) {
                $t->string('id', 12)->primary();
                $t->string('shop_id', 12)->nullable();
                $t->string('name', 150);
                $t->text('description')->nullable();
                $t->decimal('price', 10, 2)->default(0);
                $t->string('image_path')->nullable();
                $t->string('classification', 60)->nullable();
                $t->string('flavor', 80)->nullable();
                $t->boolean('is_available')->default(true);
                $t->integer('max_per_day')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('product_sizes')) {
            Schema::create('product_sizes', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('product_id', 12);
                $t->string('label', 60);
                $t->decimal('price', 10, 2)->default(0);
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('product_daily_orders')) {
            Schema::create('product_daily_orders', function (Blueprint $t) {
                $t->string('product_id', 12);
                $t->date('date');
                $t->integer('total_ordered')->default(0);
                $t->primary(['product_id', 'date']);
            });
        }

        if (!Schema::hasTable('riders')) {
            Schema::create('riders', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->nullable();
                $t->string('name', 100);
                $t->string('nickname', 60)->nullable();
                $t->string('phone', 20)->nullable();
                $t->string('vehicle_type', 60)->nullable();
                $t->string('license_plate', 30)->nullable();
                $t->string('emergency_contact_name', 100)->nullable();
                $t->string('emergency_contact_phone', 20)->nullable();
                $t->boolean('is_active')->default(true);
                $t->integer('incidents_count')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $t) {
                $t->string('id', 12)->primary();
                $t->string('shop_id', 12)->nullable();
                $t->string('user_id', 12)->nullable();
                $t->string('product_id', 12)->nullable();
                $t->integer('quantity')->default(1);
                $t->text('custom_note')->nullable();
                $t->text('special_notes')->nullable();
                $t->decimal('total_price', 10, 2)->default(0);
                $t->string('status', 30)->default('pending');
                $t->string('fulfillment_type', 20)->default('pickup');
                $t->string('delivery_zone')->nullable();
                $t->decimal('delivery_fee', 8, 2)->default(0);
                $t->decimal('service_charge', 8, 2)->default(0);
                $t->string('selected_size', 60)->nullable();
                $t->decimal('selected_size_price', 10, 2)->nullable();
                $t->text('delivery_address')->nullable();
                $t->date('schedule_date')->nullable();
                $t->string('schedule_time', 10)->nullable();
                $t->string('payment_method', 30)->nullable();
                $t->string('payment_status', 30)->default('unpaid');
                $t->string('guest_name', 100)->nullable();
                $t->string('guest_phone', 20)->nullable();
                $t->string('track_code', 30)->nullable();
                $t->boolean('kitchen_sent')->default(false);
                $t->unsignedBigInteger('rider_id')->nullable();
                $t->string('rider_token', 64)->nullable();
                $t->string('issue_type', 50)->nullable();
                $t->decimal('issue_amount', 10, 2)->nullable();
                $t->text('resolution_note')->nullable();
                $t->timestamp('issue_resolved_at')->nullable();
                $t->timestamp('settled_at')->nullable();
                $t->boolean('deposit_required')->default(false);
                $t->decimal('deposit_amount', 10, 2)->nullable();
                $t->string('deposit_status', 30)->nullable();
                $t->text('deposit_message')->nullable();
                $t->string('deposit_paymongo_id')->nullable();
                $t->string('paymongo_link_id')->nullable();
                $t->string('cancel_status', 30)->nullable();
                $t->text('cancel_reason')->nullable();
                $t->timestamp('cancel_requested_at')->nullable();
                $t->text('cancel_admin_note')->nullable();
                $t->boolean('review_requested')->default(false);
                $t->timestamp('delivered_at')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->boolean('price_confirmed')->default(false);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('order_addons')) {
            Schema::create('order_addons', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12);
                $t->unsignedBigInteger('addon_id')->nullable();
                $t->string('addon_name', 100)->nullable();
                $t->decimal('addon_price', 10, 2)->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('order_tracking')) {
            Schema::create('order_tracking', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12);
                $t->string('status', 50);
                $t->text('notes')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('order_reviews')) {
            Schema::create('order_reviews', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12);
                $t->string('shop_id', 12)->nullable();
                $t->unsignedTinyInteger('rating')->nullable();
                $t->text('review_text')->nullable();
                $t->string('review_status', 30)->default('pending');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('custom_orders')) {
            Schema::create('custom_orders', function (Blueprint $t) {
                $t->string('id', 12)->primary();
                $t->string('order_id', 12)->nullable();
                $t->string('shop_id', 12)->nullable();
                $t->string('user_id', 12)->nullable();
                $t->string('guest_name', 100)->nullable();
                $t->string('guest_phone', 20)->nullable();
                $t->string('status', 30)->default('pending');
                $t->json('reference_images')->nullable();
                $t->text('custom_note')->nullable();
                $t->decimal('quoted_price', 10, 2)->nullable();
                $t->string('payment_method', 30)->nullable();
                $t->string('payment_status', 30)->nullable();
                $t->date('schedule_date')->nullable();
                $t->string('fulfillment_type', 20)->nullable();
                $t->text('delivery_address')->nullable();
                $t->string('track_code', 30)->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('kitchen_tickets')) {
            Schema::create('kitchen_tickets', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12);
                $t->string('shop_id', 12)->nullable();
                $t->string('product_name', 150)->nullable();
                $t->string('product_image')->nullable();
                $t->integer('quantity')->default(1);
                $t->text('instructions')->nullable();
                $t->string('status', 30)->default('pending');
                $t->timestamp('sent_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12)->nullable();
                $t->string('shop_id', 12)->nullable();
                $t->enum('sender_role', ['admin','customer','guest','seller']);
                $t->string('sender_id', 12)->nullable();
                $t->text('message')->nullable();
                $t->string('image_path')->nullable();
                $t->boolean('is_read')->default(false);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->enum('receiver_role', ['admin','customer','seller','superadmin']);
                $t->string('receiver_user_id', 12)->nullable();
                $t->string('title', 200);
                $t->text('message')->nullable();
                $t->boolean('is_read')->default(false);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('user_id', 12);
                $t->string('label_name', 60)->nullable();
                $t->text('full_address');
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->boolean('is_default')->default(false);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->nullable();
                $t->string('site_title', 150)->nullable();
                $t->string('tagline', 255)->nullable();
                $t->string('logo_path')->nullable();
                $t->string('bg_type', 20)->default('color');
                $t->string('bg_color', 20)->nullable();
                $t->string('gradient_start', 20)->nullable();
                $t->string('gradient_end', 20)->nullable();
                $t->string('bg_image_path')->nullable();
                $t->decimal('bg_image_opacity', 3, 2)->default(1.00);
                $t->string('primary_color', 20)->nullable();
                $t->boolean('vat_enabled')->default(false);
                $t->decimal('vat_rate', 5, 2)->default(12.00);
                $t->string('tin_number', 30)->nullable();
                $t->string('timezone', 60)->default('Asia/Manila');
                $t->integer('daily_max_cakes')->nullable();
                $t->integer('lead_1day_max')->nullable();
                $t->integer('lead_2day_max')->nullable();
                $t->integer('lead_3day_plus_max')->nullable();
                $t->string('paymongo_mode', 10)->default('test');
                $t->string('paymongo_test_secret')->nullable();
                $t->string('paymongo_test_public')->nullable();
                $t->string('paymongo_live_secret')->nullable();
                $t->string('paymongo_live_public')->nullable();
                $t->decimal('shop_lat', 10, 7)->nullable();
                $t->decimal('shop_lng', 10, 7)->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('user_id', 12)->nullable();
                $t->string('role', 30)->nullable();
                $t->string('action', 100)->nullable();
                $t->text('details')->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'activity_logs','site_settings','user_addresses','notifications',
            'messages','kitchen_tickets','custom_orders','order_reviews',
            'order_tracking','order_addons','orders','riders',
            'product_daily_orders','product_sizes','products',
            'custom_order_options','cake_addons','cake_addon_categories',
            'delivery_zones','password_resets','users',
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
