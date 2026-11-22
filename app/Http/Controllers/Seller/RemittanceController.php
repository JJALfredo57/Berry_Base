<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SellerRemittanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemittanceController extends Controller
{
    public function __construct(private SellerRemittanceService $remittances) {}

    public function index(Request $request)
    {
        $shop = DB::table('shops')
            ->where('seller_id', session('user')['id'] ?? null)
            ->where('status', 'approved')
            ->first();
        if (! $shop) {
            abort(403, 'Shop not found or not approved.');
        }

        [$from, $to, $period] = $this->dateRange($request);
        $status = in_array($request->query('status', 'outstanding'), ['outstanding', 'waiting', 'submitted', 'confirmed', 'all'], true)
            ? (string) $request->query('status', 'outstanding')
            : 'outstanding';
        $sort = in_array($request->query('sort', 'newest'), ['newest', 'oldest', 'amount_high', 'amount_low'], true)
            ? (string) $request->query('sort', 'newest')
            : 'newest';
        $perPage = in_array((int) $request->query('per_page', 10), [10, 25, 50], true)
            ? (int) $request->query('per_page', 10)
            : 10;
        $filters = [
            'search' => mb_substr(trim((string) $request->query('search', '')), 0, 120),
            'status' => $status,
            'rider_id' => max(0, (int) $request->query('rider_id', 0)) ?: null,
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'sort' => $sort,
            'per_page' => $perPage,
        ];

        $filteredQuery = $this->remittances->applyFilters($this->remittances->query((string) $shop->id), $filters);
        $filteredTotal = $this->remittances->filteredTotal($filteredQuery);
        $filteredCount = (clone $filteredQuery)->count('rr.id');
        $rows = $this->remittances->sort($filteredQuery, $sort)
            ->paginate($perPage)
            ->withQueryString();
        $pageTotal = round((float) collect($rows->items())->sum('cake_amount_due'), 2);
        $summary = $this->remittances->summary((string) $shop->id);
        $riders = DB::table('rider_remittances as rr')
            ->join('riders as r', 'r.id', '=', 'rr.rider_id')
            ->where('rr.shop_id', $shop->id)
            ->select('r.id', 'r.name')
            ->distinct()
            ->orderBy('r.name')
            ->get();

        return view('seller.remittances', compact(
            'shop', 'rows', 'summary', 'filters', 'riders', 'filteredTotal', 'filteredCount', 'pageTotal'
        ));
    }

    private function dateRange(Request $request): array
    {
        $period = in_array($request->query('period', 'all'), ['all', 'today', 'week', 'month'], true)
            ? (string) $request->query('period', 'all')
            : 'all';
        $from = $this->date((string) $request->query('from', ''));
        $to = $this->date((string) $request->query('to', ''));

        if ($period === 'today') {
            $from = $to = now('Asia/Manila')->toDateString();
        } elseif ($period === 'week') {
            $from = now('Asia/Manila')->startOfWeek()->toDateString();
            $to = now('Asia/Manila')->endOfWeek()->toDateString();
        } elseif ($period === 'month') {
            $from = now('Asia/Manila')->startOfMonth()->toDateString();
            $to = now('Asia/Manila')->endOfMonth()->toDateString();
        }
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, $period];
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
