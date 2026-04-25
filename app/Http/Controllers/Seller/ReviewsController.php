<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ReviewsController extends Controller
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
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->where('o.shop_id', $shop->id)
            ->select('r.*', 'o.id as order_id',
                DB::raw("COALESCE(u.fullname, o.guest_name, 'Customer') as reviewer_name"))
            ->orderByDesc('r.created_at')
            ->get();

        $avgRating    = $reviews->avg('rating') ?? 0;
        $totalReviews = $reviews->count();
        $starCounts   = [];
        for ($i = 1; $i <= 5; $i++) {
            $starCounts[$i] = $reviews->where('rating', $i)->count();
        }

        return view('seller.reviews', compact('shop','reviews','avgRating','totalReviews','starCounts'));
    }
}
