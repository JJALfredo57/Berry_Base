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
        $availableQuantity = $request->input('available_quantity');
        $availableQuantity = $availableQuantity === null || $availableQuantity === '' ? null : max(0, min(9999, (int) $availableQuantity));

        if (!$label || $price < 0) return back()->with('err', 'Label and price are required.');

        $exists = DB::table('product_sizes')
            ->where('product_id', $productId)
            ->whereRaw('LOWER(label) = ?', [strtolower($label)])
            ->exists();
        if ($exists) return back()->with('err', "Size '{$label}' already exists for this product.");

        $max = DB::table('product_sizes')->where('product_id', $productId)->max('sort_order') ?? 0;
        DB::table('product_sizes')->insert([
            'product_id'  => $productId,
            'label'       => $label,
            'price'       => $price,
            'available_quantity' => $availableQuantity,
            'sort_order'  => $max + 1,
            'is_active' => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        return back()->with('msg', "Size '{$label}' added.");
    }

    public function updateStock(Request $request, string $id)
    {
        $size = DB::table('product_sizes')->where('id', $id)->first();
        if (!$size) return back()->with('err', 'Size not found.');

        $value = $request->input('available_quantity');
        $availableQuantity = $value === null || $value === '' ? null : max(0, min(9999, (int) $value));

        DB::table('product_sizes')->where('id', $id)->update([
            'available_quantity' => $availableQuantity,
            'updated_at' => now(),
        ]);

        return back()->with('msg', 'Size stock updated.');
    }

    public function destroy(string $id)
    {
        DB::table('product_sizes')->where('id', $id)->delete();
        return back()->with('msg', 'Size deleted.');
    }
}
