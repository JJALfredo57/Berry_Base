<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_addresses') && !Schema::hasColumn('user_addresses', 'archived_at')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('is_default');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_addresses') && Schema::hasColumn('user_addresses', 'archived_at')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
