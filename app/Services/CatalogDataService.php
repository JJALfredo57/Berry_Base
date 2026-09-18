<?php

namespace App\Services;

use App\Helpers\CakeshopHelper;
use Illuminate\Support\Facades\DB;

class CatalogDataService
{
    public function catalogData(): array
    {
        $bestSellerStats = $this->bestSellerStats();

        $products = DB::table('products')
            ->leftJoin('shops', 'shops.id', '=', 'products.shop_id')
            ->where(function ($query) {
                $query->where('products.is_available', true)
                    ->orWhere('products.available_quantity', 0);
            })
            ->where('products.classification', '!=', 'Custom')
            ->whereNull('products.archived_at')
            ->select('products.*', 'shops.shop_name', 'shops.shop_slug', 'shops.shop_logo')
            ->orderBy('products.classification')
            ->orderBy('products.name')
            ->get();

        [$zonesByShop, $barangayOptions] = $this->deliveryZones();
        $discountMap = CakeshopHelper::getActiveDiscountMap($products->pluck('id')->toArray());

        foreach ($products as $product) {
            $bestSellerKey = (string) $product->id . '|' . (string) ($product->shop_id ?? '');
            $bestSeller = $bestSellerStats[$bestSellerKey] ?? null;
            $shopZones = $zonesByShop->get((string) ($product->shop_id ?? ''), collect());

            $product->total_sold = (int) ($bestSeller->total_sold ?? 0);
            $product->total_orders = (int) ($bestSeller->total_orders ?? 0);
            $product->delivery_barangays = $shopZones->pluck('barangay')->filter()->unique()->sort()->values();
            $product->delivery_barangays_text = $product->delivery_barangays->implode(' ');
            $product->delivery_barangays_filter = '|' . $product->delivery_barangays->map(fn ($barangay) => strtolower(trim($barangay)))->implode('|') . '|';
            $product->active_discount = $discountMap[$product->id] ?? null;
            $product->discount_snapshot = CakeshopHelper::calculateDiscountSnapshot(
                (float) $product->price,
                $product->active_discount
            );
        }

        $bestSellers = $products
            ->filter(fn ($product) => (int) ($product->total_sold ?? 0) > 0)
            ->sortByDesc('total_sold')
            ->take(4)
            ->values();

        $productIds = $products->pluck('id')->toArray();
        $sizesMap = $this->sizesMap($productIds);
        $reviewsMap = $this->reviewsMap($productIds);
        $productReviews = $this->reviewPreviews($productIds);
        $capacityMap = $this->capacityMap($productIds);

        $productRatings = [];
        $productReviewCounts = [];
        foreach ($reviewsMap as $productId => $reviewSummary) {
            $productRatings[$productId] = $reviewSummary->avg_rating;
            $productReviewCounts[$productId] = $reviewSummary->total;
        }

        return [
            'products' => $products,
            'bestSellers' => $bestSellers,
            'sizesMap' => $sizesMap,
            'productSizes' => $sizesMap,
            'reviewsMap' => $reviewsMap,
            'productRatings' => $productRatings,
            'productReviews' => $productReviews,
            'productReviewCounts' => $productReviewCounts,
            'capacityMap' => $capacityMap,
            'barangayOptions' => $barangayOptions,
        ];
    }

    public function reviewsForProduct(string $productId, int $limit, int $offset = 0)
    {
        return DB::table('order_reviews as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->where('o.product_id', $productId)
            ->select(
                'o.product_id',
                'r.rating',
                'r.review',
                'r.image_path',
                'r.created_at',
                DB::raw("COALESCE(u.fullname, o.guest_name, 'Customer') as fullname"),
                'u.profile_photo'
            )
            ->orderByDesc('r.created_at')
            ->offset($offset)
            ->limit($limit);
    }

    private function bestSellerStats()
    {
        try {
            return DB::table('orders')
                ->select(
                    'product_id',
                    'shop_id',
                    DB::raw('COUNT(DISTINCT id) as total_sold'),
                    DB::raw('COUNT(*) as total_orders')
                )
                ->whereNotNull('product_id')
                ->whereNotNull('shop_id')
                ->whereNotIn('status', ['Cancelled', 'Rejected'])
                ->groupBy('product_id', 'shop_id')
                ->orderByDesc('total_sold')
                ->orderByDesc('total_orders')
                ->get()
                ->keyBy(fn ($row) => (string) $row->product_id . '|' . (string) $row->shop_id);
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function deliveryZones(): array
    {
        try {
            $deliveryZones = DB::table('delivery_zones')
                ->where('is_active', true)
                ->whereNotNull('shop_id')
                ->where('barangay', '<>', '')
                ->select('shop_id', 'barangay')
                ->orderBy('barangay')
                ->get();

            return [
                $deliveryZones->groupBy(fn ($zone) => (string) $zone->shop_id),
                $deliveryZones->pluck('barangay')->filter()->unique()->sort()->values(),
            ];
        } catch (\Exception $e) {
            return [collect(), collect()];
        }
    }

    private function sizesMap(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $sizesMap = [];
        try {
            $sizes = DB::table('product_sizes')
                ->whereIn('product_id', $productIds)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            foreach ($sizes as $size) {
                $sizesMap[$size->product_id][] = $size;
            }
        } catch (\Exception $e) {}

        return $sizesMap;
    }

    private function reviewsMap(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $reviewsMap = [];
        try {
            $reviews = DB::table('order_reviews as r')
                ->join('orders as o', 'o.id', '=', 'r.order_id')
                ->whereIn('o.product_id', $productIds)
                ->select('o.product_id', DB::raw('AVG(r.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                ->groupBy('o.product_id')
                ->get();
            foreach ($reviews as $review) {
                $reviewsMap[$review->product_id] = $review;
            }
        } catch (\Exception $e) {}

        return $reviewsMap;
    }

    private function reviewPreviews(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $productReviews = [];
        try {
            $previewReviews = DB::query()
                ->fromSub(function ($query) use ($productIds) {
                    $query->from('order_reviews as r')
                        ->join('orders as o', 'o.id', '=', 'r.order_id')
                        ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
                        ->whereIn('o.product_id', $productIds)
                        ->select(
                            'o.product_id',
                            'r.rating',
                            'r.review',
                            'r.image_path',
                            'r.created_at',
                            DB::raw("COALESCE(u.fullname, o.guest_name, 'Customer') as fullname"),
                            'u.profile_photo',
                            DB::raw('ROW_NUMBER() OVER (PARTITION BY o.product_id ORDER BY r.created_at DESC, r.id DESC) as review_rank')
                        );
                }, 'ranked_reviews')
                ->where('review_rank', '<=', 3)
                ->orderBy('product_id')
                ->orderBy('review_rank')
                ->get();

            foreach ($previewReviews as $review) {
                $productReviews[$review->product_id][] = $review;
            }
        } catch (\Exception $e) {}

        return $productReviews;
    }

    private function capacityMap(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $capacityMap = [];
        try {
            $dailyOrders = DB::table('product_daily_orders')
                ->whereIn('product_id', $productIds)
                ->where('date', '>=', date('Y-m-d'))
                ->get();
            foreach ($dailyOrders as $dailyOrder) {
                if (!isset($capacityMap[$dailyOrder->product_id])) {
                    $capacityMap[$dailyOrder->product_id] = [];
                }
                $capacityMap[$dailyOrder->product_id][$dailyOrder->date] = (int) $dailyOrder->total_ordered;
            }
        } catch (\Exception $e) {}

        return $capacityMap;
    }
}
