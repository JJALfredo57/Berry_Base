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

        return view('customer.catalog', compact(
            'products','bestSellers','productSizes','reviewsMap','productRatings',
            'productReviews','productReviewCounts','shopSettings','capacityMap','barangayOptions'
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
            'product_id'    => $pid,
            'quantity'      => $qty,
            'custom_note'   => implode(' | ', $parts),
            'selected_size' => trim($request->input('selected_size', '')),
        ]);
        return redirect()->route('customer.checkout');
    }
}
