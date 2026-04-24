<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add shop_id to products
        if (!Schema::hasColumn('products', 'shop_id')) {
            DB::statement("ALTER TABLE `products` ADD `shop_id` VARCHAR(12) NULL AFTER `id`");
        }

        // Add shop_id to orders
        if (!Schema::hasColumn('orders', 'shop_id')) {
            DB::statement("ALTER TABLE `orders` ADD `shop_id` VARCHAR(12) NULL AFTER `id`");
        }

        // Add shop_id to custom_orders
        if (!Schema::hasColumn('custom_orders', 'shop_id')) {
            DB::statement("ALTER TABLE `custom_orders` ADD `shop_id` VARCHAR(12) NULL AFTER `id`");
        }

        // Add shop_id to messages (for routing)
        if (!Schema::hasColumn('messages', 'shop_id')) {
            DB::statement("ALTER TABLE `messages` ADD `shop_id` VARCHAR(12) NULL AFTER `order_id`");
        }

        // Add shop_id to order_reviews
        if (!Schema::hasColumn('order_reviews', 'shop_id')) {
            DB::statement("ALTER TABLE `order_reviews` ADD `shop_id` VARCHAR(12) NULL AFTER `id`");
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
            if (Schema::hasColumn($table, 'shop_id')) {
                Schema::table($table, fn($t) => $t->dropColumn('shop_id'));
            }
        }
    }
};
