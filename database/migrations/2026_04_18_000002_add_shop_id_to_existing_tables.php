<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (['products','orders','custom_orders','order_reviews'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'shop_id')) {
                Schema::table($table, fn(Blueprint $t) => $t->string('shop_id', 12)->nullable());
            }
        }

        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'shop_id')) {
            Schema::table('messages', fn(Blueprint $t) => $t->string('shop_id', 12)->nullable());
        }

        // Auto-assign existing data to first shop (if any shop exists)
        $firstShop = DB::table('shops')->where('status', 'approved')->first();
        if ($firstShop) {
            DB::table('products')->whereNull('shop_id')->update(['shop_id' => $firstShop->id]);
            DB::table('orders')->whereNull('shop_id')->update(['shop_id' => $firstShop->id]);
            DB::table('custom_orders')->whereNull('shop_id')->update(['shop_id' => $firstShop->id]);
            DB::table('order_reviews')->whereNull('shop_id')->update(['shop_id' => $firstShop->id]);
        }
    }

    public function down(): void
    {
        foreach (['products','orders','custom_orders','messages','order_reviews'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'shop_id')) {
                Schema::table($table, fn($t) => $t->dropColumn('shop_id'));
            }
        }
    }
};
