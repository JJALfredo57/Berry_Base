<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\CakeshopHelper;

class JamieShopSeeder extends Seeder
{
    public function run(): void
    {
        $user = DB::table('users')
            ->whereRaw("LOWER(fullname) LIKE ?", ['%jamie%'])
            ->where('role', 'seller')
            ->first();

        if (!$user) {
            $this->command->error('Seller "Jamie" not found.');
            return;
        }

        $shop = DB::table('shops')->where('seller_id', $user->id)->first();

        if (!$shop) {
            $this->command->error("Jamie has no registered shop.");
            return;
        }

        $this->command->info("Seeding products & add-ons for: {$user->fullname} → {$shop->shop_name}");

        // ── PRODUCTS ──────────────────────────────────────────────────
        $products = [
            [
                'name'           => 'Red Velvet Anniversary Cake',
                'description'    => 'Rich red velvet cake topped with hand-piped "Happy Anniversary" lettering in white cream, scattered gold leaf flakes, and delicate heart accents. Packed in a clear clamshell for safe delivery.',
                'price'          => 699,
                'classification' => 'Anniversary Cake',
                'flavor'         => 'Red Velvet',
            ],
            [
                'name'           => 'White Monogram Ruffle Cake',
                'description'    => 'Elegant all-white cream cake with layered ruffle piping and a bold black custom monogram print centerpiece on top. Clean, sophisticated, and perfect for weddings, anniversaries, or debut celebrations.',
                'price'          => 799,
                'classification' => 'Special Occasion Cake',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'My Melody Themed Birthday Cake',
                'description'    => 'Adorable pink cream cake featuring My Melody character cutouts, rainbow sprinkles, mini marshmallows, and a glittery number topper. A dream cake for kids and Sanrio fans of any age.',
                'price'          => 999,
                'classification' => 'Themed Cake',
                'flavor'         => 'Strawberry',
            ],
            [
                'name'           => 'Pink Butterfly Birthday Cake',
                'description'    => 'White and blush pink cake with scalloped rosette border, butterfly toppers in pink and gold, pink ball clusters on the sides, and edible gold leaf accents. Finished with a gold acrylic "Happy Birthday" topper.',
                'price'          => 899,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Vanilla',
            ],
        ];

        $prodInserted = 0;
        foreach ($products as $data) {
            $exists = DB::table('products')
                ->where('shop_id', $shop->id)
                ->whereRaw("LOWER(name) = ?", [strtolower($data['name'])])
                ->exists();

            if ($exists) {
                $this->command->warn("  Skipped product: {$data['name']}");
                continue;
            }

            DB::table('products')->insert([
                'id'             => CakeshopHelper::generateId('products'),
                'shop_id'        => $shop->id,
                'name'           => $data['name'],
                'description'    => $data['description'],
                'price'          => $data['price'],
                'image_path'     => '',
                'classification' => $data['classification'],
                'flavor'         => $data['flavor'],
                'is_available'   => true,
                'sort_order'     => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $this->command->line("  + Product: {$data['name']} — ₱{$data['price']}");
            $prodInserted++;
        }

        // ── ADD-ON CATEGORIES & ADD-ONS ───────────────────────────────
        $categories = [
            [
                'name'       => 'Toppings',
                'icon'       => 'bi-stars',
                'sort_order' => 1,
                'addons'     => [
                    ['name' => 'Fresh Strawberries',          'price' => 75,  'description' => 'Fresh whole strawberries arranged on top of your cake.'],
                    ['name' => 'Fresh Blueberries',           'price' => 90,  'description' => 'A generous handful of fresh blueberries for a fruity finish.'],
                    ['name' => 'Fresh Mango Slices',          'price' => 60,  'description' => 'Sweet ripe mango slices fanned on top of the cake.'],
                    ['name' => 'Chocolate Drizzle',           'price' => 40,  'description' => 'Rich dark or white chocolate drizzled over the top.'],
                    ['name' => 'Rainbow Sprinkles',           'price' => 25,  'description' => 'Colorful rainbow sprinkles for a fun, festive look.'],
                    ['name' => 'Mini Marshmallows',           'price' => 30,  'description' => 'Soft mini marshmallows scattered around the cake.'],
                    ['name' => 'Gold Sugar Pearls',           'price' => 35,  'description' => 'Shimmery gold sugar pearls for an elegant touch.'],
                    ['name' => 'Edible Gold Leaf Flakes',     'price' => 55,  'description' => 'Luxurious edible gold leaf flakes scattered on top.'],
                ],
            ],
            [
                'name'       => 'Fillings',
                'icon'       => 'bi-layers',
                'sort_order' => 2,
                'addons'     => [
                    ['name' => 'Strawberry Jam',       'price' => 50,  'description' => 'Sweet strawberry jam layered between the cake tiers.'],
                    ['name' => 'Cream Cheese Filling', 'price' => 75,  'description' => 'Lightly sweetened cream cheese layered inside the cake.'],
                    ['name' => 'Chocolate Ganache',    'price' => 65,  'description' => 'Silky smooth dark chocolate ganache filling.'],
                    ['name' => 'Ube Halaya',           'price' => 55,  'description' => 'Classic Filipino ube halaya filling for a purple yam taste.'],
                    ['name' => 'Dulce de Leche',       'price' => 70,  'description' => 'Rich caramel-style dulce de leche spread between layers.'],
                    ['name' => 'Lemon Curd',           'price' => 60,  'description' => 'Tangy and bright lemon curd filling for a citrusy kick.'],
                ],
            ],
            [
                'name'       => 'Decorations',
                'icon'       => 'bi-flower1',
                'sort_order' => 3,
                'addons'     => [
                    ['name' => 'Custom Fondant Cake Topper',     'price' => 150, 'description' => 'Hand-crafted fondant topper customized to your theme.'],
                    ['name' => 'Edible Photo Print',             'price' => 120, 'description' => 'High-quality edible photo printed on rice paper. Send photo after ordering.'],
                    ['name' => 'Gold Acrylic Birthday Topper',   'price' => 85,  'description' => 'Shiny gold acrylic "Happy Birthday" script topper.'],
                    ['name' => 'Butterfly Cake Toppers (3 pcs)', 'price' => 80,  'description' => 'Three decorative butterfly toppers in assorted colors.'],
                    ['name' => 'Sugar Flowers (set of 5)',       'price' => 100, 'description' => 'Delicate hand-made sugar flowers in your choice of color.'],
                    ['name' => 'Fondant Number Topper',          'price' => 85,  'description' => 'Fondant number topper for milestone birthdays and anniversaries.'],
                    ['name' => 'Hand-Piped Rosettes (6 pcs)',    'price' => 70,  'description' => 'Six beautifully piped buttercream rosettes in your chosen color.'],
                    ['name' => 'Macarons (3 pcs)',               'price' => 95,  'description' => 'Three mini French macarons placed on or beside the cake.'],
                ],
            ],
            [
                'name'       => 'Candles & Extras',
                'icon'       => 'bi-fire',
                'sort_order' => 4,
                'addons'     => [
                    ['name' => 'Birthday Candle Set (12 pcs)', 'price' => 35, 'description' => 'Set of 12 classic birthday candles in assorted colors.'],
                    ['name' => 'Sparkler Candles (2 pcs)',     'price' => 55, 'description' => 'Two gold sparkler candles for a dazzling birthday moment.'],
                    ['name' => 'Musical Birthday Candle',      'price' => 70, 'description' => 'Spinning musical candle that plays Happy Birthday.'],
                    ['name' => 'Letter / Number Candle',       'price' => 45, 'description' => 'Gold or silver letter/number candles. Specify in order notes.'],
                    ['name' => 'Cake Pop Add-on (2 pcs)',      'price' => 80, 'description' => 'Two cake pops in matching flavor and design.'],
                    ['name' => 'Scented Ribbon Bow',           'price' => 25, 'description' => 'Decorative satin ribbon bow tied around the cake box.'],
                ],
            ],
            [
                'name'       => 'Packaging',
                'icon'       => 'bi-box-seam',
                'sort_order' => 5,
                'addons'     => [
                    ['name' => 'Premium Window Cake Box', 'price' => 85,  'description' => 'Elegant cake box with a clear window to showcase your cake.'],
                    ['name' => 'Personalized Cake Tag',   'price' => 25,  'description' => 'Custom printed card tag with your personal message.'],
                    ['name' => 'Satin Ribbon Decoration', 'price' => 30,  'description' => 'Premium satin ribbon tied around the box for gifting.'],
                    ['name' => 'Kraft Paper Gift Bag',    'price' => 35,  'description' => 'Eco-friendly kraft paper bag with handles for easy carrying.'],
                    ['name' => 'Dry Ice Packaging',       'price' => 100, 'description' => 'Insulated dry ice packaging to keep your cake fresh during transport.'],
                ],
            ],
        ];

        $catInserted   = 0;
        $addonInserted = 0;

        foreach ($categories as $catData) {
            $existing = DB::table('cake_addon_categories')
                ->where('shop_id', $shop->id)
                ->whereRaw("LOWER(name) = ?", [strtolower($catData['name'])])
                ->first();

            $catId = $existing
                ? $existing->id
                : DB::table('cake_addon_categories')->insertGetId([
                    'shop_id'    => $shop->id,
                    'name'       => $catData['name'],
                    'icon'       => $catData['icon'],
                    'sort_order' => $catData['sort_order'],
                    'is_active'  => true,
                    'created_at' => now(),
                ]);

            if (!$existing) {
                $this->command->line("  + Category: {$catData['name']}");
                $catInserted++;
            }

            foreach ($catData['addons'] as $addonData) {
                if (DB::table('cake_addons')
                    ->where('shop_id', $shop->id)
                    ->where('category_id', $catId)
                    ->whereRaw("LOWER(name) = ?", [strtolower($addonData['name'])])
                    ->exists()) {
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
                ]);
                $this->command->line("      + {$addonData['name']} — ₱{$addonData['price']}");
                $addonInserted++;
            }
        }

        $this->command->info("Done. Products: {$prodInserted} | Categories: {$catInserted} | Add-ons: {$addonInserted}");
        $this->command->warn('Note: Upload product images via Seller → Products dashboard.');
    }
}
