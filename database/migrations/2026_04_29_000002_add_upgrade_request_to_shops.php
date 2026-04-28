<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'upgrade_request_status')) {
                $table->string('upgrade_request_status', 20)->nullable()->after('tier');
            }
            if (!Schema::hasColumn('shops', 'upgrade_request_note')) {
                $table->text('upgrade_request_note')->nullable()->after('upgrade_request_status');
            }
            if (!Schema::hasColumn('shops', 'upgrade_requested_at')) {
                $table->timestamp('upgrade_requested_at')->nullable()->after('upgrade_request_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            foreach (['upgrade_request_status', 'upgrade_request_note', 'upgrade_requested_at'] as $col) {
                if (Schema::hasColumn('shops', $col)) $table->dropColumn($col);
            }
        });
    }
};
