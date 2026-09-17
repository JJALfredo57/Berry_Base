<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_carts')) {
            Schema::create('customer_carts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id', 12)->nullable()->index();
                $table->string('session_id', 120)->nullable()->index();
                $table->string('shop_id', 12)->nullable()->index();
                $table->string('status', 30)->default('active')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_cart_items')) {
            Schema::create('customer_cart_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('cart_id')->index();
                $table->string('shop_id', 12)->nullable()->index();
                $table->string('product_id', 12)->index();
                $table->integer('quantity')->default(1);
                $table->string('selected_size', 60)->nullable();
                $table->decimal('unit_price_snapshot', 10, 2)->default(0);
                $table->decimal('final_unit_price_snapshot', 10, 2)->default(0);
                $table->decimal('discount_amount_snapshot', 10, 2)->default(0);
                $table->string('discount_label_snapshot', 100)->nullable();
                $table->text('custom_note')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_verifications')) {
            Schema::create('customer_verifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id', 12)->index();
                $table->string('id_type', 60);
                $table->string('id_front_path');
                $table->string('id_back_path')->nullable();
                $table->string('selfie_path')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->text('customer_note')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->string('reviewed_by', 12)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (!Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('shop_id', 12)->nullable()->index();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('discount_type', 30);
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->decimal('max_discount', 10, 2)->nullable();
                $table->decimal('minimum_order_amount', 10, 2)->default(0);
                $table->integer('usage_limit')->nullable();
                $table->integer('per_customer_limit')->nullable();
                $table->boolean('first_order_only')->default(false);
                $table->boolean('requires_verified_customer')->default(false);
                $table->boolean('stack_with_product_discount')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('voucher_redemptions')) {
            Schema::create('voucher_redemptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('voucher_id')->index();
                $table->string('order_id', 12)->nullable()->index();
                $table->string('user_id', 12)->nullable()->index();
                $table->string('guest_phone', 30)->nullable()->index();
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->timestamp('redeemed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('order_discounts')) {
            Schema::create('order_discounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('order_id', 12)->index();
                $table->string('source_type', 30)->index();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('label', 120)->nullable();
                $table->string('code', 40)->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('loyalty_accounts')) {
            Schema::create('loyalty_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id', 12)->unique();
                $table->integer('points_balance')->default(0);
                $table->integer('lifetime_points')->default(0);
                $table->decimal('lifetime_spend', 10, 2)->default(0);
                $table->string('tier', 40)->default('Bronze')->index();
                $table->timestamp('tier_updated_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('loyalty_transactions')) {
            Schema::create('loyalty_transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id', 12)->index();
                $table->string('order_id', 12)->nullable()->index();
                $table->string('type', 30)->index();
                $table->integer('points');
                $table->integer('balance_after')->default(0);
                $table->string('description', 180)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'type'], 'loyalty_order_type_unique');
            });
        }

        if (!Schema::hasTable('loyalty_tiers')) {
            Schema::create('loyalty_tiers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 40)->unique();
                $table->integer('min_lifetime_points')->default(0);
                $table->decimal('points_multiplier', 5, 2)->default(1);
                $table->string('perk_summary', 255)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });

            DB::table('loyalty_tiers')->insert([
                ['name' => 'Bronze', 'min_lifetime_points' => 0, 'points_multiplier' => 1, 'perk_summary' => 'Earn rewards on completed orders.', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Silver', 'min_lifetime_points' => 250, 'points_multiplier' => 1.25, 'perk_summary' => 'Earn more points and unlock verified-only promos.', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Gold', 'min_lifetime_points' => 750, 'points_multiplier' => 1.5, 'perk_summary' => 'Highest rewards rate and priority trust signals.', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'cart_id')) {
                $table->unsignedBigInteger('cart_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('orders', 'voucher_code')) {
                $table->string('voucher_code', 40)->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('orders', 'voucher_discount_amount')) {
                $table->decimal('voucher_discount_amount', 10, 2)->default(0)->after('voucher_code');
            }
            if (!Schema::hasColumn('orders', 'loyalty_discount_amount')) {
                $table->decimal('loyalty_discount_amount', 10, 2)->default(0)->after('voucher_discount_amount');
            }
            if (!Schema::hasColumn('orders', 'points_earned')) {
                $table->integer('points_earned')->default(0)->after('loyalty_discount_amount');
            }
            if (!Schema::hasColumn('orders', 'points_redeemed')) {
                $table->integer('points_redeemed')->default(0)->after('points_earned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['points_redeemed','points_earned','loyalty_discount_amount','voucher_discount_amount','voucher_code','cart_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('customer_verifications');
        Schema::dropIfExists('customer_cart_items');
        Schema::dropIfExists('customer_carts');
    }
};
