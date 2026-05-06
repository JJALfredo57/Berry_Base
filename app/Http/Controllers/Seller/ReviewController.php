<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403);
        return $shop;
    }

    public function index()
    {
        $shop   = $this->getShop();
        $filter = request('type', 'all'); // all | custom | regular

        $query = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('custom_orders as co', 'co.order_id', '=', 'o.id')
            ->where('o.shop_id', $shop->id)
            ->select(
                'r.*',
                'o.fulfillment_type',
                'o.status as order_status',
                'p.name as product_name',
                'p.classification as product_classification',
                'co.cake_name',
                'co.custom_note',
                DB::raw("COALESCE(u.fullname, r.guest_name, o.guest_name, 'Customer') as reviewer_name"),
                'u.profile_photo',
                DB::raw("CASE WHEN co.id IS NOT NULL THEN 1 ELSE 0 END as is_custom")
            );

        if ($filter === 'custom')  $query->whereNotNull('co.id');
        if ($filter === 'regular') $query->whereNull('co.id');

        $reviews = $query->orderByDesc('r.created_at')->paginate(15)->withQueryString();

        $allQuery = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('o.shop_id', $shop->id);

        $avgRating    = (clone $allQuery)->avg('r.rating');
        $totalReviews = (clone $allQuery)->count();
        $customCount  = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->join('custom_orders as co', 'co.order_id', '=', 'o.id')
            ->where('o.shop_id', $shop->id)->count();
        $regularCount = $totalReviews - $customCount;

        $starCounts = (clone $allQuery)
            ->selectRaw('r.rating, COUNT(*) as cnt')
            ->groupBy('r.rating')
            ->pluck('cnt', 'rating')
            ->toArray();

        return view('seller.reviews', compact(
            'reviews', 'avgRating', 'totalReviews',
            'shop', 'starCounts', 'filter',
            'customCount', 'regularCount'
        ));
    }
}
