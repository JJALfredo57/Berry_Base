<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('seller_payout_ledgers')) {
            try {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('ALTER TABLE seller_payout_ledgers ALTER COLUMN order_id TYPE VARCHAR(40)');
                } else {
                    DB::statement('ALTER TABLE seller_payout_ledgers MODIFY order_id VARCHAR(40)');
                }
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('seller_payout_items')) {
            try {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('ALTER TABLE seller_payout_items ALTER COLUMN order_id TYPE VARCHAR(40)');
                } else {
                    DB::statement('ALTER TABLE seller_payout_items MODIFY order_id VARCHAR(40)');
                }
            } catch (\Throwable $e) {}
        }

        if (!Schema::hasTable('order_refunds')) {
            Schema::create('order_refunds', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('order_id', 20)->index();
                $table->string('shop_id', 20)->nullable()->index();
                $table->string('seller_user_id', 20)->nullable()->index();
                $table->string('track_code', 40)->nullable()->index();
                $table->string('customer_name', 160)->nullable();
                $table->string('customer_phone', 80)->nullable()->index();
                $table->string('refund_gcash_name', 160)->nullable();
                $table->string('refund_gcash_number', 80)->nullable();
                $table->text('cancel_reason')->nullable();
                $table->decimal('refund_amount', 10, 2)->default(0);
                $table->decimal('payment_amount_paid', 10, 2)->default(0);
                $table->string('status', 30)->default('pending')->index();
                $table->string('reviewed_by_role', 30)->nullable();
                $table->string('reviewed_by_user_id', 40)->nullable();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('receipt_path')->nullable();
                $table->string('reference_number', 120)->nullable();
                $table->timestamp('receipt_uploaded_at')->nullable();
                $table->unsignedBigInteger('payout_ledger_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
