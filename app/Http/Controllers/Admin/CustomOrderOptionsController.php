<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomOrderOptionsController extends Controller
{
    private array $types = [
        'flavor'     => ['label' => 'Flavors',           'icon' => 'bi-droplet',    'has_price' => false, 'has_desc' => false],
        'size'       => ['label' => 'Sizes / Diameter',  'icon' => 'bi-rulers',     'has_price' => true,  'has_desc' => true],
        'layer'      => ['label' => 'Number of Layers',  'icon' => 'bi-layers',     'has_price' => false, 'has_desc' => false],
        'complexity' => ['label' => 'Design Complexity', 'icon' => 'bi-magic',      'has_price' => true,  'has_desc' => true],
        'time_slot'  => ['label' => 'Time Slots',        'icon' => 'bi-clock',      'has_price' => false, 'has_desc' => false],
    ];

    public function index()
    {
        $options = DB::table('custom_order_options')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('type');

        return view('admin.custom_order_options', [
            'optionsByType' => $options,
            'types'         => $this->types,
        ]);
    }

    public function store(Request $request)
    {
        $type  = $request->input('type');
        $label = trim($request->input('label', ''));
        $price = (float) $request->input('price', 0);
        $desc  = trim($request->input('description', ''));

        if (!array_key_exists($type, $this->types)) {
            return back()->with('err', 'Invalid option type.');
        }
        if (!$label) {
            return back()->with('err', 'Label is required.');
        }

        $max = DB::table('custom_order_options')
            ->where('type', $type)
            ->max('sort_order') ?? 0;

        DB::table('custom_order_options')->insert([
            'type'        => $type,
            'label'       => $label,
            'price'       => $price,
            'description' => $desc ?: null,
            'sort_order'  => $max + 1,
            'is_active'   => 1,
            'created_at'  => now(),
        ]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Custom Option Add', "{$type}: {$label}");

        return back()->with('msg', "Option '\1' added successfully.");
    }

    public function update(Request $request, string $id)
    {
        $label = trim($request->input('label', ''));
        $price = (float) $request->input('price', 0);
        $desc  = trim($request->input('description', ''));

        if (!$label) {
            return back()->with('err', 'Label is required.');
        }

        DB::table('custom_order_options')->where('id', $id)->update([
            'label'       => $label,
            'price'       => $price,
            'description' => $desc ?: null,
        ]);

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Custom Option Edit', "ID:{$id} → {$label}");

        return back()->with('msg', "Option updated successfully.");
    }

    public function toggle(string $id)
    {
        $opt = DB::table('custom_order_options')->where('id', $id)->first();
        if (!$opt) return back()->with('err', 'Option not found.');

        DB::table('custom_order_options')->where('id', $id)->update([
            'is_active' => $opt->is_active ? 0 : 1,
        ]);

        return back()->with('msg', $opt->is_active ? 'Option hidden.' : 'Option shown.');
    }

    public function destroy(string $id)
    {
        $opt = DB::table('custom_order_options')->where('id', $id)->first();
        DB::table('custom_order_options')->where('id', $id)->delete();

        $user = session('user');
        CakeshopHelper::logActivity($user['id'], $user['role'], 'Custom Option Delete', "ID:{$id}");

        return back()->with('msg', 'Option deleted.');
    }

    public function sortUp(string $id)
    {
        $this->swap($id, 'up');
        return back();
    }

    public function sortDown(string $id)
    {
        $this->swap($id, 'down');
        return back();
    }

    private function swap(string $id, string $dir): void
    {
        $opt = DB::table('custom_order_options')->where('id', $id)->first();
        if (!$opt) return;

        $sibling = DB::table('custom_order_options')
            ->where('type', $opt->type)
            ->where('sort_order', $dir === 'up' ? '<' : '>', $opt->sort_order)
            ->orderBy('sort_order', $dir === 'up' ? 'desc' : 'asc')
            ->first();

        if (!$sibling) return;

        DB::table('custom_order_options')->where('id', $id)->update(['sort_order' => $sibling->sort_order]);
        DB::table('custom_order_options')->where('id', $sibling->id)->update(['sort_order' => $opt->sort_order]);
    }
}
