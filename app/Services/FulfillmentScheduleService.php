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
            'shop_open_time' => $this->timeColumnValue($settings, 'shop_open_time', '09:00'),
            'shop_close_time' => $this->timeColumnValue($settings, 'shop_close_time', '19:00'),
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
        $settings = $this->settings($shopId);
        return collect([
            (object) [
                'label' => $this->label($settings->shop_open_time, $settings->shop_close_time),
                'start_time' => $settings->shop_open_time,
                'end_time' => $settings->shop_close_time,
                'fulfillment_method' => 'both',
                'order_type' => 'both',
            ],
        ]);
    }

    public function slotsForCheckout(?string $shopId, string $orderType = 'regular'): array
    {
        $settings = $this->settings($shopId);
        return [[
            'value' => $settings->shop_open_time,
            'label' => $this->label($settings->shop_open_time, $settings->shop_close_time),
            'start' => $settings->shop_open_time,
            'end' => $settings->shop_close_time,
            'fulfillment' => 'both',
            'order_type' => 'both',
        ]];
    }

    public function validate(
        ?string $shopId,
        ?string $date,
        ?string $time,
        string $orderType = 'regular',
        string $fulfillment = 'Pickup',
        ?float $lat = null,
        ?float $lng = null,
        bool $enforceLeadTime = true
    ): array {
        if (!$date) {
            return ['ok' => false, 'message' => 'Please select your preferred date.'];
        }

        if (!$time) {
            return ['ok' => false, 'message' => 'Please select a preferred time.'];
        }

        $time = substr((string) $time, 0, 5);
        if (!$this->validTime($time)) {
            return ['ok' => false, 'message' => 'Please select a valid preferred time.'];
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

        $settings = $this->settings($shopId);
        $open = substr((string) $settings->shop_open_time, 0, 5);
        $close = substr((string) $settings->shop_close_time, 0, 5);
        if ($time < $open || $time > $close) {
            return [
                'ok' => false,
                'message' => 'Please choose a time within shop hours: '
                    . Carbon::createFromFormat('H:i', $open)->format('g:i A')
                    . ' to '
                    . Carbon::createFromFormat('H:i', $close)->format('g:i A')
                    . '.',
                'shop_open_time' => $open,
                'shop_close_time' => $close,
            ];
        }

        if ($enforceLeadTime && $selectedDate->isSameDay($today)) {
            $requiredMinutes = $this->requiredLeadMinutes($shopId, $orderType, $fulfillment, $lat, $lng);
            $earliest = $now->copy()->addMinutes($requiredMinutes);
            $selectedAt = Carbon::parse($date . ' ' . $time, config('app.timezone'));
            if ($selectedAt->lt($earliest)) {
                return [
                    'ok' => false,
                    'message' => $this->hasOpenSlotToday($shopId, $orderType, $fulfillment, $lat, $lng)
                        ? 'That time is too soon for preparation' . ($this->isDelivery($fulfillment) ? ' and delivery travel time.' : '.')
                        : 'No more times can be fulfilled today. Please choose another date.',
                    'earliest_time' => $earliest->format('g:i A'),
                    'required_minutes' => $requiredMinutes,
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Schedule is available.',
            'slot' => (object) [
                'label' => Carbon::createFromFormat('H:i', $time)->format('g:i A'),
                'start_time' => $time,
                'end_time' => $time,
            ],
        ];
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
        $settings = $this->settings($shopId);
        $close = Carbon::parse($today . ' ' . $settings->shop_close_time, config('app.timezone'));

        return $earliest->lte($close);
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

    private function columnValue(?object $settings, string $column, int $default): int
    {
        if (!$settings || !Schema::hasColumn('site_settings', $column)) {
            return $default;
        }

        return max(0, (int) ($settings->{$column} ?? $default));
    }

    private function timeColumnValue(?object $settings, string $column, string $default): string
    {
        if (!$settings || !Schema::hasColumn('site_settings', $column)) {
            return $default;
        }

        $value = substr((string) ($settings->{$column} ?? $default), 0, 5);
        return $this->validTime($value) ? $value : $default;
    }

    private function validTime(string $time): bool
    {
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
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