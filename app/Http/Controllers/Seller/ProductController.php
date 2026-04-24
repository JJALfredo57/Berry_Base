<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403, 'Shop not found or not approved.');
        return $shop;
    }

    private function saveUpload($file): string
    {
        if (!$file || !$file->isValid()) return '';
        if ($file->getSize() > 5 * 1024 * 1024) return '';
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) return '';
        $fn = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $file->storeAs('uploads/products', $fn, 'public');
        return '/storage/uploads/products/' . $fn;
    }

    public function index()
    {
        $shop     = $this->getShop();
        $platform = DB::table('platform_settings')->first();
        $maxProd  = $shop->tier === 'verified'
            ? null
            : (int)($platform->max_products_basic ?? 20);

        $products = DB::table('products')
            ->where('shop_id', $shop->id)
            ->orderByDesc('id')
            ->get();

        $productSizes = [];
        try {
            $sizes = DB::table('product_sizes')
                ->whereIn('product_id', $products->pluck('id')->toArray())
                ->where('is_active', 1)->orderBy('sort_order')->get();
            foreach ($sizes as $s) $productSizes[$s->product_id][] = $s;
        } catch (\Exception $e) {}

        return view('seller.products', compact('shop','products','productSizes','maxProd'));
    }

    public function store(Request $request)
    {
        $shop = $this->getShop();

        // Check product limit for basic sellers
        $platform = DB::table('platform_settings')->first();
        $maxProd  = $shop->tier === 'basic' ? (int)($platform->max_products_basic ?? 20) : null;
        if ($maxProd) {
            $count = DB::table('products')->where('shop_id', $shop->id)->count();
            if ($count >= $maxProd) {
                return back()->with('err', "Basic sellers can only have up to {$maxProd} products. Upgrade to Verified to add more.");
            }
        }

        $validated = $request->validate([
            'name'           => 'required|string|min:2|max:100',
            'price'          => 'required|numeric|min:1',
            'classification' => 'required|string|max:50',
            'description'    => 'nullable|string|max:500',
            'flavor'         => 'nullable|string|max:100',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ],[
            'name.required'           => 'Product name is required.',
            'price.required'          => 'Price is required.',
            'price.min'               => 'Price must be at least ₱1.',
            'classification.required' => 'Please select a classification.',
            'image.mimes'             => 'Image must be JPG, PNG, or WebP.',
            'image.max'               => 'Image must not exceed 5MB.',
        ]);

        // Duplicate check within shop
        $exists = DB::table('products')
            ->where('shop_id', $shop->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
            ->where('classification', $validated['classification'])
            ->exists();
        if ($exists) return back()->withInput()->with('err', "A \"{$validated['classification']}\" product named \"{$validated['name']}\" already exists in your shop.");

        $img = '';
        if ($request->hasFile('image')) {
            $img = $this->saveUpload($request->file('image'));
        }

        DB::table('products')->insert([
            'id'             => CakeshopHelper::generateId('products'),
            'shop_id'        => $shop->id,
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'price'          => $validated['price'],
            'image_path'     => $img,
            'classification' => $validated['classification'],
            'flavor'         => $validated['flavor'] ?? null,
            'is_available'   => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return back()->with('msg', "Product \"{$validated['name']}\" added successfully.");
    }

    public function update(Request $request, string $id)
    {
        $shop    = $this->getShop();
        $product = DB::table('products')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$product) return back()->with('err', 'Product not found.');

        $validated = $request->validate([
            'name'           => 'required|string|min:2|max:100',
            'price'          => 'required|numeric|min:1',
            'classification' => 'required|string|max:50',
            'description'    => 'nullable|string|max:500',
            'flavor'         => 'nullable|string|max:100',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ],[
            'name.required'  => 'Product name is required.',
            'price.required' => 'Price is required.',
            'price.min'      => 'Price must be at least ₱1.',
        ]);

        // Duplicate check (exclude current product)
        $exists = DB::table('products')
            ->where('shop_id', $shop->id)
            ->where('id', '!=', $id)
            ->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
            ->where('classification', $validated['classification'])
            ->exists();
        if ($exists) return back()->withInput()->with('err', "Another product with this name already exists.");

        $updates = [
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'price'          => $validated['price'],
            'classification' => $validated['classification'],
            'flavor'         => $validated['flavor'] ?? null,
            'updated_at'     => now(),
        ];

        if ($request->hasFile('image')) {
            $img = $this->saveUpload($request->file('image'));
            if ($img) $updates['image_path'] = $img;
        }

        DB::table('products')->where('id', $id)->update($updates);
        return back()->with('msg', "Product updated successfully.");
    }

    public function destroy(string $id)
    {
        $shop = $this->getShop();
        $p    = DB::table('products')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$p) return back()->with('err', 'Product not found.');

        // Check if product has existing orders
        $hasOrders = DB::table('orders')->where('product_id', $id)
            ->whereNotIn('status', ['Cancelled'])->exists();
        if ($hasOrders) {
            // Soft delete — just deactivate
            DB::table('products')->where('id', $id)->update(['is_available' => 0, 'updated_at' => now()]);
            return back()->with('msg', "Product deactivated (has existing orders — cannot permanently delete).");
        }

        DB::table('products')->where('id', $id)->delete();
        return back()->with('msg', "Product deleted.");
    }

    public function toggleAvailable(string $id)
    {
        $shop = $this->getShop();
        $p    = DB::table('products')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$p) return back()->with('err', 'Product not found.');

        DB::table('products')->where('id', $id)->update([
            'is_available' => $p->is_available ? 0 : 1,
            'updated_at'   => now(),
        ]);
        return back()->with('msg', "Product " . ($p->is_available ? 'hidden' : 'shown') . ".");
    }

    public function storeSize(Request $request, string $productId)
    {
        $shop = $this->getShop();
        $p    = DB::table('products')->where('id', $productId)->where('shop_id', $shop->id)->first();
        if (!$p) return back()->with('err', 'Product not found.');

        $validated = $request->validate([
            'label' => 'required|string|max:50',
            'price' => 'required|numeric|min:1',
        ],[
            'label.required' => 'Size label is required.',
            'price.required' => 'Size price is required.',
            'price.min'      => 'Price must be at least ₱1.',
        ]);

        // Duplicate size label check
        $exists = DB::table('product_sizes')
            ->where('product_id', $productId)
            ->whereRaw('LOWER(label) = ?', [strtolower($validated['label'])])
            ->exists();
        if ($exists) return back()->with('err', "A size \"{$validated['label']}\" already exists for this product.");

        $maxSort = DB::table('product_sizes')->where('product_id', $productId)->max('sort_order') ?? 0;
        DB::table('product_sizes')->insert([
            'product_id' => $productId,
            'label'      => $validated['label'],
            'price'      => $validated['price'],
            'is_active'  => 1,
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
        ]);
        return back()->with('msg', "Size added.");
    }

    public function destroySize(string $sizeId)
    {
        $shop = $this->getShop();
        $size = DB::table('product_sizes as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('ps.id', $sizeId)
            ->where('p.shop_id', $shop->id)
            ->first();
        if (!$size) return back()->with('err', 'Size not found.');
        DB::table('product_sizes')->where('id', $sizeId)->delete();
        return back()->with('msg', "Size removed.");
    }
}
