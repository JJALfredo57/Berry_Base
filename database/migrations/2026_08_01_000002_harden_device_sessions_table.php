<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('device_sessions')) {
            return;
        }

        Schema::table('device_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('device_sessions', 'role')) {
                $table->string('role', 30)->default('guest_customer')->index();
            }
            if (!Schema::hasColumn('device_sessions', 'user_id')) {
                $table->string('user_id', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('device_sessions', 'rider_id')) {
                $table->unsignedBigInteger('rider_id')->nullable()->index();
            }
            if (!Schema::hasColumn('device_sessions', 'guest_track_code')) {
                $table->string('guest_track_code', 30)->nullable()->index();
            }
            if (!Schema::hasColumn('device_sessions', 'token_hash')) {
                $table->string('token_hash', 64)->nullable()->unique();
            }
            if (!Schema::hasColumn('device_sessions', 'device_token')) {
                $table->text('device_token')->nullable();
            }
            if (!Schema::hasColumn('device_sessions', 'device_type')) {
                $table->string('device_type', 30)->default('android');
            }
            if (!Schema::hasColumn('device_sessions', 'platform')) {
                $table->string('platform', 80)->nullable();
            }
            if (!Schema::hasColumn('device_sessions', 'device_name')) {
                $table->text('device_name')->nullable();
            }
            if (!Schema::hasColumn('device_sessions', 'user_agent')) {
                $table->text('user_agent')->nullable();
            }
            if (!Schema::hasColumn('device_sessions', 'is_push_enabled')) {
                $table->boolean('is_push_enabled')->default(true);
            }
            if (!Schema::hasColumn('device_sessions', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
            if (!Schema::hasColumn('device_sessions', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable();
            }
            if (!Schema::hasColumn('device_sessions', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('device_sessions', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE device_sessions ALTER COLUMN user_id TYPE VARCHAR(64)');
            DB::statement('ALTER TABLE device_sessions ALTER COLUMN device_token TYPE TEXT');
            DB::statement('ALTER TABLE device_sessions ALTER COLUMN device_name TYPE TEXT');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE device_sessions MODIFY user_id VARCHAR(64) NULL');
            DB::statement('ALTER TABLE device_sessions MODIFY device_token TEXT NULL');
            DB::statement('ALTER TABLE device_sessions MODIFY device_name TEXT NULL');
        }
    }

    public function down(): void
    {
        // Non-destructive hardening migration; keep widened columns.
    }
};
