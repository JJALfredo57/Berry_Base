<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_requests')) {
            Schema::create('order_requests', function (Blueprint $table) {
                $table->string('id', 12)->primary();
                $table->string('shop_id', 12)->index();
                $table->string('user_id', 12)->nullable()->index();
                $table->string('guest_name', 120)->nullable();
                $table->string('guest_phone', 30)->nullable();
                $table->string('product_id', 12)->nullable()->index();
                $table->string('custom_order_id', 12)->nullable()->index();
                $table->string('type', 30)->default('ready_made')->index();
                $table->string('source', 40)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->date('preferred_date');
                $table->string('preferred_time', 10);
                $table->timestamp('preferred_datetime')->nullable()->index();
                $table->boolean('is_rush')->default(false)->index();
                $table->string('rush_reason', 60)->nullable();
                $table->unsignedSmallInteger('seller_prep_days_at_request')->default(0);
                $table->integer('requested_notice_minutes')->nullable();
                $table->boolean('allow_similar_cake')->default(false);
                $table->text('customer_note')->nullable();
                $table->string('status', 40)->default('pending')->index();
                $table->text('seller_response')->nullable();
                $table->date('suggested_date')->nullable();
                $table->string('suggested_time', 10)->nullable();
                $table->string('alternative_product_id', 12)->nullable();
                $table->decimal('accepted_price', 10, 2)->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'status', 'is_rush', 'preferred_datetime']);
            });
        }

        if (Schema::hasTable('custom_orders')) {
            Schema::table('custom_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('custom_orders', 'is_rush')) {
                    $table->boolean('is_rush')->default(false);
                }
                if (!Schema::hasColumn('custom_orders', 'rush_reason')) {
                    $table->string('rush_reason', 60)->nullable();
                }
                if (!Schema::hasColumn('custom_orders', 'seller_prep_days_at_request')) {
                    $table->unsignedSmallInteger('seller_prep_days_at_request')->nullable();
                }
                if (!Schema::hasColumn('custom_orders', 'requested_notice_minutes')) {
                    $table->integer('requested_notice_minutes')->nullable();
                }
                if (!Schema::hasColumn('custom_orders', 'rush_detected_at')) {
                    $table->timestamp('rush_detected_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('custom_orders')) {
            Schema::table('custom_orders', function (Blueprint $table) {
            foreach (['rush_detected_at', 'requested_notice_minutes', 'seller_prep_days_at_request', 'rush_reason', 'is_rush'] as $column) {
                if (Schema::hasColumn('custom_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
            });
        }

        Schema::dropIfExists('order_requests');
    }
};