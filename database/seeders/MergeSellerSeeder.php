<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MergeSellerSeeder extends Seeder
{
    /**
     * Transfer all products from bec.catillio@cakeshop.test to barrozos004@gmail.com,
     * then delete Bec Catillio's shop and user account.
     *
     * Run with: php artisan db:seed --class=MergeSellerSeeder
     */
    public function run(): void
    {
        // ── Source: Bec Catillio (test account) ──────────────────────────
        $source = DB::table('users')->where('email', 'bec.catillio@cakeshop.test')->first();
        if (!$source) {
            $this->command->warn('Source user bec.catillio@cakeshop.test not found — nothing to do.');
            return;
        }

        $sourceShop = DB::table('shops')->where('seller_id', $source->id)->first();
        if (!$sourceShop) {
            $this->command->warn('No shop found for source user — nothing to transfer.');
            DB::table('users')->where('id', $source->id)->delete();
            $this->command->info('✅ Source user deleted (no shop).');
            return;
        }

        // ── Target: Bec Castillo (real account) ──────────────────────────
        $target = DB::table('users')->where('email', 'barrozos004@gmail.com')->first();
        if (!$target) {
            $this->command->error('Target user barrozos004@gmail.com not found. Aborting.');
            return;
        }

        $targetShop = DB::table('shops')->where('seller_id', $target->id)->first();
        if (!$targetShop) {
            $this->command->error('No shop found for target user barrozos004@gmail.com. Aborting.');
            return;
        }

        $this->command->info("Source shop : {$sourceShop->shop_name} (id: {$sourceShop->id})");
        $this->command->info("Target shop : {$targetShop->shop_name} (id: {$targetShop->id})");

        // ── Transfer products ─────────────────────────────────────────────
        $moved = DB::table('products')
            ->where('shop_id', $sourceShop->id)
            ->update(['shop_id' => $targetShop->id]);
        $this->command->info("✅ Products transferred: {$moved}");

        // ── Transfer orders ───────────────────────────────────────────────
        $orders = DB::table('orders')
            ->where('shop_id', $sourceShop->id)
            ->update(['shop_id' => $targetShop->id]);
        $this->command->info("✅ Orders transferred: {$orders}");

        // ── Transfer custom_orders ────────────────────────────────────────
        $customOrders = DB::table('custom_orders')
            ->where('shop_id', $sourceShop->id)
            ->update(['shop_id' => $targetShop->id]);
        $this->command->info("✅ Custom orders transferred: {$customOrders}");

        // ── Transfer messages ─────────────────────────────────────────────
        $messages = DB::table('messages')
            ->where('shop_id', $sourceShop->id)
            ->update(['shop_id' => $targetShop->id]);
        $this->command->info("✅ Messages transferred: {$messages}");

        // ── Transfer order_reviews ────────────────────────────────────────
        $reviews = DB::table('order_reviews')
            ->where('shop_id', $sourceShop->id)
            ->update(['shop_id' => $targetShop->id]);
        $this->command->info("✅ Reviews transferred: {$reviews}");

        // ── Delete seller_documents tied to source shop ───────────────────
        DB::table('seller_documents')->where('shop_id', $sourceShop->id)->delete();
        $this->command->info("✅ Seller documents deleted.");

        // ── Delete source shop ────────────────────────────────────────────
        DB::table('shops')->where('id', $sourceShop->id)->delete();
        $this->command->info("✅ Source shop deleted: {$sourceShop->shop_name}");

        // ── Delete source user ────────────────────────────────────────────
        DB::table('users')->where('id', $source->id)->delete();
        $this->command->info("✅ Source user deleted: bec.catillio@cakeshop.test");

        $this->command->info('');
        $this->command->info('Done! All data now belongs to barrozos004@gmail.com.');
    }
}
