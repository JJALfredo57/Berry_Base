<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerRiskService
{
    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') return '';
        if (strlen($digits) === 10) return '63' . $digits;
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) return '63' . substr($digits, 1);
        if (strlen($digits) === 12 && str_starts_with($digits, '63')) return $digits;
        return $digits;
    }

    public function phoneVariants(?string $phone): array
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') return [];

        $local = strlen($normalized) === 12 && str_starts_with($normalized, '63')
            ? '0' . substr($normalized, 2)
            : $normalized;

        return array_values(array_unique(array_filter([
            $phone,
            $normalized,
            '+' . $normalized,
            $local,
            preg_replace('/\D/', '', (string) $phone),
        ])));
    }

    public function summary(?string $phone, ?string $shopId = null): array
    {
        $normalized = $this->normalizePhone($phone);
        $variants = $this->phoneVariants($phone);
        $empty = [
            'phone' => $phone,
            'phone_normalized' => $normalized,
            'level' => 'low',
            'score' => 0,
            'is_blocked' => false,
            'blocked_until' => null,
            'block_reason' => null,
            'cancelled_7d' => 0,
            'cancel_requests_7d' => 0,
            'unpaid_24h' => 0,
            'active_unpaid' => 0,
            'pending_custom_same_shop' => 0,
            'successful_30d' => 0,
            'reasons' => [],
        ];

        if ($normalized === '' || empty($variants) || !Schema::hasTable('orders')) {
            return $empty;
        }

        $now = now();
        $since7 = $now->copy()->subDays(7);
        $since24 = $now->copy()->subDay();
        $since30 = $now->copy()->subDays(30);
        $orderPhones = fn ($query) => $query->whereIn('guest_phone', $variants);

        $summary = $empty;
        $summary['cancelled_7d'] = (int) DB::table('orders')
            ->where($orderPhones)
            ->where('created_at', '>=', $since7)
            ->where(fn ($q) => $q->where('status', 'Cancelled')->orWhere('cancel_status', 'accepted'))
            ->count();

        $summary['cancel_requests_7d'] = (int) DB::table('orders')
            ->where($orderPhones)
            ->where('cancel_requested_at', '>=', $since7)
            ->count();

        $summary['unpaid_24h'] = (int) DB::table('orders')
            ->where($orderPhones)
            ->where('created_at', '>=', $since24)
            ->where('status', 'Awaiting Deposit')
            ->where(fn ($q) => $q->whereNull('deposit_status')->orWhere('deposit_status', 'pending'))
            ->count();

        $summary['active_unpaid'] = (int) DB::table('orders')
            ->where($orderPhones)
            ->whereNotIn('status', ['Cancelled', 'Delivered', 'Picked Up'])
            ->where(fn ($q) => $q->where('payment_status', 'Unpaid')->orWhere('deposit_status', 'pending'))
            ->count();

        $summary['successful_30d'] = (int) DB::table('orders')
            ->where($orderPhones)
            ->where('created_at', '>=', $since30)
            ->where(fn ($q) => $q->whereIn('status', ['Delivered', 'Picked Up'])->orWhere('payment_status', 'Paid')->orWhere('deposit_status', 'paid'))
            ->count();

        if (Schema::hasTable('custom_orders')) {
            $custom = DB::table('custom_orders as co')
                ->leftJoin('orders as o', 'o.id', '=', 'co.order_id')
                ->where(fn ($q) => $q->whereIn('co.guest_phone', $variants)->orWhereIn('o.guest_phone', $variants));
            if ($shopId) $custom->where('co.shop_id', $shopId);
            if (Schema::hasColumn('custom_orders', 'review_status')) {
                $custom->where('co.review_status', 'pending');
            } else {
                $custom->whereNotIn('co.status', ['Rejected', 'Cancelled', 'Approved']);
            }
            $summary['pending_custom_same_shop'] = (int) $custom->count();
        }

        $block = $this->activeBlock($phone);
        if ($block) {
            $summary['is_blocked'] = true;
            $summary['blocked_until'] = $block->blocked_until;
            $summary['block_reason'] = $block->reason ?: 'Phone number is blocked.';
        }

        $score = 0;
        $score += $summary['cancelled_7d'] * 30;
        $score += $summary['cancel_requests_7d'] * 15;
        $score += $summary['unpaid_24h'] * 25;
        $score += $summary['active_unpaid'] * 12;
        $score += $summary['pending_custom_same_shop'] * 15;
        $score -= min(20, $summary['successful_30d'] * 5);
        if ($summary['is_blocked']) $score = 100;

        $summary['score'] = max(0, $score);
        $summary['reasons'] = $this->riskReasons($summary);
        $summary['level'] = $summary['is_blocked'] || $summary['cancelled_7d'] >= 3 || $summary['unpaid_24h'] >= 3 || $summary['active_unpaid'] >= 5
            ? 'blocked'
            : ($summary['score'] >= 60 ? 'suspicious' : ($summary['score'] >= 30 ? 'watch' : 'low'));

        return $summary;
    }

    public function evaluateOrder(?string $phone, ?string $shopId = null, string $type = 'regular'): array
    {
        $summary = $this->summary($phone, $shopId);
        $blocked = $summary['level'] === 'blocked'
            || ($type === 'custom' && $summary['pending_custom_same_shop'] >= 2);

        if (!$blocked) {
            return ['allowed' => true, 'summary' => $summary, 'message' => null];
        }

        $message = $summary['is_blocked']
            ? 'This phone number is temporarily blocked from placing orders. Reason: ' . $summary['block_reason']
            : 'This phone number has too many recent cancelled, unpaid, or pending orders. Please contact the shop before placing another order.';

        if ($type === 'custom' && $summary['pending_custom_same_shop'] >= 2) {
            $message = 'This phone number already has multiple pending custom orders with this seller. Please wait for review before submitting another custom order.';
        }

        return ['allowed' => false, 'summary' => $summary, 'message' => $message];
    }

    public function badge(?string $phone, ?string $shopId = null): array
    {
        $summary = $this->summary($phone, $shopId);
        $styles = [
            'low' => ['label' => 'Low Risk', 'bg' => '#ecfdf5', 'color' => '#047857'],
            'watch' => ['label' => 'Watchlist', 'bg' => '#fef9c3', 'color' => '#854d0e'],
            'suspicious' => ['label' => 'Suspicious', 'bg' => '#ffedd5', 'color' => '#9a3412'],
            'blocked' => ['label' => 'Blocked', 'bg' => '#fee2e2', 'color' => '#991b1b'],
        ];
        return $summary + ($styles[$summary['level']] ?? $styles['low']);
    }

    public function blockPhone(?string $phone, ?string $reason, ?string $role = null, ?string $userId = null, ?Carbon $until = null): void
    {
        if (!Schema::hasTable('customer_phone_blocks')) return;
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') return;

        DB::table('customer_phone_blocks')->updateOrInsert(
            ['phone_normalized' => $normalized, 'is_active' => true],
            [
                'phone_display' => $phone,
                'reason' => $reason ?: 'Blocked by admin.',
                'blocked_by_role' => $role,
                'blocked_by_user_id' => $userId,
                'blocked_until' => $until,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function unblockPhone(?string $phone): void
    {
        if (!Schema::hasTable('customer_phone_blocks')) return;
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') return;
        DB::table('customer_phone_blocks')
            ->where('phone_normalized', $normalized)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    private function activeBlock(?string $phone): ?object
    {
        if (!Schema::hasTable('customer_phone_blocks')) return null;
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') return null;

        return DB::table('customer_phone_blocks')
            ->where('phone_normalized', $normalized)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()))
            ->orderByDesc('id')
            ->first();
    }

    private function riskReasons(array $summary): array
    {
        $reasons = [];
        if ($summary['is_blocked']) $reasons[] = $summary['block_reason'] ?: 'Manually blocked.';
        if ($summary['cancelled_7d'] > 0) $reasons[] = $summary['cancelled_7d'] . ' cancelled order(s) in 7 days';
        if ($summary['cancel_requests_7d'] > 0) $reasons[] = $summary['cancel_requests_7d'] . ' cancel request(s) in 7 days';
        if ($summary['unpaid_24h'] > 0) $reasons[] = $summary['unpaid_24h'] . ' unpaid deposit order(s) in 24 hours';
        if ($summary['active_unpaid'] > 0) $reasons[] = $summary['active_unpaid'] . ' active unpaid order(s)';
        if ($summary['pending_custom_same_shop'] > 0) $reasons[] = $summary['pending_custom_same_shop'] . ' pending custom order(s)';
        if ($summary['successful_30d'] > 0) $reasons[] = $summary['successful_30d'] . ' successful paid/completed order(s) in 30 days';
        return $reasons;
    }
}
