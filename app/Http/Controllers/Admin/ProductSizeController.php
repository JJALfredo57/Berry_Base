<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSizeController extends Controller
{
    public function store(Request $request, string $productId)
    {
        $label = trim($request->input('label', ''));
        $price = (float) $request->input('price', 0);
        if (!$label || $price < 0) return back()->with('err', 'Label and price are required.');
        $max = DB::table('product_sizes')->where('product_id', $productId)->max('sort_order') ?? 0;
        DB::table('product_sizes')->insert([
            'product_id'  => $productId,
            'label'       => $label,
            'price'       => $price,
            'sort_order'  => $max + 1,
            'is_active' => true,
            'created_at'  => now(),
        ]);
        return back()->with('msg', "Size '\1' added.");
    }

    public function destroy(string $id)
    {
        DB::table('product_sizes')->where('id', $id)->delete();
        return back()->with('msg', 'Size deleted.');
    }
}
