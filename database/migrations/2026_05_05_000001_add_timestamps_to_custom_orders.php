<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('custom_orders', 'reviewed_at'))
                $t->timestamp('reviewed_at')->nullable()->after('price_confirmed');
            if (!Schema::hasColumn('custom_orders', 'progress_sent_at'))
                $t->timestamp('progress_sent_at')->nullable()->after('progress_message');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $t) {
            $t->dropColumn(['reviewed_at', 'progress_sent_at']);
        });
    }
};
