<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('order_requests')) return;
        Schema::table('order_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_requests', 'alternative_selected_size')) $table->string('alternative_selected_size', 120)->nullable()->after('alternative_product_id');
            if (!Schema::hasColumn('order_requests', 'alternative_selected_quantity')) $table->unsignedInteger('alternative_selected_quantity')->nullable()->after('alternative_selected_size');
        });
    }
    public function down(): void {
        if (!Schema::hasTable('order_requests')) return;
        Schema::table('order_requests', function (Blueprint $table) {
            foreach (['alternative_selected_quantity','alternative_selected_size'] as $column) if (Schema::hasColumn('order_requests', $column)) $table->dropColumn($column);
        });
    }
};
