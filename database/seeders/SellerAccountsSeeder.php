<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Helpers\CakeshopHelper;

class SellerAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = [
            [
                'fullname'    => 'Jamie Cruz Iglesias',
                'email'       => 'adrianmontemayor469@gmail.com',
                'phone'       => '+639158682349',
                'username'    => 'baked-by-jamie',
                'password'    => 'Jamie@2026',
                'shop_name'   => 'Baked By Jamie',
                'shop_slug'   => 'baked-by-jamie',
                'address'     => 'Cabuaan, Bautista, Pangsinan',
                'city'        => 'Bautista Pangsinan',
                'gcash'       => '+639158682349',
                'description' => 'Baked by Jamien — Made from scratch, made with love.',
            ],
            [
                'fullname'    => 'Danielle Anne Casabar',
                'email'       => 'jjdatuin0827@gmail.com',
                'phone'       => '+639852442394',
                'username'    => 'danielles-cake-shop',
                'password'    => 'Danielle@2026',
                'shop_name'   => "Danielle's Cake Shop",
                'shop_slug'   => 'danielles-cake-shop',
                'address'     => 'Palisoc, Bautista, Pangsinan',
                'city'        => 'Bautista Pangsinan',
                'gcash'       => '+639852442394',
                'description' => "Baked fresh, served with love — Danielle's",
            ],
        ];

        foreach ($sellers as $s) {
            // Delete existing user + shop before re-inserting
            $existing = DB::table('users')->where('email', $s['email'])->first();
            if ($existing) {
                DB::table('shops')->where('seller_id', $existing->id)->delete();
                DB::table('users')->where('id', $existing->id)->delete();
                $this->command->warn("Removed old account: {$s['email']}");
            }

            $uid = CakeshopHelper::generateId('users');

            DB::table('users')->insert([
                'id'          => $uid,
                'fullname'    => $s['fullname'],
                'email'       => $s['email'],
                'phone'       => $s['phone'],
                'username'    => $s['username'],
                'password'    => Hash::make($s['password']),
                'role'        => 'seller',
                'is_verified' => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $slug = DB::table('shops')->where('shop_slug', $s['shop_slug'])->exists()
                ? $s['shop_slug'] . '-' . substr($uid, 0, 4)
                : $s['shop_slug'];

            DB::table('shops')->insert([
                'id'                 => CakeshopHelper::generateId('shops'),
                'seller_id'          => $uid,
                'shop_name'          => $s['shop_name'],
                'shop_slug'          => $slug,
                'description'        => $s['description'],
                'address'            => $s['address'],
                'city'               => $s['city'],
                'contact_number'     => $s['phone'],
                'email'              => $s['email'],
                'gcash_number'       => $s['gcash'],
                'theme_color'        => '#ec4899',
                'status'             => 'approved',
                'tier'               => 'basic',
                'is_verified'        => false,
                'commission_rate'    => 3.00,
                'commission_enabled' => false,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $this->command->info("Created: {$s['fullname']}");
            $this->command->line("  Username : {$s['username']}");
            $this->command->line("  Password : {$s['password']}");
            $this->command->line("  Email    : {$s['email']}");
        }
    }
}
