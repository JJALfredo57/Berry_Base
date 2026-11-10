<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('delivery_messages')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE delivery_messages MODIFY order_id VARCHAR(64) NOT NULL');
            DB::statement('ALTER TABLE delivery_messages MODIFY customer_user_id VARCHAR(64) NOT NULL');
            DB::statement('ALTER TABLE delivery_messages MODIFY sender_id VARCHAR(64) NOT NULL');
            return;
        }

        Schema::table('delivery_messages', function ($table) {
            $table->string('order_id', 64)->change();
            $table->string('customer_user_id', 64)->change();
            $table->string('sender_id', 64)->change();
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: string order and customer IDs cannot be safely cast back to integers.
    }
};
