<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mobile_notifications')) {
            return;
        }

        Schema::create('mobile_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('role', 30)->index();
            $table->string('user_id', 64)->nullable()->index();
            $table->unsignedBigInteger('rider_id')->nullable()->index();
            $table->string('guest_track_code', 30)->nullable()->index();
            $table->string('order_id', 64)->nullable()->index();
            $table->string('event_type', 60)->nullable()->index();
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('url', 500)->nullable();
            $table->text('data')->nullable();
            $table->string('delivery_channel', 20)->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notifications');
    }
};
