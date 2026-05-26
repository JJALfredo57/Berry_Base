<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_feedback')) return;

        Schema::table('customer_feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_feedback', 'attachment_paths')) {
                $table->text('attachment_paths')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_feedback')) return;

        Schema::table('customer_feedback', function (Blueprint $table) {
            if (Schema::hasColumn('customer_feedback', 'attachment_paths')) {
                $table->dropColumn('attachment_paths');
            }
        });
    }
};
