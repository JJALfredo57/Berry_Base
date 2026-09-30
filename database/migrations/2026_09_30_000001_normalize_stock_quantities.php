<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'available_quantity')) {
            DB::table('products')->whereNull('available_quantity')->update(['available_quantity' => 0]);

            $simpleShopId = DB::table('shops')->where('shop_slug', 'simple-cake-shop')->value('id');
            if ($simpleShopId) {
                $quantities = [
                    '4m1yg6CA9w' => 3,
                    '71KQuX0ZZ5' => 5,
                    '8yqDYKMrgQ' => 2,
                    '90aiTkkl0N' => 0,
                    'C8cl0r1lyJ' => 8,
                    'cEyDq3rg8V' => 2,
                    'CthMkIqd78' => 4,
                    'CzLwa3ZEsM' => 6,
                    'dYPpHnL6WW' => 3,
                    'EDiAAD5c37' => 7,
                    'GUOrWJXU5j' => 0,
                    'GVE7mYzBN6' => 5,
                    'JwQgBNllKf' => 8,
                    'Kc9q0H4ofL' => 2,
                    'LNqNWuj4cE' => 3,
                    'orHymHTk1l' => 5,
                    'oUrd8qDicb' => 0,
                    'TlW5zEZ8nm' => 4,
                    'ugEJM1Psm2' => 6,
                    'vP10pJUHQk' => 3,
                    'x0qCd5r66a' => 8,
                    'XGpmi7p4qi' => 5,
                    'YagyXUFUbS' => 2,
                    'Z18tsQ3CYV' => 0,
                ];

                foreach ($quantities as $productId => $quantity) {
                    DB::table('products')
                        ->where('shop_id', $simpleShopId)
                        ->where('id', $productId)
                        ->update(['available_quantity' => $quantity, 'updated_at' => now()]);
                }
            }
        }

        if (Schema::hasTable('product_sizes') && Schema::hasColumn('product_sizes', 'available_quantity')) {
            DB::table('product_sizes')->whereNull('available_quantity')->update(['available_quantity' => 0]);

            $simpleShopId = DB::table('shops')->where('shop_slug', 'simple-cake-shop')->value('id');
            if ($simpleShopId) {
                $sizes = DB::table('product_sizes as ps')
                    ->join('products as p', 'p.id', '=', 'ps.product_id')
                    ->where('p.shop_id', $simpleShopId)
                    ->select('ps.id', 'ps.label')
                    ->get();

                foreach ($sizes as $size) {
                    $label = strtolower((string) $size->label);
                    $quantity = match (true) {
                        str_contains($label, '12') => 0,
                        str_contains($label, '10') => 2,
                        str_contains($label, '8') => 5,
                        str_contains($label, '6') => 3,
                        default => 4,
                    };

                    DB::table('product_sizes')
                        ->where('id', $size->id)
                        ->update(['available_quantity' => $quantity, 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: restoring null/open stock would reintroduce unlimited stock behavior.
    }
};
