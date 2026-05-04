<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $user = DB::table('users')
            ->where('role', 'seller')
            ->where(function ($query) {
                $query->whereRaw('LOWER(fullname) = ?', ['bec castillo'])
                    ->orWhere('email', 'barrozos004@gmail.com')
                    ->orWhere('username', 'bec-castillo50');
            })
            ->first();

        if (!$user) {
            return;
        }

        $shop = DB::table('shops')->where('seller_id', $user->id)->first();
        if (!$shop) {
            return;
        }

        $categories = [
            [
                'name' => 'Fresh Fruit Toppings',
                'icon' => 'bi-flower1',
                'sort_order' => 1,
                'addons' => [
                    ['Fresh Mango Slices', 75, 'Sweet Philippine mango slices arranged on top or between cake layers.'],
                    ['Fresh Strawberry Topping', 95, 'Fresh strawberries for drip cakes, cream cakes, and birthday cakes.'],
                    ['Blueberry Compote', 90, 'House-style blueberry compote for topping or filling.'],
                    ['Mixed Fruit Crown', 130, 'Seasonal fruit topping with mango, grapes, kiwi, and cherries when available.'],
                    ['Cherry Topping', 45, 'Classic red cherries for borders and cake center accents.'],
                ],
            ],
            [
                'name' => 'Fillings & Creams',
                'icon' => 'bi-layers',
                'sort_order' => 2,
                'addons' => [
                    ['Chocolate Ganache Filling', 85, 'Rich chocolate ganache layer for chocolate and mocha cakes.'],
                    ['Cream Cheese Filling', 95, 'Lightly sweet cream cheese filling for red velvet, carrot, and fruit cakes.'],
                    ['Ube Halaya Filling', 80, 'Classic Filipino ube halaya filling for custom ube cakes.'],
                    ['Yema Custard Filling', 85, 'Sweet yema custard filling for soft chiffon cakes.'],
                    ['Mango Cream Filling', 90, 'Mango cream layer for tropical celebration cakes.'],
                    ['Dulce de Leche Filling', 90, 'Caramel-style filling for mocha, vanilla, and chocolate cakes.'],
                ],
            ],
            [
                'name' => 'Decorations & Toppers',
                'icon' => 'bi-stars',
                'sort_order' => 3,
                'addons' => [
                    ['Acrylic Happy Birthday Topper', 120, 'Reusable acrylic birthday topper in gold, silver, or clear style.'],
                    ['Custom Name Topper', 150, 'Personalized acrylic or cardstock name topper.'],
                    ['Fondant Number Topper', 100, 'Handmade fondant age or anniversary number topper.'],
                    ['Fondant Character Accent', 180, 'Small handmade fondant character or themed accent.'],
                    ['Edible Photo Print', 180, 'Printed edible image for character, logo, or photo cakes.'],
                    ['Buttercream Rosettes', 75, 'Additional piped buttercream rosettes in customer-preferred colors.'],
                    ['Sugar Flowers Set', 130, 'Set of handmade sugar flowers for elegant cakes.'],
                    ['Edible Gold Accent', 150, 'Gold leaf or gold sugar accent for premium designs.'],
                ],
            ],
            [
                'name' => 'Chocolate & Crunch',
                'icon' => 'bi-grid-3x3-gap',
                'sort_order' => 4,
                'addons' => [
                    ['Chocolate Drip', 55, 'Chocolate drip finish around the cake edge.'],
                    ['White Chocolate Drip', 65, 'White chocolate drip for pastel and minimalist cakes.'],
                    ['Oreo Crumble', 45, 'Crushed Oreo topping or side coating.'],
                    ['Chocolate Shards', 85, 'Decorative chocolate shards for tall and drip cakes.'],
                    ['Mini Chocolate Bars', 110, 'Assorted mini chocolate bars for loaded cake designs.'],
                    ['Roasted Almonds', 70, 'Roasted almond topping for crunch and texture.'],
                    ['Caramel Sauce Drizzle', 55, 'Caramel drizzle for mocha, vanilla, and mango cakes.'],
                ],
            ],
            [
                'name' => 'Candles & Celebration Extras',
                'icon' => 'bi-fire',
                'sort_order' => 5,
                'addons' => [
                    ['Classic Candle Set', 35, 'Pack of standard birthday candles.'],
                    ['Number Candle', 60, 'Single number candle in gold or silver.'],
                    ['Sparkler Candles', 75, 'Pair of sparkler candles for birthdays and surprise cakes.'],
                    ['Mini Greeting Card', 25, 'Small greeting card with customer message.'],
                    ['Cake Knife & Server Set', 45, 'Disposable cake knife and server for events.'],
                    ['Money Pulling Setup', 180, 'Money-pulling cake setup; bills are provided separately by the customer.'],
                ],
            ],
            [
                'name' => 'Gift Packaging',
                'icon' => 'bi-box-seam',
                'sort_order' => 6,
                'addons' => [
                    ['Premium Window Cake Box', 85, 'Sturdy white window box for gift-ready presentation.'],
                    ['Ribbon Wrap', 35, 'Ribbon around the cake box in a matching color.'],
                    ['Kraft Paper Carry Bag', 40, 'Carry bag for safer pickup and gifting.'],
                    ['Insulated Delivery Box', 120, 'Extra insulated packaging for longer delivery distance.'],
                ],
            ],
        ];

        foreach ($categories as $category) {
            $categoryId = DB::table('cake_addon_categories')
                ->where('shop_id', $shop->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($category['name'])])
                ->value('id');

            if (!$categoryId) {
                $categoryRow = [
                    'shop_id' => $shop->id,
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                    'created_at' => now(),
                ];

                if (Schema::hasColumn('cake_addon_categories', 'updated_at')) {
                    $categoryRow['updated_at'] = now();
                }

                $categoryId = DB::table('cake_addon_categories')->insertGetId($categoryRow);
            }

            foreach ($category['addons'] as $index => [$name, $price, $description]) {
                $exists = DB::table('cake_addons')
                    ->where('shop_id', $shop->id)
                    ->where('category_id', $categoryId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $addonRow = [
                    'shop_id' => $shop->id,
                    'category_id' => $categoryId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                ];

                if (Schema::hasColumn('cake_addons', 'updated_at')) {
                    $addonRow['updated_at'] = now();
                }

                DB::table('cake_addons')->insert($addonRow);
            }
        }
    }

    public function down(): void
    {
        $user = DB::table('users')
            ->where('role', 'seller')
            ->where(function ($query) {
                $query->whereRaw('LOWER(fullname) = ?', ['bec castillo'])
                    ->orWhere('email', 'barrozos004@gmail.com')
                    ->orWhere('username', 'bec-castillo50');
            })
            ->first();

        if (!$user) {
            return;
        }

        $shop = DB::table('shops')->where('seller_id', $user->id)->first();
        if (!$shop) {
            return;
        }

        $categoryNames = [
            'Fresh Fruit Toppings',
            'Fillings & Creams',
            'Decorations & Toppers',
            'Chocolate & Crunch',
            'Candles & Celebration Extras',
            'Gift Packaging',
        ];

        $categoryIds = DB::table('cake_addon_categories')
            ->where('shop_id', $shop->id)
            ->whereIn('name', $categoryNames)
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        DB::table('cake_addons')->where('shop_id', $shop->id)->whereIn('category_id', $categoryIds)->delete();
        DB::table('cake_addon_categories')->where('shop_id', $shop->id)->whereIn('id', $categoryIds)->delete();
    }
};
