<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('platform_settings', 'backup_auto_enabled')) {
                $t->boolean('backup_auto_enabled')->default(false)->after('dev_mode');
            }
            if (!Schema::hasColumn('platform_settings', 'backup_frequency')) {
                $t->string('backup_frequency', 20)->default('daily')->after('backup_auto_enabled');
            }
            if (!Schema::hasColumn('platform_settings', 'backup_retention_count')) {
                $t->unsignedSmallInteger('backup_retention_count')->default(14)->after('backup_frequency');
            }
            if (!Schema::hasColumn('platform_settings', 'backup_include_uploads')) {
                $t->boolean('backup_include_uploads')->default(false)->after('backup_retention_count');
            }
            if (!Schema::hasColumn('platform_settings', 'backup_last_run_at')) {
                $t->timestamp('backup_last_run_at')->nullable()->after('backup_include_uploads');
            }
            if (!Schema::hasColumn('platform_settings', 'backup_last_status')) {
                $t->string('backup_last_status', 20)->nullable()->after('backup_last_run_at');
            }
            if (!Schema::hasColumn('platform_settings', 'backup_last_message')) {
                $t->text('backup_last_message')->nullable()->after('backup_last_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $t) {
            foreach ([
                'backup_last_message',
                'backup_last_status',
                'backup_last_run_at',
                'backup_include_uploads',
                'backup_retention_count',
                'backup_frequency',
                'backup_auto_enabled',
            ] as $column) {
                if (Schema::hasColumn('platform_settings', $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }
};
