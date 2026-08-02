<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DailyCapacityService
{
    public function snapshot(?string $shopId, ?string $date): array
    {
        $date = trim((string) $date);
        if ($date === '') {
            return [
                'configured' => false,
                'status' => 'invalid',
                'message' => 'Please select your preferred date.',
            ];
        }

        $settings = $this->settingsForShop($shopId);
        $dailyMax = (int) ($settings->daily_max_cakes ?? 0);
        if (!$settings || $dailyMax <= 0) {
            return [
                'configured' => false,
                'status' => 'capacity_not_configured',
                'max' => 0,
                'ordered' => 0,
                'remaining' => 0,
                'message' => 'This shop has not set its daily capacity yet. Please contact the seller before placing an order.',
            ];
        }

        $today = date('Y-m-d');
        $leadDays = (int) floor((strtotime($date) - strtotime($today)) / 86400);
        $effectiveMax = $dailyMax;
        if ($leadDays === 1 && (int) ($settings->lead_1day_max ?? 0) > 0) {
            $effectiveMax = (int) $settings->lead_1day_max;
        } elseif ($leadDays === 2 && (int) ($settings->lead_2day_max ?? 0) > 0) {
            $effectiveMax = (int) $settings->lead_2day_max;
        } elseif ($leadDays >= 3 && (int) ($settings->lead_3day_plus_max ?? 0) > 0) {
            $effectiveMax = (int) $settings->lead_3day_plus_max;
        }

        $ordered = $this->reservedQuantity($shopId, $date);
        $remaining = max(0, $effectiveMax - $ordered);
        $pct = $effectiveMax > 0 ? $ordered / $effectiveMax : 1;

        if ($remaining === 0) {
            $status = 'full';
            $message = "Fully booked on this date ({$ordered}/{$effectiveMax} pcs).";
        } elseif ($pct >= 0.8) {
            $status = 'almost';
            $message = "Almost full - only {$remaining} of {$effectiveMax} pcs left.";
        } else {
            $status = 'available';
            $message = "{$remaining} of {$effectiveMax} pcs available.";
        }

        return [
            'configured' => true,
            'status' => $status,
            'max' => $effectiveMax,
            'ordered' => $ordered,
            'remaining' => $remaining,
            'lead_days' => $leadDays,
            'message' => $message,
        ];
    }

    public function validate(?string $shopId, ?string $date, int $quantity): array
    {
        $quantity = max(1, $quantity);
        $snapshot = $this->snapshot($shopId, $date);

        if (($snapshot['status'] ?? '') === 'invalid') {
            return ['allowed' => false] + $snapshot;
        }

        if (!($snapshot['configured'] ?? false)) {
            return ['allowed' => false] + $snapshot;
        }

        $remaining = (int) ($snapshot['remaining'] ?? 0);
        if ($quantity > $remaining) {
            $dateText = (string) $date;
            $max = (int) ($snapshot['max'] ?? 0);
            $message = $remaining === 0
                ? "Sorry, {$dateText} is fully booked ({$max} pcs max). Please choose another date."
                : "Only {$remaining} pcs available on {$dateText}. Please reduce your quantity or choose another date.";
            return ['allowed' => false, 'message' => $message] + $snapshot;
        }

        return ['allowed' => true] + $snapshot;
    }

    private function settingsForShop(?string $shopId): ?object
    {
        $settings = $shopId
            ? DB::table('site_settings')->where('shop_id', $shopId)->first()
            : DB::table('site_settings')->whereNull('shop_id')->first();

        if (!$settings && !$shopId) {
            $settings = DB::table('site_settings')->first();
        }

        return $settings;
    }

    private function reservedQuantity(?string $shopId, string $date): int
    {
        $query = DB::table('orders')
            ->where('schedule_date', $date)
            ->whereNotIn('status', ['Cancelled']);

        if ($shopId) {
            $query->where('shop_id', $shopId);
        } else {
            $query->whereNull('shop_id');
        }

        return (int) $query->sum('quantity');
    }
}
