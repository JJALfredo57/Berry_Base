<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('delivery_messages')) {
            return;
        }

        Schema::create('delivery_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('rider_id');
            $table->unsignedBigInteger('customer_user_id');
            $table->string('sender_type', 20);
            $table->unsignedBigInteger('sender_id');
            $table->text('message')->nullable();
            $table->text('image_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'id']);
            $table->index(['rider_id', 'read_at']);
            $table->index(['customer_user_id', 'read_at']);
            $table->index(['sender_type', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_messages');
    }
};
