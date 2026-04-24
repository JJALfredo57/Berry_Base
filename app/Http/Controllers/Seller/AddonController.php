<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddonController extends Controller
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
        $categories = DB::table('cake_addon_categories')
            ->where('shop_id', $shop->id)
            ->orderBy('sort_order')->orderBy('id')->get();
        $addons = DB::table('cake_addons as a')
            ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
            ->where('a.shop_id', $shop->id)
            ->select('a.*', 'c.name as category_name')
            ->orderBy('a.category_id')->orderBy('a.sort_order')->get()
            ->groupBy('category_id');
        return view('admin.addons', compact('categories', 'addons'));
    }

    public function storeCategory(Request $request)
    {
        $shop = $this->getShop();
        $name = trim($request->input('name', ''));
        $icon = trim($request->input('icon', 'bi-stars'));
        if (!$name) return back()->with('err', 'Category name is required.');
        if (DB::table('cake_addon_categories')->where('shop_id', $shop->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists())
            return back()->with('err', "Category '{$name}' already exists.");
        $max = DB::table('cake_addon_categories')->where('shop_id', $shop->id)->max('sort_order') ?? 0;
        DB::table('cake_addon_categories')->insert([
            'shop_id'    => $shop->id,
            'name'       => $name,
            'icon'       => $icon,
            'sort_order' => $max + 1,
            'is_active'  => 1,
            'created_at' => now(),
        ]);
        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Add Addon Category', $name);
        return back()->with('msg', "Category '{$name}' added.");
    }

    public function updateCategory(Request $request, string $id)
    {
        $shop = $this->getShop();
        $cat  = DB::table('cake_addon_categories')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$cat) return back()->with('err', 'Not found.');
        DB::table('cake_addon_categories')->where('id', $id)->update([
            'name' => trim($request->input('name', $cat->name)),
            'icon' => trim($request->input('icon', $cat->icon)),
        ]);
        return back()->with('msg', 'Category updated.');
    }

    public function toggleCategory(string $id)
    {
        $shop = $this->getShop();
        $cat  = DB::table('cake_addon_categories')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$cat) return back()->with('err', 'Not found.');
        DB::table('cake_addon_categories')->where('id', $id)->update(['is_active' => !$cat->is_active]);
        return back()->with('msg', 'Category ' . (!$cat->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroyCategory(string $id)
    {
        $shop = $this->getShop();
        $cat  = DB::table('cake_addon_categories')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$cat) return back()->with('err', 'Not found.');
        DB::table('cake_addons')->where('category_id', $id)->where('shop_id', $shop->id)->delete();
        DB::table('cake_addon_categories')->where('id', $id)->delete();
        return back()->with('msg', 'Category deleted.');
    }

    public function store(Request $request)
    {
        $shop  = $this->getShop();
        $catId = $request->input('category_id');
        $name  = trim($request->input('name', ''));
        $price = (float)$request->input('price', 0);
        if (!$catId || !$name) return back()->with('err', 'Category and name are required.');
        $cat = DB::table('cake_addon_categories')->where('id', $catId)->where('shop_id', $shop->id)->first();
        if (!$cat) return back()->with('err', 'Invalid category.');
        $max = DB::table('cake_addons')->where('category_id', $catId)->max('sort_order') ?? 0;
        DB::table('cake_addons')->insert([
            'category_id' => $catId,
            'shop_id'     => $shop->id,
            'name'        => $name,
            'price'       => $price,
            'sort_order'  => $max + 1,
            'is_active'   => 1,
            'created_at'  => now(),
        ]);
        return back()->with('msg', "Add-on '{$name}' added.");
    }

    public function update(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $addon = DB::table('cake_addons')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$addon) return back()->with('err', 'Not found.');
        DB::table('cake_addons')->where('id', $id)->update([
            'name'  => trim($request->input('name', $addon->name)),
            'price' => (float)$request->input('price', $addon->price),
        ]);
        return back()->with('msg', 'Add-on updated.');
    }

    public function toggle(string $id)
    {
        $shop  = $this->getShop();
        $addon = DB::table('cake_addons')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$addon) return back()->with('err', 'Not found.');
        DB::table('cake_addons')->where('id', $id)->update(['is_active' => !$addon->is_active]);
        return back()->with('msg', 'Add-on ' . (!$addon->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(string $id)
    {
        $shop  = $this->getShop();
        $addon = DB::table('cake_addons')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$addon) return back()->with('err', 'Not found.');
        DB::table('cake_addons')->where('id', $id)->delete();
        return back()->with('msg', 'Add-on deleted.');
    }
}
