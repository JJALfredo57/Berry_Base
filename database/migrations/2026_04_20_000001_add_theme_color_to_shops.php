<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('shops') && !Schema::hasColumn('shops', 'theme_color')) {
            Schema::table('shops', fn(Blueprint $t) => $t->string('theme_color', 7)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'theme_color')) {
            Schema::table('shops', fn($t) => $t->dropColumn('theme_color'));
        }
    }
};
