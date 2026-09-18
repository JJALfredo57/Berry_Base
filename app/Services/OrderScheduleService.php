<?php

namespace App\Services;

use Carbon\Carbon;

class OrderScheduleService
{
    private array $slots = [
        '09:00' => '11:00',
        '11:00' => '13:00',
        '13:00' => '15:00',
        '15:00' => '17:00',
        '17:00' => '19:00',
    ];

    public function validate(?string $date, ?string $time): array
    {
        if (!$date) {
            return ['ok' => false, 'message' => 'Please select your preferred date.'];
        }

        if (!$time) {
            return ['ok' => false, 'message' => 'Please select a preferred time slot.'];
        }

        if (!array_key_exists($time, $this->slots)) {
            return ['ok' => false, 'message' => 'Please select a valid preferred time slot.'];
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

        if ($selectedDate->isSameDay($today)) {
            $slotEnd = Carbon::parse($date . ' ' . $this->slots[$time], config('app.timezone'));
            if ($slotEnd->lte($now)) {
                return [
                    'ok' => false,
                    'message' => $this->hasOpenSlotToday($now)
                        ? 'That time slot is already closed for today. Please choose a later time slot or another date.'
                        : 'Orders for today are already closed. Please choose tomorrow or another date.',
                ];
            }
        }

        return ['ok' => true, 'message' => 'Schedule is available.'];
    }

    public function hasOpenSlotToday(?Carbon $now = null): bool
    {
        $now = $now ?: now(config('app.timezone'));
        $today = $now->toDateString();

        foreach ($this->slots as $endTime) {
            if (Carbon::parse($today . ' ' . $endTime, config('app.timezone'))->gt($now)) {
                return true;
            }
        }

        return false;
    }
}
