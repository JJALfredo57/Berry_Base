<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformController extends Controller
{
    public function home()
    {
        // Get approved shops with their stats
        try { DB::table('shops')->count(); } catch (\Exception $e) {
            // shops table not yet migrated — show empty state
            return view('platform.home', [
                'shops' => collect(), 'featuredProducts' => collect(),
                'stats' => ['shops'=>0,'products'=>0,'orders'=>0],
                'platform' => null,
            ]);
        }

        $shops = DB::table('shops as s')
            ->where('s.status', 'approved')
            ->leftJoin('users as u', 'u.id', '=', 's.seller_id')
            ->select(
                's.*',
                DB::raw('(SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.is_available = 1) as product_count'),
                DB::raw('(SELECT AVG(r.rating) FROM order_reviews r JOIN orders o ON o.id = r.order_id WHERE o.shop_id = s.id) as avg_rating'),
                DB::raw('(SELECT COUNT(*) FROM order_reviews r JOIN orders o ON o.id = r.order_id WHERE o.shop_id = s.id) as review_count')
            )
            ->orderByDesc('s.verified_at')
            ->get();

        // Featured products (newest, from approved shops)
        $featuredProducts = DB::table('products as p')
            ->join('shops as s', 's.id', '=', 'p.shop_id')
            ->where('s.status', 'approved')
            ->where('p.is_available', 1)
            ->select('p.*', 's.shop_name', 's.shop_slug', 's.tier')
            ->orderByDesc('p.created_at')
            ->limit(8)
            ->get();

        // Platform stats
        $stats = [
            'shops'    => DB::table('shops')->where('status', 'approved')->count(),
            'products' => DB::table('products as p')
                ->join('shops as s', 's.id', '=', 'p.shop_id')
                ->where('s.status', 'approved')->where('p.is_available', 1)->count(),
            'orders'   => DB::table('orders')->whereNotIn('status', ['Cancelled'])->count(),
        ];

        $platform = DB::table('platform_settings')->first();

        return view('platform.home', compact('shops', 'featuredProducts', 'stats', 'platform'));
    }

    public function shops(Request $request)
    {
        $search = trim($request->input('q', ''));
        $city   = trim($request->input('city', ''));
        $tier   = trim($request->input('tier', ''));

        $query = DB::table('shops as s')
            ->where('s.status', 'approved')
            ->select(
                's.*',
                DB::raw('(SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.is_available = 1) as product_count'),
                DB::raw('(SELECT AVG(r.rating) FROM order_reviews r JOIN orders o ON o.id = r.order_id WHERE o.shop_id = s.id) as avg_rating'),
                DB::raw('(SELECT COUNT(*) FROM order_reviews r JOIN orders o ON o.id = r.order_id WHERE o.shop_id = s.id) as review_count')
            );

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('s.shop_name', 'like', "%{$search}%")
                  ->orWhere('s.description', 'like', "%{$search}%")
                  ->orWhere('s.city', 'like', "%{$search}%");
            });
        }
        if ($city)  $query->where('s.city', 'like', "%{$city}%");
        if ($tier)  $query->where('s.tier', $tier);

        $shops = $query->orderByDesc('s.verified_at')->paginate(12);

        // Get unique cities for filter
        $cities = DB::table('shops')->where('status', 'approved')
            ->whereNotNull('city')->distinct()->pluck('city')->sort()->values();

        $platform = DB::table('platform_settings')->first();

        return view('platform.shops', compact('shops', 'cities', 'search', 'city', 'tier', 'platform'));
    }

    public function shopPage(string $slug)
    {
        $shop = DB::table('shops')->where('shop_slug', $slug)->where('status', 'approved')->first();
        if (!$shop) abort(404, 'Shop not found.');

        $products = DB::table('products')
            ->where('shop_id', $shop->id)
            ->where('is_available', 1)
            ->orderBy('classification')->orderBy('name')
            ->get();

        $productIds   = $products->pluck('id')->toArray();
        $productSizes = DB::table('product_sizes')
            ->whereIn('product_id', $productIds)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('product_id');

        $productRatings = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->whereIn('o.product_id', $productIds)
            ->selectRaw('o.product_id, AVG(r.rating) as avg_rating, COUNT(*) as review_count')
            ->groupBy('o.product_id')
            ->get()->keyBy('product_id');

        $productReviews = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->whereIn('o.product_id', $productIds)
            ->select('r.*', 'o.product_id',
                DB::raw("COALESCE(u.fullname, r.guest_name, 'Customer') as fullname"),
                'u.profile_photo')
            ->orderByDesc('r.created_at')
            ->get()->groupBy('product_id');

        $reviews = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('o.shop_id', $shop->id)
            ->select(
                'r.*',
                DB::raw("COALESCE(u.fullname, r.guest_name, 'Customer') as reviewer_name"),
                'u.profile_photo'
            )
            ->orderByDesc('r.created_at')
            ->limit(10)
            ->get();

        $avgRating    = $reviews->avg('rating');
        $reviewCount  = $reviews->count();

        $platform = DB::table('platform_settings')->first();

        return view('platform.shop', compact('shop', 'products', 'productSizes', 'productRatings', 'productReviews', 'reviews', 'avgRating', 'reviewCount', 'platform'));
    }
}
