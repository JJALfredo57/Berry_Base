<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Services\CatalogDataService;
use App\Services\ProductStockService;
use App\Services\DailyCapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function __construct(private CatalogDataService $catalogData)
    {
    }

    public function index(?Request $request = null)
    {
        $data = $this->catalogData->catalogData();
        $products = $data['products'];
        $bestSellers = $data['bestSellers'];
        $sizesMap = $data['sizesMap'];
        $reviewsMap = $data['reviewsMap'];
        $productReviews = $data['productReviews'];
        $capacityMap = $data['capacityMap'];
        $barangayOptions = $data['barangayOptions'];

        $addonCategories = collect();
        $addonsByCategory = collect();
        try {
            $addonCategories = DB::table('cake_addon_categories')
                ->where('is_active', true)->orderBy('sort_order')->get();
            $addonsByCategory = DB::table('cake_addons as a')
                ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
                ->where('a.is_active', true)->where('c.is_active', true)
                ->select('a.*', 'c.name as category_name', 'c.icon as category_icon')
                ->orderBy('a.category_id')->orderBy('a.sort_order')
                ->get()->groupBy('category_id');
        } catch (\Exception $e) {}

        return view('guest.catalog', compact(
            'products','bestSellers','sizesMap','reviewsMap','productReviews','addonCategories','addonsByCategory','capacityMap','barangayOptions'
        ));
    }

    public function reviews(Request $request, string $productId)
    {
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = min(8, max(1, (int) $request->query('limit', 5)));

        $product = DB::table('products')
            ->where('id', $productId)
            ->where('is_available', true)
            ->whereNull('archived_at')
            ->first();

        if (!$product) {
            return response()->json(['ok' => false, 'message' => 'Product not found.'], 404);
        }

        $rows = $this->catalogData->reviewsForProduct($productId, $limit + 1, $offset)->get();
        $hasMore = $rows->count() > $limit;
        $reviews = $rows->take($limit)->map(fn ($review) => [
            'rating' => (int) $review->rating,
            'review' => (string) ($review->review ?? ''),
            'image_path' => $review->image_path,
            'created_at' => \Carbon\Carbon::parse($review->created_at)->diffForHumans(),
            'fullname' => $review->fullname,
            'initial' => strtoupper(substr((string) $review->fullname, 0, 1)) ?: 'C',
            'profile_photo' => $review->profile_photo,
        ])->values();

        return response()->json([
            'ok' => true,
            'reviews' => $reviews,
            'next_offset' => $offset + $reviews->count(),
            'has_more' => $hasMore,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function checkAvailability(Request $request)
    {
        $date = $request->input('date'); // Y-m-d

        if (!$date) {
            return response()->json(['error' => 'Missing date.'], 400);
        }

        // Block past dates only — today is allowed
        $today = date('Y-m-d');
        if ($date < $today) {
            return response()->json(['status' => 'invalid', 'message' => 'Cannot select a past date.']);
        }

        // Resolve shop_id from direct param or product_id
        $shopId = $request->input('shop_id');
        if (!$shopId && $request->input('product_id')) {
            $prod   = DB::table('products')->where('id', $request->input('product_id'))->value('shop_id');
            $shopId = $prod ?: null;
        }

        return response()->json(app(DailyCapacityService::class)->snapshot($shopId ?: null, $date));

        // Get shop-specific settings first, then global
        $settings = null;
        if ($shopId) {
            $settings = DB::table('site_settings')->where('shop_id', $shopId)->first();
        }
        if (!$settings) {
            $settings = DB::table('site_settings')->whereNull('shop_id')->first()
                      ?? DB::table('site_settings')->first();
        }

        $dailyMax = (int)($settings->daily_max_cakes ?? 0);

        // 0 = unlimited
        if ($dailyMax === 0) {
            return response()->json(['status' => 'available', 'max' => 0, 'ordered' => 0, 'remaining' => null, 'message' => 'Available']);
        }

        // Calculate lead time in days
        $today    = date('Y-m-d');
        $leadDays = (int)floor((strtotime($date) - strtotime($today)) / 86400); // 0 = today, 1 = tomorrow, etc.

        // Determine effective max based on lead time
        $effectiveMax = $dailyMax;
        if ($leadDays === 1 && ($settings->lead_1day_max ?? 0) > 0) {
            $effectiveMax = (int)$settings->lead_1day_max;
        } elseif ($leadDays === 2 && ($settings->lead_2day_max ?? 0) > 0) {
            $effectiveMax = (int)$settings->lead_2day_max;
        } elseif ($leadDays >= 3 && ($settings->lead_3day_plus_max ?? 0) > 0) {
            $effectiveMax = (int)$settings->lead_3day_plus_max;
        }

        // Count total pcs ordered for that date (filtered by shop if known)
        $ordersQuery = DB::table('orders')
            ->where('schedule_date', $date)
            ->whereNotIn('status', ['Cancelled']);
        if ($shopId) $ordersQuery->where('shop_id', $shopId);
        $totalOrdered = (int) $ordersQuery->sum('quantity');

        // Add custom orders
        try {
            $customQuery = DB::table('custom_orders')
                ->where('schedule_date', $date)
                ->whereNotIn('status', ['Rejected', 'Cancelled']);
            if ($shopId) $customQuery->where('shop_id', $shopId);
            $totalOrdered += (int) $customQuery->sum('quantity');
        } catch (\Exception $e) {}

        $remaining = max(0, $effectiveMax - $totalOrdered);
        $pct       = $effectiveMax > 0 ? ($totalOrdered / $effectiveMax) : 0;

        if ($remaining === 0) {
            $status  = 'full';
            $message = "Fully booked on this date ({$totalOrdered}/{$effectiveMax} pcs)";
        } elseif ($pct >= 0.8) {
            $status  = 'almost';
            $message = "Almost full — only {$remaining} of {$effectiveMax} pcs left!";
        } else {
            $status  = 'available';
            $message = "{$remaining} of {$effectiveMax} pcs available";
        }

        return response()->json([
            'status'       => $status,
            'max'          => $effectiveMax,
            'ordered'      => $totalOrdered,
            'remaining'    => $remaining,
            'lead_days'    => $leadDays,
            'message'      => $message,
        ]);
    }

        public function selectProduct(Request $request)
    {
        $pid  = $request->input('product_id');
        $qty  = max(1, (int)$request->input('quantity', 1));
        $size = trim($request->input('selected_size', ''));

        $parts = [];
        if ($d = trim($request->input('dedication', '')))   $parts[] = 'Dedication: "' . $d . '"';
        if ($c = trim($request->input('color_theme', '')))  $parts[] = 'Color/Theme: ' . $c;
        if ($s = trim($request->input('special_note', ''))) $parts[] = 'Notes: ' . $s;
        if ($n = trim($request->input('custom_note', '')))  $parts[] = $n;
        $note = implode(' | ', $parts);

        $product = DB::table('products')->where('id', $pid)->where('is_available', true)->whereNull('archived_at')->first();
        if (!$product) return back()->with('error', 'Product not available.');

        $stock = app(ProductStockService::class)->validateProductQuantity($pid, $qty);
        if (!$stock['ok']) return back()->with('error', $stock['message'])->withInput();

        $request->session()->put('guest_checkout', [
            'product_id'   => $pid,
            'quantity'     => $qty,
            'custom_note'  => $note,
            'selected_size'=> $size,
        ]);

        return redirect()->route('guest.checkout');
    }
}
