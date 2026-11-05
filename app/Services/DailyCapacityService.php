<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyCapacityService
{
    public function snapshot(?string $shopId, ?string $date): array
    {
        $this->releaseExpiredReservations();
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
        $effectiveMax = $this->effectiveMaxForDate($settings, $dailyMax, $leadDays);

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


    private function effectiveMaxForDate(object $settings, int $dailyMax, int $leadDays): int
    {
        $prepDays = (int) ($settings->custom_cake_prep_days ?? 3);
        $offset = $leadDays - max(0, $prepDays);
        if ($offset < 0) return $dailyMax;

        $schedule = [];
        if (Schema::hasColumn('site_settings', 'custom_capacity_schedule')) {
            $raw = $settings->custom_capacity_schedule ?? null;
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
            $schedule = is_array($decoded) ? $decoded : [];
        }

        if (array_key_exists((string) $offset, $schedule)) {
            $value = (int) $schedule[(string) $offset];
            return $value > 0 ? $value : 0;
        }

        return $dailyMax;
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

        $ordered = (int) $query->sum('quantity');
        $held = 0;
        if (Schema::hasTable('order_request_capacity_reservations')) {
            $held = (int) DB::table('order_request_capacity_reservations')
                ->where('reserved_date', $date)
                ->where('status', 'active')
                ->when($shopId, fn ($q) => $q->where('shop_id', $shopId), fn ($q) => $q->whereNull('shop_id'))
                ->sum('quantity');
        }
        return $ordered + $held;
    }

    public function reserveRequest(object $request, \DateTimeInterface $expiresAt): array
    {
        if (!Schema::hasTable('order_request_capacity_reservations')) {
            return ['ok' => false, 'message' => 'Kitchen capacity reservations are not available yet.'];
        }

        return DB::transaction(function () use ($request, $expiresAt) {
            DB::table('order_request_capacity_reservations')
                ->where('order_request_id', $request->id)
                ->where('status', 'active')
                ->update(['status' => 'released', 'updated_at' => now()]);

            $check = $this->validate($request->shop_id ?? null, $request->accepted_date ?? $request->preferred_date, max(1, (int) $request->quantity));
            if (!$check['allowed']) return ['ok' => false, 'message' => $check['message']];

            DB::table('order_request_capacity_reservations')->updateOrInsert(
                ['order_request_id' => $request->id],
                [
                    'shop_id' => $request->shop_id,
                    'reserved_date' => $request->accepted_date ?? $request->preferred_date,
                    'quantity' => max(1, (int) $request->quantity),
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            return ['ok' => true, 'message' => ($check['remaining'] - max(1, (int) $request->quantity)) . ' kitchen slot(s) remain after this hold.'];
        });
    }

    public function releaseRequest(string $requestId): void
    {
        if (!Schema::hasTable('order_request_capacity_reservations')) return;
        DB::table('order_request_capacity_reservations')->where('order_request_id', $requestId)->where('status', 'active')
            ->update(['status' => 'released', 'updated_at' => now()]);
    }

    public function consumeRequest(string $requestId): void
    {
        if (!Schema::hasTable('order_request_capacity_reservations')) return;
        DB::table('order_request_capacity_reservations')->where('order_request_id', $requestId)->where('status', 'active')
            ->update(['status' => 'consumed', 'updated_at' => now()]);
    }

    public function releaseExpiredReservations(): void
    {
        if (!Schema::hasTable('order_request_capacity_reservations')) return;
        DB::table('order_request_capacity_reservations')->where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->update(['status' => 'released', 'updated_at' => now()]);
    }
}
