<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('messages') || !Schema::hasColumn('messages', 'image_path')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE messages ALTER COLUMN image_path TYPE TEXT');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE messages MODIFY image_path TEXT NULL');
        }
    }

    public function down(): void
    {
        // Keep this non-destructive. Existing multi-image URLs may already exceed 255 chars.
    }
};
