<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Services\CatalogDataService;
use App\Services\ProductStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function __construct(private CatalogDataService $catalogData)
    {
    }

    public function index()
    {
        $data = $this->catalogData->catalogData();

        $products = $data['products'];
        $bestSellers = $data['bestSellers'];
        $productSizes = $data['productSizes'];
        $reviewsMap = $data['reviewsMap'];
        $productRatings = $data['productRatings'];
        $productReviews = $data['productReviews'];
        $productReviewCounts = $data['productReviewCounts'];
        $capacityMap = $data['capacityMap'];
        $barangayOptions = $data['barangayOptions'];
        $shopSettings = \App\Helpers\CakeshopHelper::getSettings();
        $unclaimedReadyCakes = \Illuminate\Support\Facades\Schema::hasTable('unclaimed_cake_listings')
            ? DB::table('unclaimed_cake_listings as u')->join('products as p', 'p.id', '=', 'u.product_id')
                ->where('u.status', 'published')->where(function ($q) { $q->whereNull('u.safe_until')->orWhere('u.safe_until', '>', now()); })
                ->select('u.*', 'p.name as product_name')->latest('u.published_at')->get()
            : collect();
        $requestBakeSchedules = [];
        $preparation = app(\App\Services\PreparationWindowService::class);
        $fulfillment = app(\App\Services\FulfillmentScheduleService::class);
        foreach ($products->pluck('shop_id')->filter()->unique() as $shopId) {
            $settings = $fulfillment->settings($shopId);
            $prepSettings = $preparation->settings($shopId);
            $requestBakeSchedules[$shopId] = [
                'earliest_date' => $preparation->earliestDate($shopId, 'custom')->toDateString(),
                'prep_days' => (int) $prepSettings->custom_cake_prep_days,
                'shop_open_time' => substr((string) $settings->shop_open_time, 0, 5),
                'shop_close_time' => substr((string) $settings->shop_close_time, 0, 5),
                'required_minutes' => $fulfillment->requiredLeadMinutes($shopId, 'custom', 'Pickup'),
            ];
        }

        return view('customer.catalog', compact(
            'products','bestSellers','productSizes','reviewsMap','productRatings',
            'productReviews','productReviewCounts','shopSettings','capacityMap','barangayOptions','requestBakeSchedules','unclaimedReadyCakes'
        ));
    }

    public function order(Request $request)
    {
        $pid = $request->input('product_id');
        $qty = max(1, (int) $request->input('quantity', 1));
        $product = DB::table('products')->where('id', $pid)->where('is_available', true)->whereNull('archived_at')->first();
        if (!$product) return back()->with('error', 'Product not available.');

        $stock = app(ProductStockService::class)->validateProductQuantity($pid, $qty, trim($request->input('selected_size', '')));
        if (!$stock['ok']) return back()->with('error', $stock['message'])->withInput();

        $parts = [];
        if ($d = trim($request->input('dedication', '')))   $parts[] = 'Dedication: "' . $d . '"';
        if ($c = trim($request->input('color_theme', '')))  $parts[] = 'Color/Theme: ' . $c;
        if ($s = trim($request->input('special_note', ''))) $parts[] = 'Notes: ' . $s;
        if ($n = trim($request->input('custom_note', '')))  $parts[] = $n;

        $request->session()->put('checkout', [
            'customer_id' => (string) session('user')['id'],
            'product_id'    => $pid,
            'quantity'      => $qty,
            'custom_note'   => implode(' | ', $parts),
            'selected_size' => trim($request->input('selected_size', '')),
        ]);
        return redirect()->route('customer.checkout');
    }
}
