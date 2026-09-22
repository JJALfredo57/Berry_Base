<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FulfillmentScheduleService
{
    public function settings(?string $shopId): object
    {
        $settings = $shopId
            ? DB::table('site_settings')->where('shop_id', $shopId)->first()
            : DB::table('site_settings')->whereNull('shop_id')->first();

        if (!$settings) {
            $settings = DB::table('site_settings')->first();
        }

        return (object) [
            'ready_made_prep_minutes' => $this->columnValue($settings, 'ready_made_prep_minutes', 90),
            'custom_cake_prep_minutes' => $this->columnValue($settings, 'custom_cake_prep_minutes', 0),
            'pickup_buffer_minutes' => $this->columnValue($settings, 'pickup_buffer_minutes', 0),
            'delivery_base_buffer_minutes' => $this->columnValue($settings, 'delivery_base_buffer_minutes', 30),
            'delivery_minutes_per_km' => $this->columnValue($settings, 'delivery_minutes_per_km', 5),
            'shop_lat' => $settings->shop_lat ?? null,
            'shop_lng' => $settings->shop_lng ?? null,
        ];
    }

    public function slots(?string $shopId, string $orderType = 'regular', ?string $fulfillment = null): Collection
    {
        $slots = collect();

        if (Schema::hasTable('fulfillment_time_slots')) {
            $query = DB::table('fulfillment_time_slots')
                ->where('shop_id', $shopId)
                ->where('is_active', true)
                ->whereIn('order_type', ['both', $orderType])
                ->orderBy('sort_order')
                ->orderBy('start_time');

            if ($fulfillment) {
                $query->whereIn('fulfillment_method', ['both', strtolower($fulfillment)]);
            }

            $slots = $query->get();
        }

        if ($slots->isEmpty()) {
            $slots = collect($this->defaultSlots());
        }

        return $slots->map(function ($slot) {
            $start = substr((string) $slot->start_time, 0, 5);
            $end = substr((string) $slot->end_time, 0, 5);
            $slot->start_time = $start;
            $slot->end_time = $end;
            $slot->label = trim((string) ($slot->label ?? '')) ?: $this->label($start, $end);
            return $slot;
        })->values();
    }

    public function slotsForCheckout(?string $shopId, string $orderType = 'regular'): array
    {
        return $this->slots($shopId, $orderType)->map(fn ($slot) => [
            'value' => $slot->start_time,
            'label' => $slot->label,
            'start' => $slot->start_time,
            'end' => $slot->end_time,
            'fulfillment' => $slot->fulfillment_method ?? 'both',
            'order_type' => $slot->order_type ?? 'both',
        ])->all();
    }

    public function validate(
        ?string $shopId,
        ?string $date,
        ?string $time,
        string $orderType = 'regular',
        string $fulfillment = 'Pickup',
        ?float $lat = null,
        ?float $lng = null
    ): array {
        if (!$date) {
            return ['ok' => false, 'message' => 'Please select your preferred date.'];
        }

        if (!$time) {
            return ['ok' => false, 'message' => 'Please select a preferred time slot.'];
        }

        try {
            $selectedDate = Carbon::parse($date, config('app.timezone'))->startOfDay();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Please select a valid preferred date.'];
        }

        $now = now(config('app.timezone'));
        $today = $now->copy()->startOfDay();
        if ($selectedDate->lt($today)) {
            return ['ok' => false, 'message' => 'Selected date is already past. Please choose today or a future date.'];
        }

        $slot = $this->slots($shopId, $orderType, $fulfillment)->firstWhere('start_time', substr($time, 0, 5));
        if (!$slot) {
            return ['ok' => false, 'message' => 'Please select a valid preferred time slot.'];
        }

        if ($selectedDate->isSameDay($today)) {
            $requiredMinutes = $this->requiredLeadMinutes($shopId, $orderType, $fulfillment, $lat, $lng);
            $earliest = $now->copy()->addMinutes($requiredMinutes);
            $slotStart = Carbon::parse($date . ' ' . $slot->start_time, config('app.timezone'));
            if ($slotStart->lt($earliest)) {
                return [
                    'ok' => false,
                    'message' => $this->hasOpenSlotToday($shopId, $orderType, $fulfillment, $lat, $lng)
                        ? 'That time slot is too soon for preparation' . ($this->isDelivery($fulfillment) ? ' and delivery travel time.' : '.')
                        : 'No more time slots can be fulfilled today. Please choose another date.',
                    'earliest_time' => $earliest->format('g:i A'),
                    'required_minutes' => $requiredMinutes,
                ];
            }
        }

        return ['ok' => true, 'message' => 'Schedule is available.', 'slot' => $slot];
    }

    public function hasOpenSlotToday(
        ?string $shopId,
        string $orderType = 'regular',
        string $fulfillment = 'Pickup',
        ?float $lat = null,
        ?float $lng = null
    ): bool {
        $now = now(config('app.timezone'));
        $today = $now->toDateString();
        $earliest = $now->copy()->addMinutes($this->requiredLeadMinutes($shopId, $orderType, $fulfillment, $lat, $lng));

        foreach ($this->slots($shopId, $orderType, $fulfillment) as $slot) {
            if (Carbon::parse($today . ' ' . $slot->start_time, config('app.timezone'))->gte($earliest)) {
                return true;
            }
        }

        return false;
    }

    public function requiredLeadMinutes(
        ?string $shopId,
        string $orderType = 'regular',
        string $fulfillment = 'Pickup',
        ?float $lat = null,
        ?float $lng = null
    ): int {
        $settings = $this->settings($shopId);
        $prep = $orderType === 'custom'
            ? (int) $settings->custom_cake_prep_minutes
            : (int) $settings->ready_made_prep_minutes;

        $buffer = $this->isDelivery($fulfillment)
            ? (int) $settings->delivery_base_buffer_minutes
            : (int) $settings->pickup_buffer_minutes;

        if ($this->isDelivery($fulfillment) && $lat !== null && $lng !== null && $settings->shop_lat && $settings->shop_lng) {
            $km = $this->distanceKm((float) $settings->shop_lat, (float) $settings->shop_lng, $lat, $lng);
            $buffer += (int) ceil($km * max(0, (int) $settings->delivery_minutes_per_km));
        }

        return max(0, $prep + $buffer);
    }

    public function label(string $start, string $end): string
    {
        return Carbon::createFromFormat('H:i', substr($start, 0, 5))->format('g:i A')
            . ' - '
            . Carbon::createFromFormat('H:i', substr($end, 0, 5))->format('g:i A');
    }

    private function defaultSlots(): array
    {
        return [
            (object) ['label' => '9:00 AM - 11:00 AM', 'start_time' => '09:00', 'end_time' => '11:00', 'fulfillment_method' => 'both', 'order_type' => 'both'],
            (object) ['label' => '11:00 AM - 1:00 PM', 'start_time' => '11:00', 'end_time' => '13:00', 'fulfillment_method' => 'both', 'order_type' => 'both'],
            (object) ['label' => '1:00 PM - 3:00 PM', 'start_time' => '13:00', 'end_time' => '15:00', 'fulfillment_method' => 'both', 'order_type' => 'both'],
            (object) ['label' => '3:00 PM - 5:00 PM', 'start_time' => '15:00', 'end_time' => '17:00', 'fulfillment_method' => 'both', 'order_type' => 'both'],
            (object) ['label' => '5:00 PM - 7:00 PM', 'start_time' => '17:00', 'end_time' => '19:00', 'fulfillment_method' => 'both', 'order_type' => 'both'],
        ];
    }

    private function columnValue(?object $settings, string $column, int $default): int
    {
        if (!$settings || !Schema::hasColumn('site_settings', $column)) {
            return $default;
        }

        return max(0, (int) ($settings->{$column} ?? $default));
    }

    private function isDelivery(string $fulfillment): bool
    {
        return strtolower($fulfillment) === 'delivery';
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
