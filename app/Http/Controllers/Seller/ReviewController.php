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
        $shop = $this->getShop();
        $reviews = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.shop_id', $shop->id)
            ->select('r.*', 'p.name as product_name',
                DB::raw('COALESCE(u.fullname, r.guest_name, "Customer") as reviewer_name'),
                'u.profile_photo')
            ->orderByDesc('r.created_at')->paginate(20);

        $avgRating   = DB::table('order_reviews as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('o.shop_id', $shop->id)->avg('r.rating');
        $totalReviews = DB::table('order_reviews as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('o.shop_id', $shop->id)->count();

        $starCounts = DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('o.shop_id', $shop->id)
            ->selectRaw('r.rating, COUNT(*) as cnt')
            ->groupBy('r.rating')
            ->pluck('cnt', 'rating')
            ->toArray();

        return view('seller.reviews', compact('reviews', 'avgRating', 'totalReviews', 'shop', 'starCounts'));
    }
}
