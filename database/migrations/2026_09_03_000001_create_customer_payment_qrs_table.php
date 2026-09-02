<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_payment_qrs')) return;

        Schema::create('customer_payment_qrs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('order_id')->index();
            $t->string('track_code', 40)->index();
            $t->string('payment_type', 20)->index();
            $t->decimal('amount', 10, 2);
            $t->string('status', 30)->default('awaiting_payment')->index();
            $t->string('reference_number', 120)->nullable()->index();
            $t->string('paymongo_payment_intent_id', 120)->nullable()->index();
            $t->string('paymongo_payment_method_id', 120)->nullable();
            $t->text('paymongo_client_key')->nullable();
            $t->longText('paymongo_qr_image')->nullable();
            $t->text('paymongo_action_url')->nullable();
            $t->string('paymongo_status', 40)->nullable()->index();
            $t->string('paymongo_reference', 120)->nullable()->index();
            $t->timestamp('paymongo_expires_at')->nullable()->index();
            $t->timestamp('paymongo_paid_at')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_qrs');
    }
};
