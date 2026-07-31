<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('device_sessions')) {
            return;
        }

        Schema::create('device_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('role', 30)->index();
            $table->string('user_id', 12)->nullable()->index();
            $table->unsignedBigInteger('rider_id')->nullable()->index();
            $table->string('guest_track_code', 30)->nullable()->index();
            $table->string('token_hash', 64)->unique();
            $table->text('device_token');
            $table->string('device_type', 30)->default('android');
            $table->string('platform', 80)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_push_enabled')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
