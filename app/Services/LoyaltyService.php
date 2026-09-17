<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoyaltyService
{
    public function account(?string $userId): ?object
    {
        if (!$userId || !Schema::hasTable('loyalty_accounts')) return null;

        $account = DB::table('loyalty_accounts')->where('user_id', $userId)->first();
        if ($account) return $account;

        DB::table('loyalty_accounts')->insert([
            'user_id' => $userId,
            'points_balance' => 0,
            'lifetime_points' => 0,
            'lifetime_spend' => 0,
            'tier' => 'Bronze',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('loyalty_accounts')->where('user_id', $userId)->first();
    }

    public function awardForCompletedOrder(object|string $order): void
    {
        if (is_string($order)) {
            $order = DB::table('orders')->where('id', $order)->first();
        }
        if (!$order || empty($order->user_id) || !Schema::hasTable('loyalty_transactions')) return;
        if (!in_array(($order->status ?? ''), ['Delivered', 'Picked Up'], true)) return;
        if (($order->payment_status ?? '') !== 'Paid') return;

        $exists = DB::table('loyalty_transactions')
            ->where('order_id', $order->id)
            ->where('type', 'earn')
            ->exists();
        if ($exists) return;

        $account = $this->account($order->user_id);
        if (!$account) return;

        $tier = DB::table('loyalty_tiers')
            ->where('name', $account->tier ?? 'Bronze')
            ->where('is_active', true)
            ->first();

        $net = max(0, (float) ($order->total_price ?? 0) - (float) ($order->delivery_fee ?? 0) - (float) ($order->service_charge ?? 0));
        $basePoints = (int) floor($net / 50);
        $multiplier = (float) ($tier->points_multiplier ?? 1);
        $points = max(0, (int) floor($basePoints * $multiplier));
        if ($points <= 0) return;

        $balance = (int) $account->points_balance + $points;
        $lifetime = (int) $account->lifetime_points + $points;
        $newTier = $this->tierForPoints($lifetime);

        DB::table('loyalty_transactions')->insert([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'type' => 'earn',
            'points' => $points,
            'balance_after' => $balance,
            'description' => "Earned {$points} points from Order #{$order->id}.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('loyalty_accounts')->where('user_id', $order->user_id)->update([
            'points_balance' => $balance,
            'lifetime_points' => $lifetime,
            'lifetime_spend' => round((float) $account->lifetime_spend + $net, 2),
            'tier' => $newTier,
            'tier_updated_at' => $newTier !== ($account->tier ?? 'Bronze') ? now() : $account->tier_updated_at,
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('orders', 'points_earned')) {
            DB::table('orders')->where('id', $order->id)->update(['points_earned' => $points]);
        }
    }

    public function tierForPoints(int $lifetimePoints): string
    {
        $tier = DB::table('loyalty_tiers')
            ->where('is_active', true)
            ->where('min_lifetime_points', '<=', $lifetimePoints)
            ->orderByDesc('min_lifetime_points')
            ->first();

        return $tier->name ?? 'Bronze';
    }
}
