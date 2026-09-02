<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rider_remittance_batches')) {
            Schema::create('rider_remittance_batches', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->index();
                $t->unsignedBigInteger('rider_id')->index();
                $t->decimal('total_amount', 10, 2);
                $t->unsignedInteger('order_count')->default(0);
                $t->string('status', 30)->default('pending')->index();
                $t->string('remittance_method', 30)->nullable();
                $t->string('reference_number', 120)->nullable()->index();
                $t->text('rider_note')->nullable();
                $t->text('seller_note')->nullable();
                $t->string('paymongo_payment_intent_id', 120)->nullable()->index();
                $t->string('paymongo_payment_method_id', 120)->nullable();
                $t->string('paymongo_checkout_session_id', 120)->nullable()->index();
                $t->text('paymongo_client_key')->nullable();
                $t->longText('paymongo_qr_image')->nullable();
                $t->text('paymongo_action_url')->nullable();
                $t->string('paymongo_status', 40)->nullable()->index();
                $t->string('paymongo_reference', 120)->nullable()->index();
                $t->timestamp('paymongo_expires_at')->nullable()->index();
                $t->timestamp('paymongo_paid_at')->nullable()->index();
                $t->timestamp('submitted_at')->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('rejected_at')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('rider_remittances') && !Schema::hasColumn('rider_remittances', 'batch_id')) {
            Schema::table('rider_remittances', function (Blueprint $t) {
                $t->unsignedBigInteger('batch_id')->nullable()->index()->after('rider_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rider_remittances') && Schema::hasColumn('rider_remittances', 'batch_id')) {
            Schema::table('rider_remittances', function (Blueprint $t) {
                $t->dropColumn('batch_id');
            });
        }

        Schema::dropIfExists('rider_remittance_batches');
    }
};
