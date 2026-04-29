<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\CakeshopHelper;

class DanielProductSeeder extends Seeder
{
    public function run(): void
    {
        // Find seller named Daniel (case-insensitive, partial match)
        $user = DB::table('users')
            ->whereRaw("LOWER(fullname) LIKE ?", ['%daniel%'])
            ->where('role', 'seller')
            ->first();

        if (!$user) {
            $this->command->error('No seller account found with "daniel" in the name.');
            return;
        }

        $shop = DB::table('shops')->where('seller_id', $user->id)->first();

        if (!$shop) {
            $this->command->error("Seller \"{$user->fullname}\" has no shop registered.");
            return;
        }

        $this->command->info("Inserting products for: {$user->fullname} → Shop: {$shop->name}");

        $products = [
            [
                'name'           => 'Floral Birthday Cake',
                'description'    => 'White cream cake with lush green botanical leaf design, gold pearl accents, and purple floral decorations along the sides. Personalized message piped on top. Perfect for birthdays and celebrations.',
                'price'          => 899,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Hearts Anniversary Cake',
                'description'    => 'Romantic white cream cake with elegant swirl top design and scattered red heart accents. Simple, elegant, and heartfelt — ideal for anniversaries and date celebrations.',
                'price'          => 300,
                'classification' => 'Anniversary Cake',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Purple Butterfly Jelly Cake',
                'description'    => 'Clear jelly-style cake adorned with watercolor purple butterflies and a mix of lavender and gold ball decorations. A stunning and unique centerpiece for any celebration.',
                'price'          => 899,
                'classification' => 'Jelly Cake',
                'flavor'         => 'Lychee',
            ],
            [
                'name'           => 'Ocean Blue Bubble Birthday Cake',
                'description'    => 'Blue ombre textured cake with white and sky-blue bubble clusters on the sides. Finished with a round "Happy Birthday" acrylic topper and gold/silver sprinkle accents.',
                'price'          => 999,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Blueberry',
            ],
            [
                'name'           => 'OA Day Celebration Cake',
                'description'    => 'Rustic salmon-toned cake with hand-piped custom text. Fun and casual design perfect for any milestone, "extra" moment, or special personal celebration.',
                'price'          => 699,
                'classification' => 'Special Occasion Cake',
                'flavor'         => 'Strawberry',
            ],
            [
                'name'           => 'Pink Rose Butterfly Cake',
                'description'    => 'Elegant white cream cake featuring a full pink rosette border on top and sides, decorated with purple butterfly toppers and silver pearl accents throughout.',
                'price'          => 799,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Vanilla',
            ],
            [
                'name'           => 'Navy Butterfly Round Cake',
                'description'    => 'Dark navy round cake with golden watercolor butterfly decorations and delicate pearl accents. A sophisticated and eye-catching design for birthdays and special events.',
                'price'          => 899,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Dark Chocolate',
            ],
            [
                'name'           => 'Spider-Man Birthday Cake',
                'description'    => 'Fan-favorite themed cake featuring a Spider-Man face centerpiece, hand-piped web design in chocolate, and character figurine at the base. Personalized name available.',
                'price'          => 999,
                'classification' => 'Themed Cake',
                'flavor'         => 'Chocolate',
            ],
            [
                'name'           => 'Purple Rose Wreath Cake',
                'description'    => 'White cream cake decorated with a full wreath of lavender rosettes and navy blue floral star accents, finished with silver pearl details for a timeless and elegant look.',
                'price'          => 799,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Ube',
            ],
            [
                'name'           => 'Navy Blue Rose Crescent Cake',
                'description'    => 'Striking white cake featuring a cascading crescent arrangement of deep navy roses and light blue floral accents, with scattered pearl decorations for a luxurious finish.',
                'price'          => 799,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Blueberry',
            ],
            [
                'name'           => 'Chocolate Gold Drip Birthday Cake',
                'description'    => 'Rich chocolate cream base with caramel flower piping, a mix of gold and silver ball decorations, and a "Happy Birthday" plaque topper. A decadent choice for chocolate lovers.',
                'price'          => 699,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Chocolate',
            ],
            [
                'name'           => 'Garden Rose Birthday Cake',
                'description'    => 'Vibrant blue-base cake crowned with full blooms of pink roses and lush green leaf accents. Decorated with silver pearls throughout and a golden floral birthday topper.',
                'price'          => 699,
                'classification' => 'Birthday Cake',
                'flavor'         => 'Strawberry',
            ],
        ];

        $inserted = 0;
        $skipped  = 0;

        foreach ($products as $data) {
            $exists = DB::table('products')
                ->where('shop_id', $shop->id)
                ->whereRaw("LOWER(name) = ?", [strtolower($data['name'])])
                ->exists();

            if ($exists) {
                $this->command->warn("  Skipped (already exists): {$data['name']}");
                $skipped++;
                continue;
            }

            DB::table('products')->insert([
                'id'             => CakeshopHelper::generateId('products'),
                'shop_id'        => $shop->id,
                'name'           => $data['name'],
                'description'    => $data['description'],
                'price'          => $data['price'],
                'image_path'     => null,
                'classification' => $data['classification'],
                'flavor'         => $data['flavor'],
                'is_available'   => true,
                'sort_order'     => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $this->command->line("  + Inserted: {$data['name']} — ₱{$data['price']}");
            $inserted++;
        }

        $this->command->info("Done. Inserted: {$inserted} | Skipped: {$skipped}");
        $this->command->warn('Note: Product images were not set. Upload them via the Seller → Products dashboard.');
    }
}
