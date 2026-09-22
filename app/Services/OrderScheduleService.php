<?php

namespace App\Services;

use Carbon\Carbon;

class OrderScheduleService
{
    public function validate(
        ?string $date,
        ?string $time,
        ?string $shopId = null,
        string $orderType = 'regular',
        string $fulfillment = 'Pickup',
        ?float $lat = null,
        ?float $lng = null,
        bool $enforceLeadTime = true
    ): array {
        return app(FulfillmentScheduleService::class)
            ->validate($shopId, $date, $time, $orderType, $fulfillment, $lat, $lng, $enforceLeadTime);
    }

    public function hasOpenSlotToday(?Carbon $now = null): bool
    {
        return app(FulfillmentScheduleService::class)->hasOpenSlotToday(null);
    }
}
