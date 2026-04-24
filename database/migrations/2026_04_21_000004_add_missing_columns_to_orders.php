<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'custom_note'))
                $table->text('custom_note')->nullable()->after('special_notes');

            if (!Schema::hasColumn('orders', 'delivery_zone'))
                $table->string('delivery_zone')->nullable()->after('delivery_address');

            if (!Schema::hasColumn('orders', 'selected_size_price'))
                $table->decimal('selected_size_price', 10, 2)->nullable()->after('selected_size');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['custom_note', 'delivery_zone', 'selected_size_price']);
        });
    }
};
