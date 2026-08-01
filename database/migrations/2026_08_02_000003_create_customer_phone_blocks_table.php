<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customer_phone_blocks')) {
            return;
        }

        Schema::create('customer_phone_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('phone_normalized', 32)->index();
            $table->string('phone_display', 40)->nullable();
            $table->text('reason')->nullable();
            $table->string('blocked_by_role', 30)->nullable();
            $table->string('blocked_by_user_id', 32)->nullable();
            $table->timestamp('blocked_until')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_phone_blocks');
    }
};
