<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $t) {
            if (!Schema::hasColumn('delivery_zones', 'updated_at'))
                $t->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $t) {
            $t->dropColumn('updated_at');
        });
    }
};
