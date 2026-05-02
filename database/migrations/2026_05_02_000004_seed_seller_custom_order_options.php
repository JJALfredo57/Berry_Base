<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now     = now();
        $shops   = DB::table('shops')->where('status', 'approved')->pluck('id');
        $defaults = DB::table('custom_order_options')->whereNull('shop_id')->orderBy('type')->orderBy('sort_order')->get();

        if ($defaults->isEmpty()) return;

        foreach ($shops as $shopId) {
            // Skip shops that already have their own options
            $hasOwn = DB::table('custom_order_options')->where('shop_id', $shopId)->exists();
            if ($hasOwn) continue;

            foreach ($defaults as $opt) {
                DB::table('custom_order_options')->insert([
                    'shop_id'     => $shopId,
                    'type'        => $opt->type,
                    'label'       => $opt->label,
                    'price'       => $opt->price,
                    'description' => $opt->description,
                    'is_active'   => true,
                    'sort_order'  => $opt->sort_order,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $shops = DB::table('shops')->where('status', 'approved')->pluck('id');
        DB::table('custom_order_options')->whereIn('shop_id', $shops)->delete();
    }
};
