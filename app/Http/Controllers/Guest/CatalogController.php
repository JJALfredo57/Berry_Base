<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function index()
    {
        $bestSellerStats = collect();
        try {
            $bestSellerStats = DB::table('orders')
                ->select(
                    'product_id',
                    DB::raw('SUM(quantity) as total_sold'),
                    DB::raw('COUNT(*) as total_orders')
                )
                ->whereNotNull('product_id')
                ->whereNotIn('status', ['Cancelled'])
                ->groupBy('product_id')
                ->orderByDesc('total_sold')
                ->orderByDesc('total_orders')
                ->limit(6)
                ->get()
                ->keyBy('product_id');
        } catch (\Exception $e) {}

        $products = DB::table('products')
            ->leftJoin('shops', 'shops.id', '=', 'products.shop_id')
            ->where('products.is_available', 1)
            ->select('products.*', 'shops.shop_name', 'shops.shop_slug', 'shops.shop_logo')
            ->orderBy('products.classification')
            ->orderBy('products.name')
            ->get();

        foreach ($products as $product) {
            $bestSeller = $bestSellerStats[$product->id] ?? null;
            $product->total_sold = (int)($bestSeller->total_sold ?? 0);
            $product->total_orders = (int)($bestSeller->total_orders ?? 0);
        }

        $bestSellers = $products
            ->filter(fn ($product) => (int)($product->total_sold ?? 0) > 0)
            ->sortByDesc('total_sold')
            ->take(4)
            ->values();

        $productIds = $products->pluck('id')->toArray();
        $sizesMap   = [];
        $reviewsMap = [];

        // Per-product individual reviews (with comments, photos, names)
        $productReviews = [];

        if ($productIds) {
            try {
                $sizes = DB::table('product_sizes')
                    ->whereIn('product_id', $productIds)
                    ->where('is_active', 1)
                    ->orderBy('sort_order')->get();
                foreach ($sizes as $s) $sizesMap[$s->product_id][] = $s;
            } catch (\Exception $e) {}

            try {
                $reviews = DB::table('order_reviews as r')
                    ->join('orders as o', 'o.id', '=', 'r.order_id')
                    ->whereIn('o.product_id', $productIds)
                    ->select('o.product_id', DB::raw('AVG(r.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                    ->groupBy('o.product_id')->get();
                foreach ($reviews as $r) $reviewsMap[$r->product_id] = $r;
            } catch (\Exception $e) {}

            // Load individual reviews with reviewer name + photo + comment
            try {
                $indivReviews = DB::table('order_reviews as r')
                    ->join('orders as o', 'o.id', '=', 'r.order_id')
                    ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
                    ->whereIn('o.product_id', $productIds)
                    ->select(
                        'o.product_id',
                        'r.rating', 'r.review', 'r.image_path', 'r.created_at',
                        DB::raw("COALESCE(u.fullname, o.guest_name, 'Customer') as fullname"),
                        'u.profile_photo'
                    )
                    ->orderByDesc('r.created_at')
                    ->get();
                foreach ($indivReviews as $rv) {
                    $productReviews[$rv->product_id][] = $rv;
                }
            } catch (\Exception $e) {}
        }

        $addonCategories = collect();
        $addonsByCategory = collect();
        try {
            $addonCategories = DB::table('cake_addon_categories')
                ->where('is_active', 1)->orderBy('sort_order')->get();
            $addonsByCategory = DB::table('cake_addons as a')
                ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
                ->where('a.is_active', 1)->where('c.is_active', 1)
                ->select('a.*', 'c.name as category_name', 'c.icon as category_icon')
                ->orderBy('a.category_id')->orderBy('a.sort_order')
                ->get()->groupBy('category_id');
        } catch (\Exception $e) {}

        // Load daily capacity (max_per_day) and total ordered per product per date
        $capacityMap = [];
        try {
            $today2 = date('Y-m-d');
            $dailyOrders = DB::table('product_daily_orders')
                ->whereIn('product_id', $productIds)
                ->where('delivery_date', '>=', $today2)
                ->get();
            foreach ($dailyOrders as $d) {
                if (!isset($capacityMap[$d->product_id])) $capacityMap[$d->product_id] = [];
                $capacityMap[$d->product_id][$d->delivery_date] = (int)$d->total_ordered;
            }
        } catch (\Exception $e) {}

        return view('guest.catalog', compact(
            'products','bestSellers','sizesMap','reviewsMap','productReviews','addonCategories','addonsByCategory','capacityMap'
        ));
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
        $note = trim($request->input('custom_note', ''));
        $size = trim($request->input('selected_size', ''));

        $product = DB::table('products')->where('id', $pid)->where('is_available', 1)->first();
        if (!$product) return back()->with('error', 'Product not available.');

        $request->session()->put('guest_checkout', [
            'product_id'   => $pid,
            'quantity'     => $qty,
            'custom_note'  => $note,
            'selected_size'=> $size,
        ]);

        return redirect()->route('guest.checkout');
    }
}
