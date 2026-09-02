<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('riders')) {
            Schema::table('riders', function (Blueprint $table) {
                if (!Schema::hasColumn('riders', 'login_pin_hash')) {
                    $table->string('login_pin_hash')->nullable()->after('phone');
                }
                if (!Schema::hasColumn('riders', 'login_pin_set_at')) {
                    $table->timestamp('login_pin_set_at')->nullable()->after('login_pin_hash');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'rider_assignment_status')) {
                    $table->string('rider_assignment_status', 30)->nullable()->index()->after('rider_pin');
                }
                if (!Schema::hasColumn('orders', 'rider_assignment_previous_status')) {
                    $table->string('rider_assignment_previous_status', 30)->nullable()->after('rider_assignment_status');
                }
                if (!Schema::hasColumn('orders', 'rider_assigned_at')) {
                    $table->timestamp('rider_assigned_at')->nullable()->after('rider_assignment_previous_status');
                }
                if (!Schema::hasColumn('orders', 'rider_assignment_expires_at')) {
                    $table->timestamp('rider_assignment_expires_at')->nullable()->index()->after('rider_assigned_at');
                }
                if (!Schema::hasColumn('orders', 'rider_accepted_at')) {
                    $table->timestamp('rider_accepted_at')->nullable()->after('rider_assignment_expires_at');
                }
                if (!Schema::hasColumn('orders', 'rider_declined_at')) {
                    $table->timestamp('rider_declined_at')->nullable()->after('rider_accepted_at');
                }
                if (!Schema::hasColumn('orders', 'rider_decline_reason')) {
                    $table->text('rider_decline_reason')->nullable()->after('rider_declined_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'rider_assignment_status',
                    'rider_assignment_previous_status',
                    'rider_assigned_at',
                    'rider_assignment_expires_at',
                    'rider_accepted_at',
                    'rider_declined_at',
                    'rider_decline_reason',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('riders')) {
            Schema::table('riders', function (Blueprint $table) {
                foreach (['login_pin_hash', 'login_pin_set_at'] as $column) {
                    if (Schema::hasColumn('riders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
