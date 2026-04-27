<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $t) {
            if (!Schema::hasColumn('delivery_zones', 'lat'))
                $t->decimal('lat', 10, 7)->nullable()->after('estimated_time');
            if (!Schema::hasColumn('delivery_zones', 'lng'))
                $t->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $t) {
            $t->dropColumn(['lat', 'lng']);
        });
    }
};
