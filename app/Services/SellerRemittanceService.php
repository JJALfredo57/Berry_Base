<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerRemittanceService
{
    public const WAITING_STATUSES = ['pending', 'rejected', 'awaiting_payment', 'qr_expired'];

    public const OUTSTANDING_STATUSES = ['pending', 'submitted', 'rejected', 'awaiting_payment', 'qr_expired'];

    /** Read-only display state; payout amounts never determine cash handover status. */
    public static function display(?object $remittance, ?float $cakeCashDue = null): array
    {
        $status = $remittance->status ?? null;
        $due = $remittance->cake_remittance_amount ?? $remittance->amount ?? $cakeCashDue;
        if ($status === 'confirmed') {
            return ['label' => 'COD remitted', 'heading' => 'COD remittance completed', 'amount_label' => 'Cash received by seller'];
        }
        if ($due !== null && (float) $due <= 0.009) {
            return ['label' => 'No COD cash to remit', 'heading' => 'No COD cash to remit', 'amount_label' => 'COD cash required'];
        }
        if ($status === 'submitted') {
            return ['label' => 'Awaiting seller confirmation', 'heading' => 'COD handover awaiting confirmation', 'amount_label' => 'Cash handed over for confirmation'];
        }

        $label = match ($status) {
            'pending' => 'Waiting for rider',
            'rejected' => 'Needs rider retry',
            'awaiting_payment' => 'Waiting for rider payment',
            'qr_expired' => 'Payment expired — rider action needed',
            null => $due !== null && $due > 0.009 ? 'Waiting for rider' : 'Remittance status unavailable',
            default => 'Remittance status unavailable',
        };

        return ['label' => $label, 'heading' => 'Rider COD remittance', 'amount_label' => 'Cash to hand over to seller'];
    }

    public function recordsForOrders(string $shopId, array $orderIds): Collection
    {
        if (! $orderIds || ! Schema::hasTable('rider_remittances')) {
            return collect();
        }

        return DB::table('rider_remittances')->where('shop_id', $shopId)
            ->whereIn('order_id', $orderIds)->get()->keyBy('order_id');
    }

    public function outstandingTotal(string $shopId): float
    {
        if (! Schema::hasTable('rider_remittances')) {
            return 0.0;
        }

        return round((float) DB::table('rider_remittances')
            ->where('shop_id', $shopId)
            ->whereIn('status', self::OUTSTANDING_STATUSES)
            ->sum(DB::raw('COALESCE(cake_remittance_amount, amount, 0)')), 2);
    }

    public function summary(string $shopId): array
    {
        if (! Schema::hasTable('rider_remittances')) {
            return [
                'outstanding' => 0.0, 'waiting' => 0.0, 'submitted' => 0.0,
                'confirmed' => 0.0, 'outstanding_count' => 0, 'record_count' => 0,
            ];
        }

        $base = DB::table('rider_remittances')->where('shop_id', $shopId);
        $sum = fn (array $statuses): float => round((float) (clone $base)
            ->whereIn('status', $statuses)
            ->sum(DB::raw('COALESCE(cake_remittance_amount, amount, 0)')), 2);

        return [
            'outstanding' => $sum(self::OUTSTANDING_STATUSES),
            'waiting' => $sum(self::WAITING_STATUSES),
            'submitted' => $sum(['submitted']),
            'confirmed' => $sum(['confirmed']),
            'outstanding_count' => (clone $base)->whereIn('status', self::OUTSTANDING_STATUSES)->count(),
            'record_count' => (clone $base)->count(),
        ];
    }

    public function query(string $shopId): Builder
    {
        return DB::table('rider_remittances as rr')
            ->join('orders as o', 'o.id', '=', 'rr.order_id')
            ->leftJoin('riders as r', 'r.id', '=', 'rr.rider_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('custom_orders as co', 'co.order_id', '=', 'o.id')
            ->where('rr.shop_id', $shopId)
            ->select(
                'rr.*',
                'o.track_code',
                'o.status as order_status',
                'o.payment_status',
                'o.payment_method',
                'o.quantity',
                'o.total_price',
                'o.created_at as order_created_at',
                DB::raw("CASE WHEN o.user_id IS NOT NULL THEN u.fullname ELSE COALESCE(o.guest_name, o.fullname, 'Customer') END as customer_name"),
                DB::raw("COALESCE(r.name, o.delivery_rider_name, 'Rider unavailable') as rider_name"),
                DB::raw("COALESCE(p.name, co.cake_name, 'Cake order') as product_name"),
                DB::raw('COALESCE(rr.customer_collected_amount, rr.amount, 0) as customer_cash_collected'),
                DB::raw('COALESCE(rr.cake_remittance_amount, rr.amount, 0) as cake_amount_due'),
                DB::raw('COALESCE(rr.delivery_fee_retained, 0) as rider_delivery_fee')
            );
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        $status = (string) ($filters['status'] ?? 'outstanding');
        if ($status === 'outstanding') {
            $query->whereIn('rr.status', self::OUTSTANDING_STATUSES);
        } elseif ($status === 'waiting') {
            $query->whereIn('rr.status', self::WAITING_STATUSES);
        } elseif ($status === 'submitted') {
            $query->where('rr.status', 'submitted');
        } elseif ($status === 'confirmed') {
            $query->where('rr.status', 'confirmed');
        } elseif ($status !== 'all') {
            $query->where('rr.status', $status);
        }

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $nested) use ($like) {
                $nested->whereRaw('LOWER(rr.order_id) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(COALESCE(o.track_code, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(r.name, o.delivery_rider_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(CASE WHEN o.user_id IS NOT NULL THEN u.fullname ELSE COALESCE(o.guest_name, o.fullname, '') END) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(p.name, co.cake_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(rr.reference_number, '')) LIKE ?", [$like]);
            });
        }

        if (! empty($filters['rider_id'])) {
            $query->where('rr.rider_id', (int) $filters['rider_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereRaw('DATE(COALESCE(rr.collected_at, rr.created_at)) >= ?', [$filters['from']]);
        }
        if (! empty($filters['to'])) {
            $query->whereRaw('DATE(COALESCE(rr.collected_at, rr.created_at)) <= ?', [$filters['to']]);
        }

        return $query;
    }

    public function sort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderByRaw('COALESCE(rr.collected_at, rr.created_at) ASC')->orderBy('rr.id'),
            'amount_high' => $query->orderByRaw('COALESCE(rr.cake_remittance_amount, rr.amount, 0) DESC')->orderByDesc('rr.id'),
            'amount_low' => $query->orderByRaw('COALESCE(rr.cake_remittance_amount, rr.amount, 0) ASC')->orderByDesc('rr.id'),
            default => $query->orderByRaw('COALESCE(rr.collected_at, rr.created_at) DESC')->orderByDesc('rr.id'),
        };
    }

    public function filteredTotals(Builder $query): array
    {
        return [
            'customer_collected' => round((float) (clone $query)->sum(DB::raw('COALESCE(rr.customer_collected_amount, rr.amount, 0)')), 2),
            'rider_delivery_fee' => round((float) (clone $query)->sum(DB::raw('COALESCE(rr.delivery_fee_retained, 0)')), 2),
            'cake_amount_due' => round((float) (clone $query)->sum(DB::raw('COALESCE(rr.cake_remittance_amount, rr.amount, 0)')), 2),
        ];
    }

    public function filteredTotal(Builder $query): float
    {
        return $this->filteredTotals($query)['cake_amount_due'];
    }
}
