<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Remove Design Complexity platform defaults
        DB::table('custom_order_options')
            ->whereNull('shop_id')
            ->where('type', 'complexity')
            ->delete();

        // Update Layer prices
        $layerPrices = [
            '1 Layer (Single Tier)'  => 0,
            '2 Layers (Double Tier)' => 200,
            '3 Layers (Triple Tier)' => 400,
            '2-Tier Stacked Cake'    => 800,
            '3-Tier Stacked Cake'    => 1800,
        ];

        foreach ($layerPrices as $label => $price) {
            DB::table('custom_order_options')
                ->whereNull('shop_id')
                ->where('type', 'layer')
                ->where('label', $label)
                ->update(['price' => $price]);
        }
    }

    public function down(): void
    {
        // Restore Design Complexity defaults
        $now = now();
        $complexities = [
            ['label' => 'Simple / Minimal',    'price' => 0,    'description' => 'Basic design — solid color, simple text, minimal decoration.', 'sort_order' => 1],
            ['label' => 'Standard',             'price' => 200,  'description' => 'Some piping, basic flowers, or simple fondant work.',           'sort_order' => 2],
            ['label' => 'Moderate',             'price' => 400,  'description' => 'Multi-element design with sculpted details or printed images.',  'sort_order' => 3],
            ['label' => 'Elaborate',            'price' => 700,  'description' => 'Highly detailed work — figures, scenes, or heavy fondant art.',  'sort_order' => 4],
            ['label' => 'Premium / Signature',  'price' => 1200, 'description' => 'Artist-level showpiece with full sculpting or luxury finishes.', 'sort_order' => 5],
        ];
        foreach ($complexities as $opt) {
            DB::table('custom_order_options')->insert(array_merge($opt, [
                'shop_id' => null, 'type' => 'complexity', 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        // Reset layer prices to 0
        DB::table('custom_order_options')
            ->whereNull('shop_id')
            ->where('type', 'layer')
            ->update(['price' => 0]);
    }
};
