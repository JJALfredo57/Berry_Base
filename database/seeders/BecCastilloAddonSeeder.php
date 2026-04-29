<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BecCastilloAddonSeeder extends Seeder
{
    public function run(): void
    {
        $user = DB::table('users')
            ->whereRaw("LOWER(fullname) LIKE ?", ['%bec%castillo%'])
            ->where('role', 'seller')
            ->first();

        if (!$user) {
            $this->command->error('Seller "Bec Castillo" not found.');
            return;
        }

        $shop = DB::table('shops')->where('seller_id', $user->id)->first();

        if (!$shop) {
            $this->command->error("Bec Castillo has no registered shop.");
            return;
        }

        $this->command->info("Seeding add-ons for shop ID: {$shop->id}");

        $categories = [
            [
                'name' => 'Toppings',
                'icon' => 'bi-stars',
                'sort_order' => 1,
                'addons' => [
                    ['name' => 'Fresh Strawberries',         'price' => 75,  'description' => 'Fresh whole strawberries arranged on top of your cake.'],
                    ['name' => 'Fresh Blueberries',          'price' => 90,  'description' => 'A generous handful of fresh blueberries for a fruity finish.'],
                    ['name' => 'Fresh Mango Slices',         'price' => 60,  'description' => 'Sweet, ripe mango slices fanned on top of the cake.'],
                    ['name' => 'Chocolate Drizzle',          'price' => 40,  'description' => 'Rich dark or white chocolate drizzled over the top.'],
                    ['name' => 'Rainbow Sprinkles',          'price' => 25,  'description' => 'Colorful rainbow sprinkles for a fun, festive look.'],
                    ['name' => 'Gold Sugar Pearls',          'price' => 35,  'description' => 'Shimmery gold sugar pearls for an elegant touch.'],
                    ['name' => 'Crushed Oreo Crumbs',        'price' => 30,  'description' => 'Finely crushed Oreo cookies sprinkled on top.'],
                    ['name' => 'Toasted Desiccated Coconut', 'price' => 20,  'description' => 'Lightly toasted coconut flakes for a tropical flavor.'],
                ],
            ],
            [
                'name' => 'Fillings',
                'icon' => 'bi-layers',
                'sort_order' => 2,
                'addons' => [
                    ['name' => 'Strawberry Jam',      'price' => 50,  'description' => 'Sweet strawberry jam layered between the cake tiers.'],
                    ['name' => 'Chocolate Ganache',   'price' => 65,  'description' => 'Silky smooth dark chocolate ganache filling.'],
                    ['name' => 'Dulce de Leche',      'price' => 70,  'description' => 'Rich caramel-style dulce de leche spread between layers.'],
                    ['name' => 'Ube Halaya',          'price' => 55,  'description' => 'Classic Filipino ube halaya filling for a purple yam taste.'],
                    ['name' => 'Cream Cheese Filling','price' => 75,  'description' => 'Lightly sweetened cream cheese layered inside the cake.'],
                    ['name' => 'Lemon Curd',          'price' => 60,  'description' => 'Tangy and bright lemon curd filling for a citrusy kick.'],
                    ['name' => 'Bavarian Cream',      'price' => 65,  'description' => 'Light and airy Bavarian cream filling between layers.'],
                ],
            ],
            [
                'name' => 'Decorations',
                'icon' => 'bi-flower1',
                'sort_order' => 3,
                'addons' => [
                    ['name' => 'Custom Fondant Cake Topper', 'price' => 150, 'description' => 'Hand-crafted fondant topper customized to your theme.'],
                    ['name' => 'Edible Photo Print',         'price' => 120, 'description' => 'High-quality edible photo printed on rice paper. Send photo after ordering.'],
                    ['name' => 'Sugar Flowers (set of 5)',   'price' => 100, 'description' => 'Delicate hand-made sugar flowers in your choice of color.'],
                    ['name' => 'Chocolate Bark Shards',      'price' => 80,  'description' => 'Artistic chocolate bark shards for a dramatic look.'],
                    ['name' => 'Macarons (3 pcs)',           'price' => 95,  'description' => 'Three mini French macarons placed on or beside the cake.'],
                    ['name' => 'Edible Gold Leaf Accent',    'price' => 130, 'description' => 'Luxurious edible gold leaf applied for a premium finish.'],
                    ['name' => 'Fondant Number Topper',      'price' => 85,  'description' => 'Fondant number topper for milestone birthdays and anniversaries.'],
                    ['name' => 'Hand-Piped Rosettes (6 pcs)','price' => 70,  'description' => 'Six beautifully piped buttercream rosettes in your chosen color.'],
                ],
            ],
            [
                'name' => 'Candles & Extras',
                'icon' => 'bi-fire',
                'sort_order' => 4,
                'addons' => [
                    ['name' => 'Birthday Candle Set (12 pcs)', 'price' => 35, 'description' => 'Set of 12 classic birthday candles in assorted colors.'],
                    ['name' => 'Sparkler Candles (2 pcs)',     'price' => 55, 'description' => 'Two gold sparkler candles for a dazzling birthday moment.'],
                    ['name' => 'Musical Birthday Candle',      'price' => 70, 'description' => 'Spinning musical candle that plays Happy Birthday.'],
                    ['name' => 'Letter / Number Candle',       'price' => 45, 'description' => 'Gold or silver letter/number candles. Specify in order notes.'],
                    ['name' => 'Cake Pop Add-on (2 pcs)',      'price' => 80, 'description' => 'Two cake pops in matching flavor and design.'],
                    ['name' => 'Scented Ribbon Bow',           'price' => 25, 'description' => 'Decorative satin ribbon bow tied around the cake box.'],
                ],
            ],
            [
                'name' => 'Packaging',
                'icon' => 'bi-box-seam',
                'sort_order' => 5,
                'addons' => [
                    ['name' => 'Premium Window Cake Box',  'price' => 85,  'description' => 'Elegant cake box with a clear window to display your cake.'],
                    ['name' => 'Kraft Paper Gift Bag',     'price' => 35,  'description' => 'Eco-friendly kraft paper bag with handles for easy carrying.'],
                    ['name' => 'Personalized Cake Tag',    'price' => 25,  'description' => 'Custom printed card tag with your personal message.'],
                    ['name' => 'Satin Ribbon Decoration',  'price' => 30,  'description' => 'Premium satin ribbon tied around the box for gifting.'],
                    ['name' => 'Dry Ice Packaging',        'price' => 100, 'description' => 'Insulated dry ice packaging to keep your cake fresh during transport.'],
                ],
            ],
        ];

        $catInserted = 0;
        $addonInserted = 0;
        $skipped = 0;

        foreach ($categories as $catData) {
            $existing = DB::table('cake_addon_categories')
                ->where('shop_id', $shop->id)
                ->whereRaw("LOWER(name) = ?", [strtolower($catData['name'])])
                ->first();

            if ($existing) {
                $catId = $existing->id;
                $this->command->warn("  Category exists: {$catData['name']}");
            } else {
                $catId = DB::table('cake_addon_categories')->insertGetId([
                    'shop_id'    => $shop->id,
                    'name'       => $catData['name'],
                    'icon'       => $catData['icon'],
                    'sort_order' => $catData['sort_order'],
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->line("  + Category: {$catData['name']}");
                $catInserted++;
            }

            foreach ($catData['addons'] as $addonData) {
                $addonExists = DB::table('cake_addons')
                    ->where('shop_id', $shop->id)
                    ->where('category_id', $catId)
                    ->whereRaw("LOWER(name) = ?", [strtolower($addonData['name'])])
                    ->exists();

                if ($addonExists) {
                    $skipped++;
                    continue;
                }

                DB::table('cake_addons')->insert([
                    'shop_id'     => $shop->id,
                    'category_id' => $catId,
                    'name'        => $addonData['name'],
                    'description' => $addonData['description'],
                    'price'       => $addonData['price'],
                    'is_active'   => true,
                    'sort_order'  => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $this->command->line("      + {$addonData['name']} — ₱{$addonData['price']}");
                $addonInserted++;
            }
        }

        $this->command->info("Done. Categories: {$catInserted} inserted | Add-ons: {$addonInserted} inserted | {$skipped} skipped (already exist)");
    }
}
