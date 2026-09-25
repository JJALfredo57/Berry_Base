<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderRequestController extends Controller
{
    private const ACTIVE_STATUSES = ['pending', 'accepted', 'customer_accepted', 'schedule_suggested', 'alternative_offered', 'needs_more_details'];
    private const OFFER_STATUSES = ['accepted', 'customer_accepted'];
    private const CLOSED_STATUSES = ['declined', 'customer_declined', 'expired', 'converted', 'cancelled'];

    public function index(Request $request)
    {
        $uid = (string) (session('user')['id'] ?? '');
        if ($uid === '') abort(403);

        $filter = $request->input('filter', 'active');
        if (!in_array($filter, ['active', 'offers', 'closed', 'all'], true)) {
            $filter = 'active';
        }

        if (!Schema::hasTable('order_requests')) {
            return view('customer.order_requests', [
                'requests' => collect(),
                'filter' => $filter,
                'counts' => ['active' => 0, 'offers' => 0, 'closed' => 0, 'all' => 0],
            ]);
        }

        $hasAcceptedDate = Schema::hasColumn('order_requests', 'accepted_date');
        $hasAcceptedTime = Schema::hasColumn('order_requests', 'accepted_time');
        $hasAcceptedDatetime = Schema::hasColumn('order_requests', 'accepted_datetime');
        $hasConvertedOrderId = Schema::hasColumn('order_requests', 'converted_order_id');

        $base = DB::table('order_requests as r')->where('r.user_id', $uid);
        $counts = [
            'active' => (clone $base)->whereIn('r.status', self::ACTIVE_STATUSES)->count(),
            'offers' => (clone $base)->whereIn('r.status', self::OFFER_STATUSES)->count(),
            'closed' => (clone $base)->whereIn('r.status', self::CLOSED_STATUSES)->count(),
            'all' => (clone $base)->count(),
        ];

        $selects = [
            'r.*',
            'p.name as product_name',
            'p.image_path',
            'p.flavor as product_flavor',
            'p.classification as product_classification',
            's.shop_name',
            's.shop_slug',
            'ap.name as alternative_product_name',
            DB::raw(($hasAcceptedDate ? 'r.accepted_date' : 'NULL') . ' as accepted_date'),
            DB::raw(($hasAcceptedTime ? 'r.accepted_time' : 'NULL') . ' as accepted_time'),
            DB::raw(($hasAcceptedDatetime ? 'r.accepted_datetime' : 'NULL') . ' as accepted_datetime'),
            DB::raw(($hasConvertedOrderId ? 'r.converted_order_id' : 'NULL') . ' as converted_order_id'),
            DB::raw(($hasConvertedOrderId ? 'o.track_code' : 'NULL') . ' as converted_track_code'),
            DB::raw(($hasConvertedOrderId ? 'o.status' : 'NULL') . ' as converted_order_status'),
        ];

        $query = DB::table('order_requests as r')
            ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
            ->leftJoin('products as ap', 'ap.id', '=', 'r.alternative_product_id')
            ->leftJoin('shops as s', 's.id', '=', 'r.shop_id')
            ->when($hasConvertedOrderId, fn ($q) => $q->leftJoin('orders as o', 'o.id', '=', 'r.converted_order_id'))
            ->where('r.user_id', $uid)
            ->select($selects);

        if ($filter === 'active') {
            $query->whereIn('r.status', self::ACTIVE_STATUSES);
        } elseif ($filter === 'offers') {
            $query->whereIn('r.status', self::OFFER_STATUSES);
        } elseif ($filter === 'closed') {
            $query->whereIn('r.status', self::CLOSED_STATUSES);
        }

        $requests = $query
            ->orderByRaw("CASE WHEN r.status IN ('accepted','customer_accepted') THEN 0 WHEN r.status = 'pending' AND r.is_rush = true THEN 1 WHEN r.status = 'pending' THEN 2 WHEN r.status IN ('schedule_suggested','alternative_offered','needs_more_details') THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN r.preferred_datetime IS NULL THEN 1 ELSE 0 END')
            ->orderBy('r.preferred_datetime')
            ->orderByDesc('r.created_at')
            ->paginate(10)
            ->withQueryString();

        $requests->getCollection()->transform(function ($item) {
            $item->status_meta = $this->statusMeta((string) ($item->status ?? 'pending'));
            $cakeName = property_exists($item, 'cake_name') ? $item->cake_name : null;
            $item->display_name = $item->product_name ?: ($cakeName ?: 'Cake request');
            $item->display_image = $item->image_path ?: null;
            $item->preferred_label = $this->dateTimeLabel($item->preferred_datetime ?? null, $item->preferred_date ?? null, $item->preferred_time ?? null);
            $item->accepted_label = $this->dateTimeLabel($item->accepted_datetime ?? null, $item->accepted_date ?? null, $item->accepted_time ?? null);
            return $item;
        });

        return view('customer.order_requests', compact('requests', 'filter', 'counts'));
    }

    private function statusMeta(string $status): array
    {
        return match ($status) {
            'accepted' => ['label' => 'Seller accepted', 'tone' => 'success', 'hint' => 'Review the offer and continue checkout when ready.'],
            'customer_accepted' => ['label' => 'Checkout started', 'tone' => 'primary', 'hint' => 'Complete checkout to place this request as an order.'],
            'converted' => ['label' => 'Order placed', 'tone' => 'success', 'hint' => 'This request is now an order.'],
            'declined' => ['label' => 'Seller declined', 'tone' => 'danger', 'hint' => 'The seller could not accept this request.'],
            'customer_declined' => ['label' => 'Declined by you', 'tone' => 'muted', 'hint' => 'You declined this offer.'],
            'expired' => ['label' => 'Expired', 'tone' => 'muted', 'hint' => 'This request is no longer active.'],
            'schedule_suggested' => ['label' => 'New schedule offered', 'tone' => 'warning', 'hint' => 'Seller suggested another date or time.'],
            'alternative_offered' => ['label' => 'Alternative offered', 'tone' => 'warning', 'hint' => 'Seller suggested a similar cake.'],
            'needs_more_details' => ['label' => 'Needs details', 'tone' => 'warning', 'hint' => 'Seller needs more information before accepting.'],
            default => ['label' => 'Waiting for seller', 'tone' => 'pending', 'hint' => 'The seller has not responded yet.'],
        };
    }

    private function dateTimeLabel($datetime, $date, $time): ?string
    {
        try {
            if ($datetime) {
                return Carbon::parse($datetime)->format('M d, Y g:i A');
            }
            if ($date && $time) {
                return Carbon::parse($date . ' ' . $time)->format('M d, Y g:i A');
            }
            if ($date) {
                return Carbon::parse($date)->format('M d, Y');
            }
        } catch (\Throwable $e) {
            return trim((string) $date . ' ' . (string) $time) ?: null;
        }
        return null;
    }
}
