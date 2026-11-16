<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->index()->after('verified_at');
            }
            if (!Schema::hasColumn('shops', 'archived_by_user_id')) {
                $table->string('archived_by_user_id', 40)->nullable()->index()->after('archived_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'archived_by_user_id')) $table->dropColumn('archived_by_user_id');
            if (Schema::hasColumn('shops', 'archived_at')) $table->dropColumn('archived_at');
        });
    }
};
