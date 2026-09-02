<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('riders')) {
            return;
        }

        Schema::table('riders', function (Blueprint $table) {
            if (!Schema::hasColumn('riders', 'password_must_change')) {
                $table->boolean('password_must_change')->default(false)->after('login_pin_set_at');
            }
            if (!Schema::hasColumn('riders', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password_must_change');
            }
            if (!Schema::hasColumn('riders', 'password_reset_otp')) {
                $table->string('password_reset_otp', 10)->nullable()->after('password_changed_at');
            }
            if (!Schema::hasColumn('riders', 'password_reset_expires_at')) {
                $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_otp');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('riders')) {
            return;
        }

        Schema::table('riders', function (Blueprint $table) {
            foreach ([
                'password_reset_expires_at',
                'password_reset_otp',
                'password_changed_at',
                'password_must_change',
            ] as $column) {
                if (Schema::hasColumn('riders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
