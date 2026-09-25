<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('order_requests')) {
            Schema::table('order_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('order_requests', 'customer_token')) {
                    $table->string('customer_token', 64)->nullable()->index();
                }
                if (!Schema::hasColumn('order_requests', 'accepted_date')) {
                    $table->date('accepted_date')->nullable();
                }
                if (!Schema::hasColumn('order_requests', 'accepted_time')) {
                    $table->string('accepted_time', 10)->nullable();
                }
                if (!Schema::hasColumn('order_requests', 'accepted_datetime')) {
                    $table->timestamp('accepted_datetime')->nullable();
                }
                if (!Schema::hasColumn('order_requests', 'customer_decision_at')) {
                    $table->timestamp('customer_decision_at')->nullable();
                }
                if (!Schema::hasColumn('order_requests', 'customer_decision_note')) {
                    $table->text('customer_decision_note')->nullable();
                }
                if (!Schema::hasColumn('order_requests', 'converted_order_id')) {
                    $table->string('converted_order_id', 12)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'order_request_id')) {
                    $table->string('order_request_id', 12)->nullable()->index();
                }
                if (!Schema::hasColumn('orders', 'is_rush')) {
                    $table->boolean('is_rush')->default(false);
                }
                if (!Schema::hasColumn('orders', 'rush_reason')) {
                    $table->string('rush_reason', 60)->nullable();
                }
                if (!Schema::hasColumn('orders', 'seller_prep_days_at_request')) {
                    $table->unsignedSmallInteger('seller_prep_days_at_request')->nullable();
                }
                if (!Schema::hasColumn('orders', 'requested_notice_minutes')) {
                    $table->integer('requested_notice_minutes')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['requested_notice_minutes', 'seller_prep_days_at_request', 'rush_reason', 'is_rush', 'order_request_id'] as $column) {
                    if (Schema::hasColumn('orders', $column)) $table->dropColumn($column);
                }
            });
        }
        if (Schema::hasTable('order_requests')) {
            Schema::table('order_requests', function (Blueprint $table) {
                foreach (['converted_order_id', 'customer_decision_note', 'customer_decision_at', 'accepted_datetime', 'accepted_time', 'accepted_date', 'customer_token'] as $column) {
                    if (Schema::hasColumn('order_requests', $column)) $table->dropColumn($column);
                }
            });
        }
    }
};
