<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VoucherController extends Controller
{
    private function context(): array
    {
        $route = request()->route()?->getName() ?? '';
        if (str_starts_with($route, 'seller.')) {
            $shop = DB::table('shops')
                ->where('seller_id', session('user')['id'] ?? '')
                ->where('status', 'approved')
                ->first();
            if (!$shop) abort(403, 'Shop not found.');
            return ['role' => 'seller', 'shop' => $shop, 'shop_id' => $shop->id, 'route_prefix' => 'seller'];
        }

        return ['role' => 'superadmin', 'shop' => null, 'shop_id' => null, 'route_prefix' => 'superadmin'];
    }

    public function index()
    {
        $ctx = $this->context();
        $vouchers = DB::table('vouchers as v')
            ->leftJoin('shops as s', 's.id', '=', 'v.shop_id')
            ->when($ctx['role'] === 'seller', fn ($q) => $q->where('v.shop_id', $ctx['shop_id']))
            ->when($ctx['role'] !== 'seller', fn ($q) => $q->whereNull('v.shop_id'))
            ->select('v.*', 's.shop_name')
            ->orderByDesc('v.id')
            ->paginate(20);

        $assignmentCounts = [];
        if (Schema::hasTable('voucher_assignments')) {
            $ids = collect($vouchers->items())->pluck('id')->all();
            $assignmentCounts = DB::table('voucher_assignments')
                ->whereIn('voucher_id', $ids)
                ->select('voucher_id', DB::raw('count(*) as total'))
                ->groupBy('voucher_id')
                ->pluck('total', 'voucher_id')
                ->all();
        }

        return view('admin.vouchers', compact('ctx', 'vouchers', 'assignmentCounts'));
    }

    public function store(Request $request)
    {
        $ctx = $this->context();
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_discount' => 'nullable|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'audience' => 'required|in:public,assigned',
            'customer_identifier' => 'nullable|string|max:120',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $code = strtoupper(preg_replace('/\s+/', '', $data['code']));
        if (DB::table('vouchers')->whereRaw('UPPER(code) = ?', [$code])->exists()) {
            return back()->with('err', 'Voucher code already exists.')->withInput();
        }

        $customer = null;
        if ($data['audience'] === 'assigned') {
            $identifier = trim((string) ($data['customer_identifier'] ?? ''));
            if ($identifier === '') return back()->with('err', 'Choose a customer for assigned vouchers.')->withInput();
            $customer = DB::table('users')
                ->where('role', 'customer')
                ->where(fn ($q) => $q->where('id', $identifier)->orWhere('email', $identifier)->orWhere('phone', $identifier)->orWhere('fullname', 'like', "%{$identifier}%"))
                ->orderByDesc('created_at')
                ->first();
            if (!$customer) return back()->with('err', 'Customer not found. Use exact email, phone, or customer ID.')->withInput();
        }

        $payload = [
            'shop_id' => $ctx['shop_id'],
            'code' => $code,
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'discount_type' => $data['discount_type'],
            'discount_value' => round((float) $data['discount_value'], 2),
            'max_discount' => $data['max_discount'] !== null ? round((float) $data['max_discount'], 2) : null,
            'minimum_order_amount' => round((float) ($data['minimum_order_amount'] ?? 0), 2),
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'first_order_only' => $request->boolean('first_order_only'),
            'requires_verified_customer' => $request->boolean('requires_verified_customer'),
            'stack_with_product_discount' => $request->boolean('stack_with_product_discount', true),
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('vouchers', 'audience')) $payload['audience'] = $data['audience'];
        if (Schema::hasColumn('vouchers', 'created_by_role')) $payload['created_by_role'] = $ctx['role'];
        if (Schema::hasColumn('vouchers', 'created_by_id')) $payload['created_by_id'] = session('user')['id'] ?? null;

        $voucherId = DB::table('vouchers')->insertGetId($payload);

        if ($customer && Schema::hasTable('voucher_assignments')) {
            DB::table('voucher_assignments')->insert([
                'voucher_id' => $voucherId,
                'user_id' => $customer->id,
                'assigned_by_role' => $ctx['role'],
                'assigned_by_id' => session('user')['id'] ?? null,
                'status' => 'active',
                'assigned_at' => now(),
                'expires_at' => $data['ends_at'] ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        CakeshopHelper::logActivity(session('user')['id'] ?? 'system', $ctx['role'], 'Create Voucher', "Created voucher {$code}");
        return back()->with('msg', 'Voucher created.');
    }

    public function toggle(string $id)
    {
        $ctx = $this->context();
        $query = DB::table('vouchers')->where('id', $id);
        $ctx['role'] === 'seller' ? $query->where('shop_id', $ctx['shop_id']) : $query->whereNull('shop_id');
        $voucher = $query->first();
        if (!$voucher) return back()->with('err', 'Voucher not found.');
        DB::table('vouchers')->where('id', $voucher->id)->update([
            'is_active' => !((bool) $voucher->is_active),
            'updated_at' => now(),
        ]);
        return back()->with('msg', 'Voucher status updated.');
    }
}
