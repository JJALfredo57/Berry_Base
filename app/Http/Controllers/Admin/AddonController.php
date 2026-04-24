<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddonController extends Controller
{
    public function index()
    {
        $categories = DB::table('cake_addon_categories')
            ->orderBy('sort_order')->orderBy('id')->get();

        $addons = DB::table('cake_addons as a')
            ->join('cake_addon_categories as c', 'c.id', '=', 'a.category_id')
            ->select('a.*', 'c.name as category_name')
            ->orderBy('a.category_id')
            ->orderBy('a.sort_order')
            ->get()
            ->groupBy('category_id');

        return view('admin.addons', compact('categories', 'addons'));
    }

    // ── CATEGORY ────────────────────────────────────────────────

    public function storeCategory(Request $request)
    {
        $name = trim($request->input('name', ''));
        $icon = trim($request->input('icon', 'bi-stars'));
        if (!$name) return back()->with('err', 'Category name is required.');

        // Duplicate check
        if (DB::table('cake_addon_categories')->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
            return back()->with('err', "Addon category '{$name}' already exists.");
        }

        $max = DB::table('cake_addon_categories')->max('sort_order') ?? 0;
        DB::table('cake_addon_categories')->insert([
            'name'       => $name,
            'icon'       => $icon,
            'sort_order' => $max + 1,
            'is_active'  => 1,
            'created_at' => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Add Addon Category', $name);
        return back()->with('msg', "Category '\1' added.");
    }

    public function updateCategory(Request $request, string $id)
    {
        $name = trim($request->input('name', ''));
        $icon = trim($request->input('icon', 'bi-stars'));
        if (!$name) return back()->with('err', 'Category name is required.');

        DB::table('cake_addon_categories')->where('id', $id)->update([
            'name' => $name,
            'icon' => $icon,
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Update Addon Category', $name);
        return back()->with('msg', "Category updated.");
    }

    public function toggleCategory(string $id)
    {
        $cat = DB::table('cake_addon_categories')->where('id', $id)->first();
        if (!$cat) return back()->with('err', 'Category not found.');
        $new = $cat->is_active ? 0 : 1;
        DB::table('cake_addon_categories')->where('id', $id)->update(['is_active' => $new]);
        return back()->with('msg', "Category " . ($new ? 'shown' : 'hidden') . ".");
    }

    public function destroyCategory(string $id)
    {
        $count = DB::table('cake_addons')->where('category_id', $id)->count();
        if ($count > 0) {
            return back()->with('err', "Cannot delete — category has {$count} add-on(s). Delete or move them first.");
        }
        DB::table('cake_addon_categories')->where('id', $id)->delete();
        return back()->with('msg', 'Category deleted.');
    }

    // ── ADDON ────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $catId = (int) $request->input('category_id');
        $name  = trim($request->input('name', ''));
        $desc  = trim($request->input('description', ''));
        $price = (float) $request->input('price', 0);

        // Duplicate check — same name within same category
        if ($catId && $name) {
            $exists = DB::table('cake_addons')
                ->where('category_id', $catId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists();
            if ($exists) return back()->with('err', "Add-on '{$name}' already exists in this category.");
        }

        if (!$catId || !$name) return back()->with('err', 'Category and name are required.');

        $max = DB::table('cake_addons')->where('category_id', $catId)->max('sort_order') ?? 0;
        DB::table('cake_addons')->insert([
            'category_id' => $catId,
            'name'        => $name,
            'description' => $desc ?: null,
            'price'       => $price,
            'is_active'   => 1,
            'sort_order'  => $max + 1,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Add Addon', $name);
        return back()->with('msg', "'\1' added successfully.");
    }

    public function update(Request $request, string $id)
    {
        $name  = trim($request->input('name', ''));
        $desc  = trim($request->input('description', ''));
        $price = (float) $request->input('price', 0);
        $catId = (int) $request->input('category_id');

        if (!$name) return back()->with('err', 'Name is required.');

        DB::table('cake_addons')->where('id', $id)->update([
            'category_id' => $catId,
            'name'        => $name,
            'description' => $desc ?: null,
            'price'       => $price,
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Update Addon', $name);
        return back()->with('msg', "'\1' updated.");
    }

    public function toggle(string $id)
    {
        $addon = DB::table('cake_addons')->where('id', $id)->first();
        if (!$addon) return back()->with('err', 'Add-on not found.');
        $new = $addon->is_active ? 0 : 1;
        DB::table('cake_addons')->where('id', $id)->update(['is_active' => $new]);
        return back()->with('msg', "'\1' " . ($new ? 'is now visible.' : 'is now hidden.'));
    }

    public function destroy(string $id)
    {
        $addon = DB::table('cake_addons')->where('id', $id)->first();
        if (!$addon) return back()->with('err', 'Add-on not found.');
        // Soft-hide instead of delete if it's used in orders
        $used = DB::table('order_addons')->where('addon_id', $id)->exists();
        if ($used) {
            DB::table('cake_addons')->where('id', $id)->update(['is_active' => 0]);
            return back()->with('msg', "'\1' hidden (cannot delete — used in existing orders).");
        }
        DB::table('cake_addons')->where('id', $id)->delete();
        return back()->with('msg', "'\1' deleted.");
    }
}
