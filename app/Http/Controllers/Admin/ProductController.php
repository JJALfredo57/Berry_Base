<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private function saveUpload($file): string
    {
        if (!$file || !$file->isValid()) return '';
        if ($file->getSize() > 5 * 1024 * 1024) return '';
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) return '';
        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $path = $file->storeAs('uploads/products', $filename, 'public');
        if (!$path) return '';
        return '/storage/uploads/products/' . $filename;
    }

    public function index()
    {
        $products = DB::table('products')->orderByDesc('id')->get();

        // Load sizes per product for the admin card display
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

        // Convert to collections for blade compatibility
        foreach ($productSizes as $pid => $arr) {
            $productSizes[$pid] = collect($arr);
        }

        return view('admin.products', compact('products', 'productSizes'));
    }

    public function store(Request $request)
    {
        $user           = session('user');
        $name           = trim($request->input('name', ''));
        $desc           = trim($request->input('description', ''));
        $price          = (float)$request->input('price', 0);
        $classification = $request->input('classification', 'Standard');
        $flavor         = trim($request->input('flavor', ''));
        $maxPerDay      = max(0, (int)$request->input('max_per_day', 0));

        if (!$name || $price <= 0) return redirect()->route('admin.products.index')->with('err', 'Name and valid price are required.');

        // Duplicate check — same name + same classification
        $exists = DB::table('products')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where('classification', $classification)
            ->exists();
        if ($exists) return redirect()->route('admin.products.index')->with('err', 'A "'.$classification.'" product named "'.$name.'" already exists.');

        $img = '';
        if ($request->hasFile('image')) {
            $up = $this->saveUpload($request->file('image'));
            if ($up) $img = $up;
        }

        DB::table('products')->insert([
            'id'             => CakeshopHelper::generateId('products'),
            'name'           => $name,
            'description'    => $desc,
            'price'          => $price,
            'image_path'     => $img,
            'classification' => $classification,
            'flavor'         => $flavor ?: null,
            'max_per_day'    => $maxPerDay,
            'created_at'     => now(),
        ]);

        CakeshopHelper::logActivity($user['id'], $user['role'], 'Add Product', $name);
        return redirect()->route('admin.products.index')->with('msg', 'Product added successfully.');
    }

    public function edit(string $id)
    {
        $product = DB::table('products')->where('id', $id)->first();
        if (!$product) return redirect()->route('admin.products.index')->with('err', 'Product not found.');
        $products = DB::table('products')->orderByDesc('id')->get();

        $productSizes = [];
        try {
            $sizes = DB::table('product_sizes')->where('is_active', 1)->orderBy('sort_order')->get();
            foreach ($sizes as $s) {
                $productSizes[$s->product_id][] = $s;
            }
        } catch (\Exception $e) {}
        foreach ($productSizes as $pid => $arr) {
            $productSizes[$pid] = collect($arr);
        }

        return view('admin.products', compact('products','product','productSizes'));
    }

    public function update(Request $request, string $id)
    {
        $user           = session('user');
        $name           = trim($request->input('name', ''));
        $desc           = trim($request->input('description', ''));
        $price          = (float)$request->input('price', 0);
        $classification = $request->input('classification', 'Standard');
        $flavor         = trim($request->input('flavor', ''));
        $maxPerDay      = max(0, (int)$request->input('max_per_day', 0));

        // Duplicate check — exclude self
        $dupExists = DB::table('products')
            ->where('id', '!=', $id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where('classification', $classification)
            ->exists();
        if ($dupExists) return redirect()->route('admin.products.index')->with('err', 'Another "'.$classification.'" product named "'.$name.'" already exists.');

        $data = [
            'name'           => $name,
            'description'    => $desc,
            'price'          => $price,
            'classification' => $classification,
            'flavor'         => $flavor ?: null,
            'max_per_day'    => $maxPerDay,
        ];

        if ($request->hasFile('image')) {
            $up = $this->saveUpload($request->file('image'));
            if ($up) $data['image_path'] = $up;
        }

        DB::table('products')->where('id', $id)->update($data);
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Edit Product', $name);
        return redirect()->route('admin.products.index')->with('msg', 'Product updated.');
    }

    public function toggleAvailable(string $id)
    {
        $user    = session('user');
        $product = DB::table('products')->where('id', $id)->first();
        if (!$product) return redirect()->route('admin.products.index')->with('err', 'Product not found.');
        $new = $product->is_available ? 0 : 1;
        DB::table('products')->where('id', $id)->update(['is_available' => $new]);
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Toggle Product Availability', $product->name . ' → ' . ($new ? 'Available' : 'Not Available'));
        return redirect()->route('admin.products.index')->with('msg', $product->name . ' is now ' . ($new ? 'Available' : 'Not Available') . '.');
    }

    public function destroy(string $id)
    {
        $user = session('user');
        $name = DB::table('products')->where('id', $id)->value('name');

        // Check if product has existing orders before deleting
        $orderCount = DB::table('orders')->where('product_id', $id)->count();
        if ($orderCount > 0) {
            return redirect()->route('admin.products.index')
                ->with('err', "Cannot delete '{$name}' — it has {$orderCount} existing order(s) linked to it.");
        }

        DB::table('products')->where('id', $id)->delete();
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Delete Product', $name ?? 'ID:'.$id);
        return redirect()->route('admin.products.index')->with('msg', 'Product deleted.');
    }
}
