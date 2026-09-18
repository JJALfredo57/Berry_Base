<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoyaltyService
{
    private function defaultTiers()
    {
        return collect([
            (object) [
                'name' => 'Bronze',
                'min_lifetime_points' => 0,
                'points_multiplier' => 1,
                'perk_summary' => 'Earn rewards on completed orders.',
                'is_active' => true,
            ],
            (object) [
                'name' => 'Silver',
                'min_lifetime_points' => 250,
                'points_multiplier' => 1.25,
                'perk_summary' => 'Earn more points and unlock verified-only promos.',
                'is_active' => true,
            ],
            (object) [
                'name' => 'Gold',
                'min_lifetime_points' => 750,
                'points_multiplier' => 1.5,
                'perk_summary' => 'Highest rewards rate and priority trust signals.',
                'is_active' => true,
            ],
        ]);
    }

    public function tiers()
    {
        if (!Schema::hasTable('loyalty_tiers')) {
            return $this->defaultTiers();
        }

        $tiers = DB::table('loyalty_tiers')
            ->where('is_active', true)
            ->orderBy('min_lifetime_points')
            ->get();

        return $tiers->isNotEmpty() ? $tiers : $this->defaultTiers();
    }

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

    public function membershipOverview(?string $userId): array
    {
        $account = $this->account($userId);
        $tiers = $this->tiers()->values();
        $lifetime = (int) ($account->lifetime_points ?? 0);
        $balance = (int) ($account->points_balance ?? 0);
        $computedCurrent = $tiers
            ->filter(fn ($tier) => (int) $tier->min_lifetime_points <= $lifetime)
            ->last();
        $currentTier = (string) ($computedCurrent->name ?? $account->tier ?? 'Bronze');
        $nextTier = $tiers->first(fn ($tier) => (int) $tier->min_lifetime_points > $lifetime);
        $currentMin = (int) ($computedCurrent->min_lifetime_points ?? 0);
        $nextMin = $nextTier ? (int) $nextTier->min_lifetime_points : null;
        $progress = $nextMin
            ? min(100, max(0, (int) floor((($lifetime - $currentMin) / max(1, $nextMin - $currentMin)) * 100)))
            : 100;

        $tierCards = $tiers->map(function ($tier) use ($lifetime, $currentTier) {
            $name = (string) $tier->name;
            return [
                'name' => $name,
                'min_lifetime_points' => (int) $tier->min_lifetime_points,
                'points_multiplier' => (float) $tier->points_multiplier,
                'perk_summary' => (string) ($tier->perk_summary ?? ''),
                'benefits' => $this->tierBenefits($name, (string) ($tier->perk_summary ?? '')),
                'is_current' => strcasecmp($name, $currentTier) === 0,
                'is_unlocked' => $lifetime >= (int) $tier->min_lifetime_points,
            ];
        })->all();

        return [
            'account' => $account,
            'balance' => $balance,
            'lifetime_points' => $lifetime,
            'current_tier' => $currentTier,
            'next_tier' => $nextTier,
            'points_to_next' => $nextMin ? max(0, $nextMin - $lifetime) : 0,
            'progress' => $progress,
            'tiers' => $tierCards,
        ];
    }

    private function tierBenefits(string $tierName, string $summary): array
    {
        $benefits = [
            $summary ?: 'Earn rewards on completed paid orders.',
        ];

        $tier = strtolower($tierName);
        if ($tier === 'bronze') {
            $benefits[] = 'Start earning points after paid completed orders.';
            $benefits[] = 'Track your available points in your customer profile.';
        } elseif ($tier === 'silver') {
            $benefits[] = 'Earn 25% more points than Bronze.';
            $benefits[] = 'Better access to verified-only promos once your ID is approved.';
        } elseif ($tier === 'gold') {
            $benefits[] = 'Earn 50% more points than Bronze.';
            $benefits[] = 'Priority trust signal for larger COD/COP orders.';
        } else {
            $benefits[] = 'Higher loyalty tier benefits as configured by BerryBase.';
            $benefits[] = 'More reasons to keep ordering with your customer account.';
        }

        return array_values(array_unique(array_filter($benefits)));
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
        if (!Schema::hasTable('loyalty_tiers')) {
            return $this->tiers()
                ->filter(fn ($tier) => (int) $tier->min_lifetime_points <= $lifetimePoints)
                ->last()
                ->name ?? 'Bronze';
        }

        $tier = DB::table('loyalty_tiers')
            ->where('is_active', true)
            ->where('min_lifetime_points', '<=', $lifetimePoints)
            ->orderByDesc('min_lifetime_points')
            ->first();

        return $tier->name ?? 'Bronze';
    }
}
