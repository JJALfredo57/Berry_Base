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

    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            foreach (['status', 'target_type', 'target_id', 'route_name', 'user_agent'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
