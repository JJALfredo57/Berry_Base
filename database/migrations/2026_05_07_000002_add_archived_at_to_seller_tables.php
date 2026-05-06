<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tables = [
            'cake_addons',
            'cake_addon_categories',
            'custom_order_options',
            'delivery_zones',
            'product_sizes',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                if (!Schema::hasColumn($t->getTable(), 'archived_at'))
                    $t->timestamp('archived_at')->nullable()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['cake_addons','cake_addon_categories','custom_order_options','delivery_zones','product_sizes'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('archived_at');
            });
        }
    }
};
