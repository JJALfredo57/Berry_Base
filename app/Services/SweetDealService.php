<?php

namespace App\Services;

use App\Helpers\CakeshopHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SweetDealService
{
    public function validateCartItems(iterable $items): array
    {
        if (!$this->hasQuotaColumn()) {
            return ['ok' => true];
        }

        foreach ($this->groupDiscountedCartItems($items) as $productId => $quantity) {
            $discount = CakeshopHelper::getActiveProductDiscount((string) $productId);
            if (!$discount) {
                return ['ok' => false, 'message' => 'A Sweet Deal in your cart is no longer available. Please remove and add the cake again to refresh the price.'];
            }

            $remaining = $this->remainingQuota($discount);
            if ($remaining !== null && $quantity > $remaining) {
                return ['ok' => false, 'message' => "Only {$remaining} Sweet Deal pcs left for this cake. Please reduce quantity or remove and add the cake again at the regular price."];
            }
        }

        return ['ok' => true];
    }

    public function validateDirectItem(string $productId, int $quantity, array $pricing): array
    {
        if (!$this->hasQuotaColumn() || empty($pricing['has_discount'])) {
            return ['ok' => true];
        }

        $discount = CakeshopHelper::getActiveProductDiscount($productId);
        if (!$discount) {
            return ['ok' => false, 'message' => 'This Sweet Deal is no longer available. Please refresh the catalog and try again.'];
        }

        $remaining = $this->remainingQuota($discount);
        if ($remaining !== null && max(1, $quantity) > $remaining) {
            return ['ok' => false, 'message' => "Only {$remaining} Sweet Deal pcs left for this cake. Please reduce quantity or add it again at the regular price."];
        }

        return ['ok' => true];
    }

    public function scheduleLimitForCartItems(iterable $items): ?array
    {
        $limits = [];
        foreach ($this->groupDiscountedCartItems($items) as $productId => $quantity) {
            $discount = CakeshopHelper::getActiveProductDiscount((string) $productId);
            if (!$discount || empty($discount->ends_at)) {
                continue;
            }
            $limits[] = [
                'date' => date('Y-m-d', strtotime((string) $discount->ends_at)),
                'label' => date('M d, Y', strtotime((string) $discount->ends_at)),
            ];
        }

        if (!$limits) {
            return null;
        }

        usort($limits, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $limits[0];
    }

    public function scheduleLimitForDirectItem(string $productId, array $pricing): ?array
    {
        if (empty($pricing['has_discount'])) {
            return null;
        }

        $discount = CakeshopHelper::getActiveProductDiscount($productId);
        if (!$discount || empty($discount->ends_at)) {
            return null;
        }

        return [
            'date' => date('Y-m-d', strtotime((string) $discount->ends_at)),
            'label' => date('M d, Y', strtotime((string) $discount->ends_at)),
        ];
    }

    public function validateScheduleDateForCartItems(iterable $items, ?string $scheduleDate): array
    {
        $limit = $this->scheduleLimitForCartItems($items);
        return $this->validateScheduleAgainstLimit($limit, $scheduleDate);
    }

    public function validateScheduleDateForDirectItem(string $productId, array $pricing, ?string $scheduleDate): array
    {
        $limit = $this->scheduleLimitForDirectItem($productId, $pricing);
        return $this->validateScheduleAgainstLimit($limit, $scheduleDate);
    }

    private function validateScheduleAgainstLimit(?array $limit, ?string $scheduleDate): array
    {
        if (!$limit || !$scheduleDate) {
            return ['ok' => true];
        }

        $selected = date('Y-m-d', strtotime($scheduleDate));
        if ($selected > $limit['date']) {
            return [
                'ok' => false,
                'message' => 'Sweet Deal orders must be scheduled on or before ' . $limit['label'] . '.',
            ];
        }

        return ['ok' => true];
    }

    public function reserveCartItems(iterable $items): array
    {
        return $this->reserveGrouped($this->groupDiscountedCartItems($items));
    }

    public function reserveDirectItem(string $productId, int $quantity, array $pricing): array
    {
        if (empty($pricing['has_discount'])) {
            return ['ok' => true];
        }

        return $this->reserveGrouped(collect([$productId => max(1, $quantity)]));
    }

    private function reserveGrouped(Collection $grouped): array
    {
        if (!$this->hasQuotaColumn() || $grouped->isEmpty()) {
            return ['ok' => true];
        }

        $now = now()->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($grouped, $now) {
            foreach ($grouped as $productId => $quantity) {
                $discount = DB::table('product_discounts')
                    ->where('product_id', (string) $productId)
                    ->where('is_active', true)
                    ->where(function ($query) use ($now) {
                        $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($query) use ($now) {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    })
                    ->where(function ($query) {
                        $query->whereNull('deal_quantity_limit')->orWhere('deal_quantity_limit', '>', 0);
                    })
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$discount) {
                    return ['ok' => false, 'message' => 'A Sweet Deal was just claimed by another customer. Please refresh your cart and try again.'];
                }

                $remaining = $this->remainingQuota($discount);
                if ($remaining === null) {
                    continue;
                }

                $quantity = max(1, (int) $quantity);
                if ($remaining < $quantity) {
                    return ['ok' => false, 'message' => "Only {$remaining} Sweet Deal pcs left for this cake. Please reduce quantity or refresh your cart."];
                }

                DB::table('product_discounts')->where('id', $discount->id)->update([
                    'deal_quantity_limit' => $remaining - $quantity,
                    'updated_at' => now(),
                ]);
            }

            return ['ok' => true];
        });
    }

    private function groupDiscountedCartItems(iterable $items): Collection
    {
        return collect($items)
            ->map(fn ($item) => is_array($item) ? (object) $item : $item)
            ->filter(function ($item) {
                $meta = json_decode($item->meta ?? '[]', true) ?: [];
                return ($meta['cart_type'] ?? '') !== 'custom_cake'
                    && !empty($item->product_id)
                    && (float) ($item->discount_amount_snapshot ?? 0) > 0;
            })
            ->groupBy(fn ($item) => (string) $item->product_id)
            ->map(fn ($group) => $group->sum(fn ($item) => max(1, (int) ($item->quantity ?? 1))));
    }

    private function remainingQuota(?object $discount): ?int
    {
        if (!$discount || !property_exists($discount, 'deal_quantity_limit') || $discount->deal_quantity_limit === null) {
            return null;
        }

        return max(0, (int) $discount->deal_quantity_limit);
    }

    private function hasQuotaColumn(): bool
    {
        return Schema::hasTable('product_discounts') && Schema::hasColumn('product_discounts', 'deal_quantity_limit');
    }
}