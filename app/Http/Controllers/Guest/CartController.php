<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\ProductStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request, CartService $cartService)
    {
        return view('guest.cart', $cartService->items($request, null));
    }

    public function store(Request $request, CartService $cartService)
    {
        $parts = [];
        if ($d = trim($request->input('dedication', ''))) $parts[] = 'Dedication: "' . $d . '"';
        if ($c = trim($request->input('color_theme', ''))) $parts[] = 'Color/Theme: ' . $c;
        if ($s = trim($request->input('special_note', ''))) $parts[] = 'Notes: ' . $s;
        if ($n = trim($request->input('custom_note', ''))) $parts[] = $n;

        $result = $cartService->add(
            $request,
            null,
            (string) $request->input('product_id'),
            (int) $request->input('quantity', 1),
            $request->input('selected_size'),
            implode(' | ', $parts)
        );

        if ($result['ok'] && !$request->boolean('stay_on_catalog')) {
            return redirect()->route('cart')->with('msg', $result['message']);
        }

        return back()->with($result['ok'] ? 'msg' : 'error', $result['message']);
    }

    public function update(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return back()->with('error', 'Cart not found.');
        $item = DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->first();
        if (!$item) return back()->with('error', 'Cart item not found.');
        $qty = max(1, min(99, (int) $request->input('quantity', 1)));
        $stock = app(ProductStockService::class)->validateProductQuantity((string) $item->product_id, $qty);
        if (!$stock['ok']) return back()->with('error', $stock['message']);

        DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->update([
            'quantity' => $qty,
            'updated_at' => now(),
        ]);
        return back()->with('msg', 'Cart updated.');
    }

    public function destroy(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if ($cart) {
            DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->delete();
            $cartService->removeEmptyShop((int) $cart->id);
        }
        return back()->with('msg', 'Item removed from cart.');
    }

    public function checkoutItem(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return redirect()->route('cart')->with('error', 'Cart not found.');
        $item = DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->first();
        if (!$item) return redirect()->route('cart')->with('error', 'Cart item not found.');

        $request->session()->put('guest_checkout', [
            'cart_id' => $cart->id,
            'cart_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => max(1, (int) $item->quantity),
            'custom_note' => $item->custom_note ?? '',
            'selected_size' => $item->selected_size ?? '',
        ]);

        return redirect()->route('guest.checkout');
    }

    public function checkoutShop(Request $request, string $shopId, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return redirect()->route('cart')->with('error', 'Cart not found.');

        $shopKey = $shopId === 'platform' ? null : $shopId;
        $selectedIds = collect($request->input('selected_item_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Please select at least one cake to checkout.');
        }

        $items = DB::table('customer_cart_items')
            ->where('cart_id', $cart->id)
            ->when($shopKey, fn ($q) => $q->where('shop_id', $shopKey), fn ($q) => $q->whereNull('shop_id'))
            ->whereIn('id', $selectedIds->all())
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart')->with('error', 'No selected cart items found for that seller.');
        }

        if ($items->count() !== $selectedIds->count()) {
            return redirect()->route('cart')->with('error', 'Some selected cakes are no longer available in this seller cart. Please review your cart.');
        }

        foreach ($items as $item) {
            $stock = app(ProductStockService::class)->validateProductQuantity((string) $item->product_id, max(1, (int) $item->quantity));
            if (!$stock['ok']) {
                return redirect()->route('cart')->with('error', $stock['message']);
            }
        }

        $first = $items->first();
        $request->session()->put('guest_checkout', [
            'cart_id' => $cart->id,
            'cart_shop_id' => $shopKey,
            'cart_item_ids' => $items->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'product_id' => $first->product_id,
            'quantity' => (int) $items->sum('quantity'),
            'custom_note' => '',
            'selected_size' => '',
            'is_group' => true,
        ]);

        return redirect()->route('guest.checkout');
    }
}
