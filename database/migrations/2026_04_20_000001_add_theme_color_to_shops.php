<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('shops', 'theme_color')) {
            DB::statement("ALTER TABLE `shops` ADD `theme_color` VARCHAR(7) NULL DEFAULT NULL AFTER `gcash_number`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shops', 'theme_color')) {
            Schema::table('shops', fn($t) => $t->dropColumn('theme_color'));
        }
    }
};
