<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'status')) {
                $table->string('status', 20)->default('success')->after('details');
            }
            if (!Schema::hasColumn('activity_logs', 'target_type')) {
                $table->string('target_type', 80)->nullable()->after('status');
            }
            if (!Schema::hasColumn('activity_logs', 'target_id')) {
                $table->string('target_id', 120)->nullable()->after('target_type');
            }
            if (!Schema::hasColumn('activity_logs', 'route_name')) {
                $table->string('route_name', 150)->nullable()->after('target_id');
            }
            if (!Schema::hasColumn('activity_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['created_at', 'status']);
            $table->index(['role', 'action']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['activity_logs_created_at_status_index']);
            $table->dropIndex(['activity_logs_role_action_index']);
            $table->dropIndex(['activity_logs_target_type_target_id_index']);

            foreach (['status', 'target_type', 'target_id', 'route_name', 'user_agent'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
