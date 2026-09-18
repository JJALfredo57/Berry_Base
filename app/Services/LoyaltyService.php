<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoyaltyService
{
    private const DEFAULT_SETTINGS = [
        'earn_enabled' => true,
        'redeem_enabled' => true,
        'points_base_amount' => 50.0,
        'point_value' => 1.0,
        'max_redemption_percent' => 50.0,
        'redemption_requires_verified' => true,
    ];

    public function settings(): array
    {
        $settings = self::DEFAULT_SETTINGS;
        if (!Schema::hasTable('platform_settings')) {
            return $settings;
        }

        $platform = DB::table('platform_settings')->first();
        if (!$platform) {
            return $settings;
        }

        if (Schema::hasColumn('platform_settings', 'loyalty_earn_enabled')) {
            $settings['earn_enabled'] = (bool) $platform->loyalty_earn_enabled;
        }
        if (Schema::hasColumn('platform_settings', 'loyalty_redeem_enabled')) {
            $settings['redeem_enabled'] = (bool) $platform->loyalty_redeem_enabled;
        }
        if (Schema::hasColumn('platform_settings', 'loyalty_points_base_amount')) {
            $settings['points_base_amount'] = max(1, (float) $platform->loyalty_points_base_amount);
        }
        if (Schema::hasColumn('platform_settings', 'loyalty_point_value')) {
            $settings['point_value'] = max(0.01, (float) $platform->loyalty_point_value);
        }
        if (Schema::hasColumn('platform_settings', 'loyalty_max_redemption_percent')) {
            $settings['max_redemption_percent'] = max(0, min(100, (float) $platform->loyalty_max_redemption_percent));
        }
        if (Schema::hasColumn('platform_settings', 'loyalty_redemption_requires_verified')) {
            $settings['redemption_requires_verified'] = (bool) $platform->loyalty_redemption_requires_verified;
        }

        return $settings;
    }

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
        $settings = $this->settings();
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
                'benefits' => $this->tierBenefits($name, (string) ($tier->perk_summary ?? ''), (float) ($tier->points_multiplier ?? 1)),
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
            'settings' => $settings,
        ];
    }

    private function tierBenefits(string $tierName, string $summary, float $multiplier = 1): array
    {
        $benefits = [
            $summary ?: 'Earn rewards on completed paid orders.',
        ];

        $tier = strtolower($tierName);
        $bonusPercent = max(0, (int) round(($multiplier - 1) * 100));
        if ($bonusPercent > 0) {
            $benefits[] = "Earn {$bonusPercent}% more points than the base rate.";
        } else {
            $benefits[] = 'Earn points at the base rewards rate.';
        }

        if ($tier === 'bronze') {
            $benefits[] = 'Start earning points after paid completed orders.';
        } elseif ($tier === 'silver') {
            $benefits[] = 'Better access to verified-only promos once your ID is approved.';
        } elseif ($tier === 'gold') {
            $benefits[] = 'Priority trust signal for larger COD/COP orders.';
        } else {
            $benefits[] = 'Tier benefits follow the current BerryBase rewards settings.';
        }

        return array_values(array_unique(array_filter($benefits)));
    }

    public function redemptionQuote(?string $userId, float $eligibleSubtotal, int $requestedPoints, bool $isVerified): array
    {
        $settings = $this->settings();
        $account = $this->account($userId);
        $balance = (int) ($account->points_balance ?? 0);
        $eligibleSubtotal = max(0, $eligibleSubtotal);
        $pointValue = (float) $settings['point_value'];
        $maxDiscountBySubtotal = $eligibleSubtotal * ((float) $settings['max_redemption_percent'] / 100);
        $maxBySubtotal = (int) floor($maxDiscountBySubtotal / max(0.01, $pointValue));
        $maxRedeemable = max(0, min($balance, $maxBySubtotal));
        $baseResponse = [
            'balance' => $balance,
            'max' => $maxRedeemable,
            'point_value' => $pointValue,
            'max_redemption_percent' => (float) $settings['max_redemption_percent'],
            'requires_verified' => (bool) $settings['redemption_requires_verified'],
        ];

        if ($requestedPoints <= 0) {
            return $baseResponse + ['ok' => true, 'points' => 0, 'discount' => 0.0, 'message' => ''];
        }

        if (!$settings['redeem_enabled']) {
            return $baseResponse + ['ok' => false, 'points' => 0, 'discount' => 0.0, 'message' => 'Rewards redemption is currently paused.'];
        }

        if ($settings['redemption_requires_verified'] && !$isVerified) {
            return $baseResponse + ['ok' => false, 'points' => 0, 'discount' => 0.0, 'message' => 'Verify your account before redeeming points.'];
        }

        if ($requestedPoints > $balance) {
            return $baseResponse + ['ok' => false, 'points' => 0, 'discount' => 0.0, 'message' => 'You do not have enough points for that redemption.'];
        }

        if ($requestedPoints > $maxRedeemable) {
            return $baseResponse + ['ok' => false, 'points' => 0, 'discount' => 0.0, 'message' => 'Points can cover up to ' . rtrim(rtrim(number_format((float) $settings['max_redemption_percent'], 2), '0'), '.') . '% of the product subtotal after vouchers.'];
        }

        return $baseResponse + ['ok' => true, 'points' => $requestedPoints, 'discount' => round($requestedPoints * $pointValue, 2), 'message' => 'Points applied.'];
    }

    public function earningEstimate(?string $userId, float $eligibleSubtotal): array
    {
        $settings = $this->settings();
        $account = $this->account($userId);
        $tierName = (string) ($account->tier ?? $this->tierForPoints((int) ($account->lifetime_points ?? 0)));
        $tier = $this->tiers()->first(fn ($item) => strcasecmp((string) $item->name, $tierName) === 0);
        $multiplier = (float) ($tier->points_multiplier ?? 1);
        $basePoints = $settings['earn_enabled']
            ? (int) floor(max(0, $eligibleSubtotal) / max(1, (float) $settings['points_base_amount']))
            : 0;

        return [
            'enabled' => (bool) $settings['earn_enabled'],
            'eligible_subtotal' => max(0, $eligibleSubtotal),
            'base_points' => $basePoints,
            'multiplier' => $multiplier,
            'points' => max(0, (int) floor($basePoints * $multiplier)),
        ];
    }

    public function redeemForOrder(string $userId, string $orderId, int $points, float $discount): void
    {
        if ($points <= 0 || !Schema::hasTable('loyalty_accounts') || !Schema::hasTable('loyalty_transactions')) return;
        $settings = $this->settings();

        DB::transaction(function () use ($userId, $orderId, $points, $discount, $settings) {
            $account = DB::table('loyalty_accounts')->where('user_id', $userId)->lockForUpdate()->first();
            if (!$account || (int) $account->points_balance < $points) {
                throw new \RuntimeException('Points balance changed. Please try again.');
            }

            $balance = (int) $account->points_balance - $points;
            DB::table('loyalty_accounts')->where('user_id', $userId)->update([
                'points_balance' => $balance,
                'updated_at' => now(),
            ]);

            DB::table('loyalty_transactions')->insert([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => 'redeem',
                'points' => -$points,
                'balance_after' => $balance,
                'description' => "Redeemed {$points} points for Order #{$orderId}.",
                'meta' => json_encode(['discount_amount' => round($discount, 2), 'point_value' => (float) $settings['point_value']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function awardForCompletedOrder(object|string $order): void
    {
        if (is_string($order)) {
            $order = DB::table('orders')->where('id', $order)->first();
        }
        if (!$order || empty($order->user_id) || !Schema::hasTable('loyalty_transactions')) return;
        $settings = $this->settings();
        if (!$settings['earn_enabled']) return;
        if (!in_array(($order->status ?? ''), ['Delivered', 'Picked Up'], true)) return;
        if (($order->payment_status ?? '') !== 'Paid') return;

        $exists = DB::table('loyalty_transactions')
            ->where('order_id', $order->id)
            ->where('type', 'earn')
            ->exists();
        if ($exists) return;

        $account = $this->account($order->user_id);
        if (!$account) return;

        $tier = Schema::hasTable('loyalty_tiers')
            ? DB::table('loyalty_tiers')->where('name', $account->tier ?? 'Bronze')->where('is_active', true)->first()
            : $this->tiers()->first(fn ($item) => strcasecmp((string) $item->name, (string) ($account->tier ?? 'Bronze')) === 0);

        $net = max(0, (float) ($order->total_price ?? 0) - (float) ($order->delivery_fee ?? 0) - (float) ($order->service_charge ?? 0));
        $basePoints = (int) floor($net / max(1, (float) $settings['points_base_amount']));
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
