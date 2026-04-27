<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function index()
    {
        $products = DB::table('products')
            ->leftJoin('shops', 'shops.id', '=', 'products.shop_id')
            ->where('products.classification', '!=', 'Custom')
            ->select('products.*', 'shops.shop_name', 'shops.shop_slug', 'shops.shop_logo')
            ->orderByDesc('products.id')
            ->get();

        $discountMap = CakeshopHelper::getActiveDiscountMap($products->pluck('id')->toArray());
        foreach ($products as $product) {
            $product->active_discount = $discountMap[$product->id] ?? null;
            $product->discount_snapshot = CakeshopHelper::calculateDiscountSnapshot(
                (float) $product->price,
                $product->active_discount
            );
        }

        // Load sizes per product
        $productSizes = [];
        try {
            $sizes = DB::table('product_sizes')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get();
            foreach ($sizes as $s) {
                $productSizes[$s->product_id][] = $s;
            }
        } catch (\Exception $e) {}

        // Load average ratings per product
        $productRatings = [];
        try {
            $ratings = DB::table('order_reviews as r')
                ->join('orders as o', 'o.id', '=', 'r.order_id')
                ->select('o.product_id', DB::raw('AVG(r.rating) as avg_rating'), DB::raw('COUNT(*) as review_count'))
                ->groupBy('o.product_id')
                ->get();
            foreach ($ratings as $r) {
                $productRatings[$r->product_id] = $r->avg_rating;
            }
        } catch (\Exception $e) {}

        // Load reviews per product (with reviewer name and image)
        $productReviews = [];
        try {
            $reviews = DB::table('order_reviews as r')
                ->join('orders as o', 'o.id', '=', 'r.order_id')
                ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
                ->select('r.*', 'o.product_id', 'u.fullname', 'u.profile_photo', 'o.guest_name')
                ->orderByDesc('r.id')
                ->get();
            foreach ($reviews as $rv) {
                $productReviews[$rv->product_id][] = $rv;
            }
        } catch (\Exception $e) {}

        // Load review counts per product
        $productReviewCounts = [];
        try {
            $counts = DB::table('order_reviews as r')
                ->join('orders as o', 'o.id', '=', 'r.order_id')
                ->select('o.product_id', DB::raw('COUNT(*) as count'))
                ->groupBy('o.product_id')
                ->get();
            foreach ($counts as $cnt) {
                $productReviewCounts[$cnt->product_id] = $cnt->count;
            }
        } catch (\Exception $e) {}

        // Load shop settings
        $shopSettings = \App\Helpers\CakeshopHelper::getSettings();

        // Load daily capacity map
        $capacityMap = [];
        try {
            $pids = $products->pluck('id')->toArray();
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $dailyOrders = DB::table('product_daily_orders')
                ->whereIn('product_id', $pids)
                ->where('delivery_date', '>=', $tomorrow)
                ->get();
            foreach ($dailyOrders as $d) {
                if (!isset($capacityMap[$d->product_id])) $capacityMap[$d->product_id] = [];
                $capacityMap[$d->product_id][$d->delivery_date] = (int)$d->total_ordered;
            }
        } catch (\Exception $e) {}

        return view('customer.catalog', compact('products','productSizes','productRatings','productReviews','productReviewCounts','shopSettings','capacityMap'));
    }

    public function order(Request $request)
    {
        $parts = [];
        if ($d = trim($request->input('dedication', '')))   $parts[] = 'Dedication: "' . $d . '"';
        if ($c = trim($request->input('color_theme', '')))  $parts[] = 'Color/Theme: ' . $c;
        if ($s = trim($request->input('special_note', ''))) $parts[] = 'Notes: ' . $s;
        if ($n = trim($request->input('custom_note', '')))  $parts[] = $n;

        $request->session()->put('checkout', [
            'product_id'    => $request->input('product_id'),
            'quantity'      => max(1, (int) $request->input('quantity', 1)),
            'custom_note'   => implode(' | ', $parts),
            'selected_size' => trim($request->input('selected_size', '')),
        ]);
        return redirect()->route('customer.checkout');
    }
}
