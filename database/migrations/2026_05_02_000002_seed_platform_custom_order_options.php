<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Only seed if no platform defaults exist yet
        if (DB::table('custom_order_options')->whereNull('shop_id')->exists()) return;

        $now = now();

        $options = [
            // ── Flavors ──────────────────────────────────────────────────
            ['type' => 'flavor', 'label' => 'Chocolate Moist',       'price' => 0, 'description' => 'Rich, dense dark chocolate cake with deep cocoa flavor.',                'sort_order' => 1],
            ['type' => 'flavor', 'label' => 'Vanilla Bean',           'price' => 0, 'description' => 'Classic creamy vanilla with real vanilla bean specks.',                  'sort_order' => 2],
            ['type' => 'flavor', 'label' => 'Ube (Purple Yam)',       'price' => 0, 'description' => 'Traditional Filipino ube with a sweet, earthy purple yam flavor.',       'sort_order' => 3],
            ['type' => 'flavor', 'label' => 'Red Velvet',             'price' => 0, 'description' => 'Velvety smooth red cake with a hint of cocoa and cream cheese frosting.','sort_order' => 4],
            ['type' => 'flavor', 'label' => 'Matcha Green Tea',       'price' => 0, 'description' => 'Premium Japanese matcha with a delicate earthy, slightly bitter taste.',  'sort_order' => 5],
            ['type' => 'flavor', 'label' => 'Lemon Chiffon',          'price' => 0, 'description' => 'Light and airy chiffon cake with bright, zesty lemon flavor.',            'sort_order' => 6],
            ['type' => 'flavor', 'label' => 'Pandan',                 'price' => 0, 'description' => 'Fragrant Filipino pandan with a soft, tropical green leaf aroma.',        'sort_order' => 7],
            ['type' => 'flavor', 'label' => 'Salted Caramel',         'price' => 0, 'description' => 'Buttery caramel with a perfect balance of sweet and salty.',              'sort_order' => 8],
            ['type' => 'flavor', 'label' => 'Strawberry',             'price' => 0, 'description' => 'Fresh strawberry flavor throughout the cake and frosting.',                'sort_order' => 9],
            ['type' => 'flavor', 'label' => 'Cookies & Cream',        'price' => 0, 'description' => 'Vanilla cake loaded with crushed Oreo cookies in every bite.',            'sort_order' => 10],
            ['type' => 'flavor', 'label' => 'Mango',                  'price' => 0, 'description' => 'Sweet ripe Philippine mango flavor baked right into the cake.',           'sort_order' => 11],
            ['type' => 'flavor', 'label' => 'Caramel Butter',         'price' => 0, 'description' => 'Rich buttery cake with a smooth caramel glaze and filling.',              'sort_order' => 12],

            // ── Sizes / Diameter ─────────────────────────────────────────
            ['type' => 'size', 'label' => '4" Round — Smash Cake (2–3 slices)',     'price' => 0,    'description' => 'Perfect for smash cake sessions and solo celebrations.',         'sort_order' => 1],
            ['type' => 'size', 'label' => '6" Round — Small (6–8 slices)',           'price' => 0,    'description' => 'Ideal for intimate gatherings and small families.',               'sort_order' => 2],
            ['type' => 'size', 'label' => '8" Round — Medium (12–15 slices)',        'price' => 300,  'description' => 'Great for birthdays and small celebrations.',                     'sort_order' => 3],
            ['type' => 'size', 'label' => '10" Round — Large (18–22 slices)',        'price' => 600,  'description' => 'Feeds a crowd — perfect for parties and gatherings.',             'sort_order' => 4],
            ['type' => 'size', 'label' => '12" Round — Extra Large (25–30 slices)', 'price' => 1000, 'description' => 'For big celebrations and large family events.',                   'sort_order' => 5],
            ['type' => 'size', 'label' => '6" Square (8–10 slices)',                 'price' => 150,  'description' => 'Square shape for a modern look, serves a small group.',           'sort_order' => 6],
            ['type' => 'size', 'label' => '8" Square (14–16 slices)',                'price' => 450,  'description' => 'Square shape ideal for corporate and themed events.',             'sort_order' => 7],
            ['type' => 'size', 'label' => 'Quarter Sheet (20–24 slices)',             'price' => 700,  'description' => 'Classic sheet cake cut, great for office parties.',               'sort_order' => 8],
            ['type' => 'size', 'label' => 'Half Sheet (35–40 slices)',                'price' => 1200, 'description' => 'Large sheet cake for big events and reunions.',                  'sort_order' => 9],

            // ── Number of Layers ──────────────────────────────────────────
            ['type' => 'layer', 'label' => '1 Layer (Single Tier)',  'price' => 0,    'description' => 'Classic single-tier cake — base price, no surcharge.',                  'sort_order' => 1],
            ['type' => 'layer', 'label' => '2 Layers (Double Tier)', 'price' => 200,  'description' => 'Two layers with filling in between — more height and servings.',         'sort_order' => 2],
            ['type' => 'layer', 'label' => '3 Layers (Triple Tier)', 'price' => 400,  'description' => 'Three generous layers with filling — perfect for special occasions.',    'sort_order' => 3],
            ['type' => 'layer', 'label' => '2-Tier Stacked Cake',    'price' => 800,  'description' => 'Two separate tiers stacked — stunning centerpiece for events.',          'sort_order' => 4],
            ['type' => 'layer', 'label' => '3-Tier Stacked Cake',    'price' => 1800, 'description' => 'Three dramatic stacked tiers — ideal for weddings and debut.',           'sort_order' => 5],

            // ── Delivery Time Slots ───────────────────────────────────────
            ['type' => 'time_slot', 'label' => 'Morning (8:00 AM – 11:00 AM)',          'price' => 0, 'description' => null, 'sort_order' => 1],
            ['type' => 'time_slot', 'label' => 'Midday (11:00 AM – 1:00 PM)',           'price' => 0, 'description' => null, 'sort_order' => 2],
            ['type' => 'time_slot', 'label' => 'Early Afternoon (1:00 PM – 3:00 PM)',   'price' => 0, 'description' => null, 'sort_order' => 3],
            ['type' => 'time_slot', 'label' => 'Afternoon (3:00 PM – 5:00 PM)',         'price' => 0, 'description' => null, 'sort_order' => 4],
            ['type' => 'time_slot', 'label' => 'Late Afternoon (5:00 PM – 7:00 PM)',    'price' => 0, 'description' => null, 'sort_order' => 5],
        ];

        foreach ($options as $opt) {
            DB::table('custom_order_options')->insert([
                'shop_id'     => null,
                'type'        => $opt['type'],
                'label'       => $opt['label'],
                'price'       => $opt['price'],
                'description' => $opt['description'],
                'is_active'   => true,
                'sort_order'  => $opt['sort_order'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('custom_order_options')->whereNull('shop_id')->delete();
    }
};
