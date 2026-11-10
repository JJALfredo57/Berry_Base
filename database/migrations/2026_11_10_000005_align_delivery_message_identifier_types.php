<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('delivery_messages')) {
            return;
        }

        Schema::table('delivery_messages', function (Blueprint $table) {
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
