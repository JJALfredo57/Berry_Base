<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\CakeshopHelper;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Site Settings ─────────────────────────────────────────────────
        if (!DB::table('site_settings')->where('id',1)->exists()) {
            DB::table('site_settings')->insert([
                'id'             => 1,
                'site_title'     => 'My Cake Shop',
                'tagline'        => 'Order Fresh Cakes Online!',
                'logo_path'      => '',
                'bg_type'        => 'gradient',
                'bg_color'       => '#fff7fb',
                'gradient_start' => '#fff7fb',
                'gradient_end'   => '#ffe3f1',
                'bg_image_path'  => null,
                'bg_image_opacity' => 1.0,
                'primary_color'  => '#e91e63',
                'updated_at'     => now(),
            ]);
        }

        // ── Products ──────────────────────────────────────────────────────
        if (DB::table('products')->count() === 0) {
            $products = [
                // Standard cakes
                ['name'=>'Chocolate Cake',        'desc'=>'Rich dark chocolate layered cake with ganache frosting.',         'price'=>850.00,  'classification'=>'Standard', 'flavor'=>'Chocolate'],
                ['name'=>'Strawberry Shortcake',  'desc'=>'Light sponge cake with fresh strawberries and whipped cream.',    'price'=>750.00,  'classification'=>'Standard', 'flavor'=>'Strawberry'],
                ['name'=>'Ube Macapuno Cake',      'desc'=>'Classic Filipino ube cake with creamy macapuno filling.',         'price'=>900.00,  'classification'=>'Standard', 'flavor'=>'Ube'],
                ['name'=>'Red Velvet Cake',        'desc'=>'Velvety red cake with cream cheese frosting.',                   'price'=>880.00,  'classification'=>'Standard', 'flavor'=>'Red Velvet'],
                ['name'=>'Mango Supreme Cake',     'desc'=>'Fresh mango layers with light cream — perfect for summer.',      'price'=>820.00,  'classification'=>'Standard', 'flavor'=>'Mango'],
                ['name'=>'Caramel Crunch Cake',    'desc'=>'Buttery caramel layers with crunchy toffee bits.',               'price'=>870.00,  'classification'=>'Standard', 'flavor'=>'Caramel'],
                ['name'=>'Mocha Chiffon Cake',     'desc'=>'Soft chiffon cake with a hint of espresso and mocha cream.',     'price'=>800.00,  'classification'=>'Standard', 'flavor'=>'Mocha'],
                ['name'=>'Vanilla Bliss Cake',     'desc'=>'Classic vanilla sponge with fresh cream and sprinkles.',         'price'=>720.00,  'classification'=>'Standard', 'flavor'=>'Vanilla'],
                // Fondant cakes
                ['name'=>'Princess Fondant Cake',  'desc'=>'Decorated fondant cake with princess theme — great for kids.',  'price'=>1500.00, 'classification'=>'Fondant',   'flavor'=>'Vanilla'],
                ['name'=>'Galaxy Fondant Cake',    'desc'=>'Stunning galaxy-inspired fondant design.',                      'price'=>1800.00, 'classification'=>'Fondant',   'flavor'=>'Chocolate'],
                ['name'=>'Floral Fondant Cake',    'desc'=>'Elegant fondant cake with handcrafted sugar flowers.',           'price'=>1600.00, 'classification'=>'Fondant',   'flavor'=>'Strawberry'],
                // Perishable / refrigerated
                ['name'=>'Bento Cake (4")',        'desc'=>'Cute individual-sized bento cake, perfect as a personal treat.', 'price'=>280.00,  'classification'=>'Perishable','flavor'=>'Chocolate'],
                ['name'=>'Crepe Cake',             'desc'=>'20 layers of thin crepes with luscious cream filling.',          'price'=>950.00,  'classification'=>'Perishable','flavor'=>'Vanilla'],
                ['name'=>'Brazo de Mercedes',      'desc'=>'Rolled meringue cake with rich egg yolk custard center.',        'price'=>650.00,  'classification'=>'Perishable','flavor'=>'Vanilla'],
            ];

            $sizes = [
                'Chocolate Cake'       => [['6"',0],['8"',200],['10"',400],['12"',700]],
                'Strawberry Shortcake' => [['6"',0],['8"',200],['10"',400]],
                'Ube Macapuno Cake'    => [['6"',0],['8"',200],['10"',400],['12"',700]],
                'Red Velvet Cake'      => [['6"',0],['8"',200],['10"',400],['12"',700]],
                'Mango Supreme Cake'   => [['6"',0],['8"',200],['10"',400]],
                'Caramel Crunch Cake'  => [['6"',0],['8"',200],['10"',400]],
                'Mocha Chiffon Cake'   => [['6"',0],['8"',200],['10"',400]],
                'Vanilla Bliss Cake'   => [['6"',0],['8"',200],['10"',400]],
                'Princess Fondant Cake'=> [['6"',0],['8"',300],['10"',600]],
                'Galaxy Fondant Cake'  => [['6"',0],['8"',300],['10"',600]],
                'Floral Fondant Cake'  => [['6"',0],['8"',300],['10"',600]],
            ];

            foreach ($products as $p) {
                $pid = CakeshopHelper::generateId('products');
                DB::table('products')->insert([
                    'id'             => $pid,
                    'name'           => $p['name'],
                    'description'    => $p['desc'],
                    'price'          => $p['price'],
                    'image_path'     => '/storage/uploads/products/default.png',
                    'classification' => $p['classification'],
                    'flavor'         => $p['flavor'],
                    'is_available'   => 1,
                    'created_at'     => now(),
                ]);

                // Insert sizes if defined
                if (isset($sizes[$p['name']])) {
                    foreach ($sizes[$p['name']] as $i => $sz) {
                        DB::table('product_sizes')->insert([
                            'product_id' => $pid,
                            'label'      => $sz[0],
                            'price'      => $p['price'] + $sz[1],
                            'sort_order' => $i + 1,
                            'is_active'  => true,
                            'created_at' => now(),
                        ]);
                    }
                }
            }
        }

        // ── Cake Addon Categories ─────────────────────────────────────────
        if (DB::table('cake_addon_categories')->count() === 0) {
            $cats = [
                ['🎨 Design / Decorations', 'bi-palette',      1],
                ['🍓 Toppings',             'bi-grid-3x3-gap', 2],
                ['🍫 Fillings',             'bi-layers',       3],
                ['✨ Other Add-ons',         'bi-stars',        4],
            ];
            foreach ($cats as $c) {
                DB::table('cake_addon_categories')->insert([
                    'name' => $c[0], 'icon' => $c[1], 'sort_order' => $c[2],
                    'is_active' => true, 'created_at' => now(),
                ]);
            }
        }

        // ── Cake Addons ───────────────────────────────────────────────────
        if (DB::table('cake_addons')->count() === 0) {
            $catId = fn($n) => DB::table('cake_addon_categories')->where('name','like',"%{$n}%")->value('id');

            $addons = [
                // Design
                [$catId('Design'), 'Birthday Theme',           null,                      50.00,  1, 1],
                [$catId('Design'), 'Graduation Theme',         null,                      50.00,  1, 2],
                [$catId('Design'), 'Wedding Theme',            null,                      80.00,  1, 3],
                [$catId('Design'), 'Cartoon Theme',            null,                      80.00,  1, 4],
                [$catId('Design'), 'Name of Celebrant',        'Text on cake',            50.00,  1, 5],
                [$catId('Design'), 'Age Number',               'e.g. 18, 21, 50',         50.00,  1, 6],
                [$catId('Design'), 'Fondant Characters',       'Custom fondant figures',  150.00, 1, 7],
                [$catId('Design'), 'Photo Print (Edible)',     'Edible image on top',     200.00, 1, 8],
                [$catId('Design'), '3D Cake Design',           null,                      350.00, 1, 9],
                [$catId('Design'), 'Acrylic Cake Topper',      null,                      120.00, 1, 10],
                [$catId('Design'), 'LED Cake Topper',          'Light-up topper',         150.00, 1, 11],
                [$catId('Design'), 'Balloon Decorations',      'Set of balloons',         100.00, 1, 12],
                // Toppings
                [$catId('Toppings'), 'Fresh Strawberries',     null,   80.00, 1, 1],
                [$catId('Toppings'), 'Fresh Mango',            null,   70.00, 1, 2],
                [$catId('Toppings'), 'Blueberries',            null,   90.00, 1, 3],
                [$catId('Toppings'), 'Chocolate Drizzle',      null,   50.00, 1, 4],
                [$catId('Toppings'), 'Sprinkles',              null,   30.00, 1, 5],
                [$catId('Toppings'), 'Oreo / Biscuits',        null,   60.00, 1, 6],
                [$catId('Toppings'), 'Candy / Marshmallow',    null,   50.00, 1, 7],
                [$catId('Toppings'), 'Almonds / Peanuts',      null,   60.00, 1, 8],
                [$catId('Toppings'), 'Caramel Sauce',          null,   50.00, 1, 9],
                [$catId('Toppings'), 'White Chocolate Chips',  null,   60.00, 1, 10],
                // Fillings
                [$catId('Fillings'), 'Chocolate Filling',      null,   80.00, 1, 1],
                [$catId('Fillings'), 'Ube Filling',            null,   80.00, 1, 2],
                [$catId('Fillings'), 'Custard Filling',        null,   70.00, 1, 3],
                [$catId('Fillings'), 'Strawberry Jam',         null,   70.00, 1, 4],
                [$catId('Fillings'), 'Mango Cream',            null,   80.00, 1, 5],
                [$catId('Fillings'), 'Cream Cheese',           null,   90.00, 1, 6],
                [$catId('Fillings'), 'Leche Flan',             null,   100.00,1, 7],
                // Other
                [$catId('Other'), 'Number Candles',            null,   60.00, 1, 1],
                [$catId('Other'), 'Money Cake',                'Bills inside the cake', 100.00, 1, 2],
                [$catId('Other'), 'Surprise Cake',             'Candy inside',  120.00, 1, 3],
                [$catId('Other'), 'Message on Cake Box',       null,   30.00,  1, 4],
                [$catId('Other'), 'Customized Cake Box',       null,   150.00, 1, 5],
                [$catId('Other'), 'Gift Wrapping',             null,   80.00,  1, 6],
            ];

            foreach ($addons as $a) {
                DB::table('cake_addons')->insert([
                    'category_id' => $a[0], 'name' => $a[1], 'description' => $a[2],
                    'price' => $a[3], 'is_active' => (bool)$a[4], 'sort_order' => $a[5],
                    'created_at' => now(),
                ]);
            }
        }

        // ── Custom Order Options ──────────────────────────────────────────
        if (DB::table('custom_order_options')->count() === 0) {
            $options = [
                // Flavors
                ['flavor','Chocolate',   0,    null, 1, 1],
                ['flavor','Vanilla',     0,    null, 1, 2],
                ['flavor','Red Velvet',  0,    null, 1, 3],
                ['flavor','Ube',         0,    null, 1, 4],
                ['flavor','Caramel',     0,    null, 1, 5],
                ['flavor','Mango',       0,    null, 1, 6],
                ['flavor','Strawberry',  0,    null, 1, 7],
                ['flavor','Mocha',       0,    null, 1, 8],
                ['flavor','Buko Pandan', 0,    null, 1, 9],
                ['flavor','Lemon',       0,    null, 1, 10],
                // Sizes
                ['size','6"',  0,    'Perfect for 6–8 persons',    1, 1],
                ['size','8"',  200,  'Good for 10–15 persons',     1, 2],
                ['size','10"', 400,  'Serves 20–25 persons',       1, 3],
                ['size','12"', 700,  'Large event, 30+ persons',   1, 4],
                // Layers
                ['layer','1 Layer',         0, null, 1, 1],
                ['layer','2 Layers',        0, null, 1, 2],
                ['layer','3 Layers',        0, null, 1, 3],
                ['layer','4 Layers (Tiered)',0, null, 1, 4],
                // Complexity
                ['complexity','Simple',                        0,    'Basic design, text only',          1, 1],
                ['complexity','Moderate',                      300,  'Some decorations, fondant accents', 1, 2],
                ['complexity','Complex',                       600,  'Detailed design, multiple elements', 1, 3],
                ['complexity','Highly Complex (3D / Fondant)', 1200, 'Full fondant, 3D characters',       1, 4],
                // Time Slots
                ['time_slot','9:00 AM – 11:00 AM', 0, null, 1, 1],
                ['time_slot','11:00 AM – 1:00 PM', 0, null, 1, 2],
                ['time_slot','1:00 PM – 3:00 PM',  0, null, 1, 3],
                ['time_slot','3:00 PM – 5:00 PM',  0, null, 1, 4],
                ['time_slot','5:00 PM – 7:00 PM',  0, null, 1, 5],
            ];

            foreach ($options as $o) {
                DB::table('custom_order_options')->insert([
                    'type' => $o[0], 'label' => $o[1], 'price' => $o[2],
                    'description' => $o[3], 'is_active' => (bool)$o[4], 'sort_order' => $o[5],
                    'created_at' => now(),
                ]);
            }
        }

        // ── Delivery Zones (Bautista, Pangasinan) ─────────────────────────
        if (DB::table('delivery_zones')->count() === 0) {
            $zones = [
                ['Poblacion East',  0,   'free', 1],
                ['Poblacion West',  0,   'free', 2],
                ['Artacho',        50,   'near', 3],
                ['Baluyot',        50,   'near', 4],
                ['Cabuaan',        50,   'near', 5],
                ['Canan East',     50,   'near', 6],
                ['Canan West',     50,   'near', 7],
                ['Caoayan East',   80,   'mid',  8],
                ['Caoayan West',   80,   'mid',  9],
                ['Comisel',        80,   'mid',  10],
                ['Dilan',          80,   'mid',  11],
                ['Palua',          80,   'mid',  12],
                ['Pansipic',       80,   'mid',  13],
                ['Posong',         100,  'far',  14],
                ['Quibaol',        100,  'far',  15],
                ['Rang-ay',        100,  'far',  16],
                ['San Miguel',     100,  'far',  17],
                ['Villa Paraiso',  100,  'far',  18],
                ['Out of Coverage (Meet-up / Negotiate)', 250, 'ooc', 19],
            ];
            foreach ($zones as $i => $z) {
                DB::table('delivery_zones')->insert([
                    'barangay'   => $z[0], 'fee' => $z[1],
                    'zone_type'  => $z[2], 'is_active' => 1,
                    'sort_order' => $z[3], 'created_at' => now(),
                ]);
            }
        }
    }
}
