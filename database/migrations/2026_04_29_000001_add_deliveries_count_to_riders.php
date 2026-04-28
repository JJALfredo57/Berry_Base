<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('riders', 'deliveries_count')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->unsignedInteger('deliveries_count')->default(0)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('riders', 'deliveries_count')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->dropColumn('deliveries_count');
            });
        }
    }
};
