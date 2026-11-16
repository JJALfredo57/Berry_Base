<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('post_delivery_return_cases')) {
            Schema::create('post_delivery_return_cases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('case_number', 32)->unique();
                $table->string('order_id', 40)->index();
                $table->string('shop_id', 40)->nullable()->index();
                $table->string('customer_user_id', 40)->index();
                $table->string('seller_user_id', 40)->nullable()->index();
                $table->string('reason', 40)->index();
                $table->text('description');
                $table->string('status', 32)->default('submitted')->index();
                $table->decimal('customer_payment_amount', 12, 2)->default(0);
                $table->decimal('service_fee_amount', 12, 2)->default(0);
                $table->decimal('requested_amount', 12, 2)->default(0);
                $table->decimal('approved_amount', 12, 2)->nullable();
                $table->decimal('seller_recovery_amount', 12, 2)->default(0);
                $table->decimal('platform_cost_amount', 12, 2)->default(0);
                $table->string('liability', 24)->nullable();
                $table->text('seller_response')->nullable();
                $table->text('decision_note')->nullable();
                $table->string('refund_gcash_name', 160);
                $table->string('refund_gcash_number', 80);
                $table->unsignedBigInteger('held_ledger_id')->nullable()->index();
                $table->string('ledger_status_before_hold', 32)->nullable();
                $table->string('payout_hold_status', 32)->default('not_required')->index();
                $table->string('refund_reference', 120)->nullable()->unique();
                $table->string('refund_receipt_path')->nullable();
                $table->string('refund_receipt_hash', 64)->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('seller_responded_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->string('decided_by_user_id', 40)->nullable()->index();
                $table->string('refunded_by_user_id', 40)->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('post_delivery_return_evidence')) {
            Schema::create('post_delivery_return_evidence', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('return_case_id')->index();
                $table->string('kind', 12)->index();
                $table->string('path');
                $table->string('sha256', 64);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size_bytes');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_delivery_return_evidence');
        Schema::dropIfExists('post_delivery_return_cases');
    }
};
