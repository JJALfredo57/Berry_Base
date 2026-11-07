<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreparationWindowService
{
    public const BOOKING_WINDOW_MONTHS = 1;

    public function settings(?string $shopId): object
    {
        $settings = $shopId
            ? DB::table('site_settings')->where('shop_id', $shopId)->first()
            : DB::table('site_settings')->whereNull('shop_id')->first();

        if (!$settings) {
            $settings = DB::table('site_settings')->first();
        }

        return (object) [
            'ready_made_prep_days' => $this->columnValue($settings, 'ready_made_prep_days', 0),
            'custom_cake_prep_days' => $this->columnValue($settings, 'custom_cake_prep_days', 3),
            'custom_cart_hold_minutes' => $this->columnValue($settings, 'custom_cart_hold_minutes', 15),
        ];
    }

    public function earliestDate(?string $shopId, string $type): Carbon
    {
        $settings = $this->settings($shopId);
        $days = $type === 'custom'
            ? (int) $settings->custom_cake_prep_days
            : (int) $settings->ready_made_prep_days;

        return now(config('app.timezone'))->startOfDay()->addDays(max(0, $days));
    }

    public function latestDate(): Carbon
    {
        return now(config('app.timezone'))->startOfDay()->addMonthNoOverflow(self::BOOKING_WINDOW_MONTHS);
    }

    public function validateDate(?string $shopId, ?string $date, string $type): array
    {
        if (!$date) {
            return ['ok' => false, 'message' => 'Please select your preferred date.'];
        }

        try {
            $selected = Carbon::parse($date, config('app.timezone'))->startOfDay();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Please select a valid preferred date.'];
        }

        $earliest = $this->earliestDate($shopId, $type);
        $latest = $this->latestDate();
        if ($earliest->gt($latest)) {
            return [
                'ok' => false,
                'message' => 'This seller requires ' . $earliest->diffInDays(now(config('app.timezone'))->startOfDay()) . ' preparation days, which is longer than the current one-month booking window. Please contact the seller for a later schedule.',
                'earliest_date' => $earliest->toDateString(),
                'latest_date' => $latest->toDateString(),
                'booking_window_months' => self::BOOKING_WINDOW_MONTHS,
            ];
        }
        if ($selected->lt($earliest)) {
            $settings = $this->settings($shopId);
            $days = $type === 'custom'
                ? (int) $settings->custom_cake_prep_days
                : (int) $settings->ready_made_prep_days;
            $label = $type === 'custom' ? 'custom cakes' : 'ready-made cakes';

            return [
                'ok' => false,
                'message' => ucfirst($label) . " need at least {$days} preparation day" . ($days === 1 ? '' : 's') . '. Earliest available date is ' . $earliest->format('M d, Y') . '.',
                'earliest_date' => $earliest->toDateString(),
                'prep_days' => $days,
            ];
        }

        if ($selected->gt($latest)) {
            return [
                'ok' => false,
                'message' => 'Preferred dates are available only through ' . $latest->format('M d, Y') . '. Please choose a date within the next month.',
                'earliest_date' => $earliest->toDateString(),
                'latest_date' => $latest->toDateString(),
                'booking_window_months' => self::BOOKING_WINDOW_MONTHS,
            ];
        }

        return [
            'ok' => true,
            'earliest_date' => $earliest->toDateString(),
            'latest_date' => $latest->toDateString(),
            'booking_window_months' => self::BOOKING_WINDOW_MONTHS,
        ];
    }

    public function holdUntil(?string $shopId): Carbon
    {
        $minutes = max(1, (int) $this->settings($shopId)->custom_cart_hold_minutes);
        return now(config('app.timezone'))->addMinutes($minutes);
    }

    public function reviewDeadline(?string $shopId, ?string $preferredDate): ?Carbon
    {
        if (!$preferredDate) return null;
        try {
            $date = Carbon::parse($preferredDate, config('app.timezone'))->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $days = max(0, (int) $this->settings($shopId)->custom_cake_prep_days);
        return $date->copy()->subDays($days)->endOfDay();
    }

    public function validateCustomHold(array $meta): array
    {
        $expiresAt = trim((string) ($meta['fulfillment_hold_expires_at'] ?? ''));
        if ($expiresAt === '') {
            return ['ok' => false, 'message' => 'This custom cake schedule needs to be refreshed before checkout.'];
        }

        try {
            $expires = Carbon::parse($expiresAt, config('app.timezone'));
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'This custom cake schedule needs to be refreshed before checkout.'];
        }

        if ($expires->lte(now(config('app.timezone')))) {
            return ['ok' => false, 'message' => 'A custom cake schedule hold expired. Please update fulfillment to recheck availability before checkout.'];
        }

        return ['ok' => true, 'expires_at' => $expires->toDateTimeString()];
    }

    private function columnValue(?object $settings, string $column, int $default): int
    {
        if (!$settings) return $default;
        if (!Schema::hasColumn('site_settings', $column)) return $default;
        return max(0, (int) ($settings->{$column} ?? $default));
    }
}
