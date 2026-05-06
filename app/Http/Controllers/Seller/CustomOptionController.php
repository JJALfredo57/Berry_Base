<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomOptionController extends Controller
{
    // All option types with their display labels
    const TYPES = [
        'flavor'    => ['label' => 'Flavors',            'icon' => 'bi-droplet', 'has_price' => false],
        'size'      => ['label' => 'Sizes (Diameter)',   'icon' => 'bi-rulers',  'has_price' => true],
        'layer'     => ['label' => 'Number of Layers',   'icon' => 'bi-layers',  'has_price' => true],
        'time_slot' => ['label' => 'Delivery Time Slots','icon' => 'bi-clock',   'has_price' => false],
    ];

    private function getShopId(): ?string
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        return $shop?->id;
    }

    public function index()
    {
        $shopId = $this->getShopId();

        $allOptions = DB::table('custom_order_options')
            ->where('shop_id', $shopId)
            ->whereNull('archived_at')
            ->orderBy('type')->orderBy('sort_order')->orderBy('id')
            ->get()->groupBy('type');

        $archivedOptions = DB::table('custom_order_options')
            ->where('shop_id', $shopId)
            ->whereNotNull('archived_at')
            ->orderBy('type')->orderByDesc('archived_at')
            ->get()->groupBy('type');

        $types = self::TYPES;

        return view('seller.custom_options', compact('allOptions', 'archivedOptions', 'types'));
    }

    public function store(Request $request)
    {
        $shopId = $this->getShopId();
        $type   = $request->input('type');
        $label  = trim($request->input('label', ''));
        $price  = (float) $request->input('price', 0);
        $desc   = trim($request->input('description', ''));

        if (!$label || !array_key_exists($type, self::TYPES)) {
            return back()->with('err', 'Invalid option type or empty label.');
        }

        // Duplicate check — same label within same type and shop
        $exists = DB::table('custom_order_options')
            ->where('type', $type)
            ->where('shop_id', $shopId)
            ->whereRaw('LOWER(label) = ?', [strtolower($label)])
            ->exists();
        if ($exists) {
            $typeName = self::TYPES[$type]['label'] ?? $type;
            return back()->with('err', "A \"{$typeName}\" option labeled \"{$label}\" already exists.");
        }

        $max = DB::table('custom_order_options')
            ->where('type', $type)
            ->where('shop_id', $shopId)
            ->max('sort_order') ?? 0;

        DB::table('custom_order_options')->insert([
            'shop_id'     => $shopId,
            'type'        => $type,
            'label'       => $label,
            'price'       => $price,
            'description' => $desc ?: null,
            'sort_order'  => $max + 1,
            'is_active'   => true,
            'created_at'  => now(),
        ]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Add Custom Option', "{$type}: {$label}");

        return back()->with('msg', "Option '{$label}' added successfully.");
    }

    public function update(Request $request, string $id)
    {
        $label = trim($request->input('label', ''));
        $price = (float) $request->input('price', 0);
        $desc  = trim($request->input('description', ''));

        if (!$label) return back()->with('err', 'Label cannot be empty.');

        DB::table('custom_order_options')->where('id', $id)->update([
            'label'       => $label,
            'price'       => $price,
            'description' => $desc ?: null,
        ]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Edit Custom Option', $label);

        return back()->with('msg', "Option updated.");
    }

    public function toggle(string $id)
    {
        $opt = DB::table('custom_order_options')->where('id', $id)->first();
        if (!$opt) return back()->with('err', 'Option not found.');

        DB::table('custom_order_options')->where('id', $id)
            ->update(['is_active' => !$opt->is_active]);

        return back()->with('msg', "Option " . ($opt->is_active ? 'hidden' : 'shown') . ".");
    }

    public function archive(string $id)
    {
        $shopId = $this->getShopId();
        $opt    = DB::table('custom_order_options')->where('id', $id)->where('shop_id', $shopId)->first();
        if (!$opt) return back()->with('err', 'Option not found.');
        DB::table('custom_order_options')->where('id', $id)->update(['archived_at' => now()]);
        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Archive Custom Option', $opt->label);
        return back()->with('msg', "Option '{$opt->label}' archived.");
    }

    public function restore(string $id)
    {
        $shopId = $this->getShopId();
        $opt    = DB::table('custom_order_options')->where('id', $id)->where('shop_id', $shopId)->first();
        if (!$opt) return back()->with('err', 'Option not found.');
        DB::table('custom_order_options')->where('id', $id)->update(['archived_at' => null]);
        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Restore Custom Option', $opt->label);
        return back()->with('msg', "Option '{$opt->label}' restored.");
    }

    public function sortUp(string $id)
    {
        $shopId = $this->getShopId();
        $opt    = DB::table('custom_order_options')->where('id', $id)->where('shop_id', $shopId)->first();
        if (!$opt) return back();

        $prev = DB::table('custom_order_options')
            ->where('type', $opt->type)->where('shop_id', $shopId)
            ->where('sort_order', '<', $opt->sort_order)
            ->orderByDesc('sort_order')->first();

        if ($prev) {
            DB::table('custom_order_options')->where('id', $opt->id)->update(['sort_order' => $prev->sort_order]);
            DB::table('custom_order_options')->where('id', $prev->id)->update(['sort_order' => $opt->sort_order]);
        }

        return back();
    }

    public function sortDown(string $id)
    {
        $shopId = $this->getShopId();
        $opt    = DB::table('custom_order_options')->where('id', $id)->where('shop_id', $shopId)->first();
        if (!$opt) return back();

        $next = DB::table('custom_order_options')
            ->where('type', $opt->type)->where('shop_id', $shopId)
            ->where('sort_order', '>', $opt->sort_order)
            ->orderBy('sort_order')->first();

        if ($next) {
            DB::table('custom_order_options')->where('id', $opt->id)->update(['sort_order' => $next->sort_order]);
            DB::table('custom_order_options')->where('id', $next->id)->update(['sort_order' => $opt->sort_order]);
        }

        return back();
    }
}
