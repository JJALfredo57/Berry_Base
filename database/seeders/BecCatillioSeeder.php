<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BecCatillioSeeder extends Seeder
{
    /**
     * Seed sample seller account, shop, and products for Bec Catillio.
     * Run with: php artisan db:seed --class=BecCatillioSeeder
     */
    public function run(): void
    {
        $now = now();

        // ── 1. Seller user ────────────────────────────────────────
        $user = DB::table('users')->where('email', 'bec.catillio@cakeshop.test')->first();
        if (!$user) {
            $userId = Str::random(10);
            DB::table('users')->insert([
                'id'            => $userId,
                'fullname'      => 'Bec Catillio',
                'email'         => 'bec.catillio@cakeshop.test',
                'phone'         => '09170000001',
                'password'      => password_hash('BecCakes@2026', PASSWORD_DEFAULT),
                'username'      => 'bec_catillio',
                'role'          => 'seller',
                'is_verified'   => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $this->command->info("✅ Seller user created: bec.catillio@cakeshop.test / BecCakes@2026");
        } else {
            $userId = $user->id;
            $this->command->info("ℹ️  Seller user already exists — skipping user creation.");
        }

        // ── 2. Shop ───────────────────────────────────────────────
        $shop = DB::table('shops')->where('seller_id', $userId)->first();
        if (!$shop) {
            $shopId = Str::random(10);
            DB::table('shops')->insert([
                'id'              => $shopId,
                'seller_id'       => $userId,
                'shop_name'       => "Bec's Cakeshop",
                'shop_slug'       => 'becs-cakeshop',
                'description'     => "Handcrafted cakes made with love by Bec Catillio. Specializing in custom cakes, themed cakes, and classic flavors for every celebration.",
                'address'         => 'Lingayen, Pangasinan',
                'city'            => 'Lingayen',
                'contact_number'  => '09170000001',
                'status'          => 'approved',
                'tier'            => 'verified',
                'commission_rate' => 2.00,
                'verified_at'     => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $this->command->info("✅ Shop created: Bec's Cakeshop (approved/verified)");
        } else {
            $shopId = $shop->id;
            $this->command->info("ℹ️  Shop already exists — skipping shop creation.");
        }

        // ── 3. Products ───────────────────────────────────────────
        $existingNames = DB::table('products')
            ->where('shop_id', $shopId)
            ->pluck('name')
            ->map(fn($n) => strtolower($n))
            ->toArray();

        $products = [
            [
                'name'           => 'Hello Kitty Birthday Cake',
                'description'    => 'Whimsical Hello Kitty themed birthday cake with pink ruffled buttercream, Hello Kitty figurines, and personalized topper. A dream cake for every Hello Kitty fan.',
                'price'          => 1800.0,
                'image_path'     => '/storage/uploads/products/20260414105851_df781209a412.jpg',
                'classification' => 'Fondant',
                'flavor'         => 'Strawberry',
            ],
            [
                'name'           => 'Mr & Mrs Wedding Cake',
                'description'    => 'Stunning two-tier wedding cake with teal fondant base, gold leaf accents, white roses, and a Mr & Mrs acrylic topper. Perfect for weddings and anniversaries.',
                'price'          => 3500.0,
                'image_path'     => '/storage/uploads/products/20260414105849_3b2a26e47e61.jpg',
                'classification' => 'Fondant',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Classic White Cream Cake',
                'description'    => 'Elegant all-white buttercream cake with rosette piping border, gold pearl accents, and chocolate drip. Clean and sophisticated for any occasion.',
                'price'          => 900.0,
                'image_path'     => '/storage/uploads/products/20260414105850_7c64dcbf26e7.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Mocha Chocolate Drip Cake',
                'description'    => 'Rich mocha buttercream cake with chocolate drip sides and festive rainbow sprinkles. Deeply flavored with coffee and chocolate.',
                'price'          => 950.0,
                'image_path'     => '/storage/uploads/products/20260414105850_fe5abffab5b9.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Mocha',
            ],
            [
                'name'           => 'Blue Velvet Rosette Cake',
                'description'    => 'Sky-blue buttercream cake with rosette piping and gold pearl accents. Chocolate drip sides add a luxurious touch. Great for baby showers and birthdays.',
                'price'          => 950.0,
                'image_path'     => '/storage/uploads/products/20260414105849_241d8c0c788e.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Butterfly Cupcake Set',
                'description'    => 'Chocolate cupcakes with lavender-purple buttercream swirls and butterfly fondant toppers. Perfect for birthdays and garden parties. Sold per dozen.',
                'price'          => 480.0,
                'image_path'     => '/storage/uploads/products/20260414105850_e957b92c6171.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Chocolate',
            ],
            [
                'name'           => 'Mango Cream Cake',
                'description'    => 'Yellow buttercream cake with sweet mango jam center, piped rosette border, and chocolate drip. A Filipino tropical favorite.',
                'price'          => 950.0,
                'image_path'     => '/storage/uploads/products/20260414105850_5d2503854f31.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Mango',
            ],
            [
                'name'           => 'Blueberry Dream Cake',
                'description'    => 'Teal-blue buttercream cake with piped shell border and rich blueberry compote center. Bursting with real blueberry flavor.',
                'price'          => 900.0,
                'image_path'     => '/storage/uploads/products/20260414105850_6ab2b660fa73.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Blueberry',
            ],
            [
                'name'           => 'Blush White Drip Cake',
                'description'    => 'Romantic blush-white buttercream cake with rosette border, gold pearl sprinkles, and chocolate drip. Ideal for weddings, anniversaries, and special occasions.',
                'price'          => 950.0,
                'image_path'     => '/storage/uploads/products/20260414105851_d78affe7275f.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Mocha Rosette Drip Cake',
                'description'    => 'Mocha buttercream cake with full rosette piping on top, festive sprinkles, and generous chocolate drip. Bold coffee-chocolate flavor.',
                'price'          => 1000.0,
                'image_path'     => '/storage/uploads/products/20260414105851_0373ae24bcfd.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Mocha',
            ],
            [
                'name'           => 'Mini Duo Drip Cake',
                'description'    => 'Adorable mini cakes with smooth buttercream finish, chocolate drip, and piped rosette crown. Available in Lemon and Vanilla. Perfect for small celebrations.',
                'price'          => 650.0,
                'image_path'     => '/storage/uploads/products/20260414105849_c7cfa486f040.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Lemon / Vanilla',
            ],
            [
                'name'           => 'Blue Berry Swirl Cake',
                'description'    => 'Light blue buttercream cake with swirled rosette piping and dark blueberry compote topping. Colorful rainbow sprinkles add a festive touch.',
                'price'          => 900.0,
                'image_path'     => '/storage/uploads/products/20260414105850_cb22e90d4cac.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Blueberry',
            ],
            [
                'name'           => 'Pure White Drip Cake',
                'description'    => 'Pristine white buttercream cake with soft rosette border, gold pearl accents, and chocolate drip. Simple and elegant for any celebration.',
                'price'          => 900.0,
                'image_path'     => '/storage/uploads/products/20260414105851_d7697c6396bf.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Mocha Truffle Cake',
                'description'    => 'Smooth mocha cream cake with Maltesers, chocolate wafer sticks, and Hershey kisses. Chocolate drip border adds indulgence to this irresistible creation.',
                'price'          => 1050.0,
                'image_path'     => '/storage/uploads/products/20260414105851_655e51a9ce8f.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Mocha',
            ],
            [
                'name'           => 'Mango Caramel Cake',
                'description'    => 'Smooth yellow buttercream cake topped with glossy mango-caramel sauce, shell-piped border, and elegant chocolate drip. Rich tropical sweetness.',
                'price'          => 950.0,
                'image_path'     => '/storage/uploads/products/20260414105850_aa45efb1a62a.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Mango',
            ],
            [
                'name'           => 'Caramel Oreo Drip Cake',
                'description'    => 'Yellow buttercream cake with caramel glaze, whole Oreo cookies, and crunchy walnut bits. Rich chocolate drip makes this a showstopper.',
                'price'          => 1100.0,
                'image_path'     => '/storage/uploads/products/20260414105849_21aa4a9976dd.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Caramel',
            ],
            [
                'name'           => 'Dark Chocolate Matcha Cake',
                'description'    => 'Dark chocolate glazed cake with striking matcha green drizzle. Available as layered naked cake or fully glazed. Bold flavors for the adventurous cake lover.',
                'price'          => 1100.0,
                'image_path'     => '/storage/uploads/products/20260414105850_52e136ac2d1b.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Dark Chocolate',
            ],
            [
                'name'           => 'Malteser Cream Cake',
                'description'    => 'White buttercream cake with Maltesers chocolate balls, swirled rosette piping, and elegant chocolate drip lines. Sweet and crunchy.',
                'price'          => 1000.0,
                'image_path'     => '/storage/uploads/products/20260414105850_19cb9f0cdca7.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Chocolate',
            ],
            [
                'name'           => 'Mango Delight Cake',
                'description'    => 'Bright yellow buttercream cake with piped rosette border, sweet mango jam center, and chocolate drip. Captures the irresistible flavor of ripe Philippine mangoes.',
                'price'          => 950.0,
                'image_path'     => '/storage/uploads/products/20260414105851_2b58a6ae3ab7.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Mango',
            ],
            [
                'name'           => 'Cookies and Cream Cake',
                'description'    => 'White cake coated in Oreo crumble base, topped with cookies and cream buttercream rosettes and white chocolate chunks. A cookies and cream lover\'s dream.',
                'price'          => 1050.0,
                'image_path'     => '/storage/uploads/products/20260414105850_7f5bb7f3559c.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Cookies & Cream',
            ],
            [
                'name'           => 'Oreo Cream Mini Cake',
                'description'    => 'Compact cookies and cream cake with Oreo crumble base, cream cheese-style buttercream, white chocolate shard toppers, and dark Oreo crumble center.',
                'price'          => 850.0,
                'image_path'     => '/storage/uploads/products/20260414105851_714c862fcc4e.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Cookies & Cream',
            ],
            [
                'name'           => 'Cheesy Heaven Cake',
                'description'    => 'Rich cake generously topped with freshly grated cheese — sweet, salty, and absolutely irresistible. A Filipino classic that everyone loves.',
                'price'          => 800.0,
                'image_path'     => '/storage/uploads/products/20260414105849_5afbd9b88327.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Cheese',
            ],
            [
                'name'           => 'Double Tier Chocolate Cake',
                'description'    => 'Impressive two-tier all-chocolate buttercream cake with textured piping on the bottom tier and elegant swirl piping on top. A grand centerpiece for milestone celebrations.',
                'price'          => 2800.0,
                'image_path'     => '/storage/uploads/products/20260414105851_1af75b5458ff.jpg',
                'classification' => 'Standard',
                'flavor'         => 'Chocolate',
            ],
            [
                'name'           => 'Naruto Theme Cake',
                'description'    => 'Naruto Uzumaki themed birthday cake on a golden yellow fondant base with flame toppers and number topper. Perfect for anime fans.',
                'price'          => 1800.0,
                'image_path'     => '/storage/uploads/products/20260414105850_63e628d0dc7b.jpg',
                'classification' => 'Fondant',
                'flavor'         => 'Vanilla',
            ],
        ];

        $toInsert = array_filter($products, fn($p) => !in_array(strtolower($p['name']), $existingNames));

        if (empty($toInsert)) {
            $this->command->info('ℹ️  All products already exist for this shop — nothing to add.');
            return;
        }

        $rows = array_map(fn($p) => array_merge($p, [
            'id'           => Str::random(10),
            'shop_id'      => $shopId,
            'is_available' => 1,
            'created_at'   => $now,
        ]), array_values($toInsert));

        DB::table('products')->insert($rows);

        $this->command->info('✅ ' . count($rows) . ' products added for Bec\'s Cakeshop!');
    }
}
