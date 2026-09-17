<?php

namespace App\Services;

use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CartService
{
    public function cart(Request $request, ?string $userId = null): ?object
    {
        if (!Schema::hasTable('customer_carts')) return null;

        $sessionId = $request->session()->getId();
        $query = DB::table('customer_carts')->where('status', 'active');
        $userId ? $query->where('user_id', $userId) : $query->where('session_id', $sessionId)->whereNull('user_id');

        $cart = $query->orderByDesc('id')->first();
        if ($cart) return $cart;

        $id = DB::table('customer_carts')->insertGetId([
            'user_id' => $userId,
            'session_id' => $userId ? null : $sessionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('customer_carts')->where('id', $id)->first();
    }

    public function count(Request $request, ?string $userId = null): int
    {
        $cart = $this->cart($request, $userId);
        if (!$cart) return 0;

        return (int) DB::table('customer_cart_items')
            ->where('cart_id', $cart->id)
            ->sum('quantity');
    }

    public function items(Request $request, ?string $userId = null): array
    {
        $cart = $this->cart($request, $userId);
        if (!$cart) return ['cart' => null, 'items' => collect(), 'subtotal' => 0.0];

        $items = DB::table('customer_cart_items as ci')
            ->join('products as p', 'p.id', '=', 'ci.product_id')
            ->leftJoin('shops as s', 's.id', '=', 'ci.shop_id')
            ->where('ci.cart_id', $cart->id)
            ->where('p.is_available', true)
            ->whereNull('p.archived_at')
            ->select('ci.*', 'p.name as product_name', 'p.image_path', 'p.price as product_price', 's.name as shop_name')
            ->orderByDesc('ci.id')
            ->get();

        $subtotal = $items->sum(fn ($item) => (float) $item->final_unit_price_snapshot * (int) $item->quantity);

        return ['cart' => $cart, 'items' => $items, 'subtotal' => round($subtotal, 2)];
    }

    public function add(Request $request, ?string $userId, string $productId, int $quantity, ?string $selectedSize, ?string $customNote): array
    {
        $product = DB::table('products')->where('id', $productId)->where('is_available', true)->whereNull('archived_at')->first();
        if (!$product) return ['ok' => false, 'message' => 'Product not available.'];

        $cart = $this->cart($request, $userId);
        if (!$cart) return ['ok' => false, 'message' => 'Cart is not ready yet.'];

        if (!empty($cart->shop_id) && !empty($product->shop_id) && $cart->shop_id !== $product->shop_id) {
            return ['ok' => false, 'message' => 'Your cart currently has items from another seller. Please checkout or clear it first.'];
        }

        $selectedSize = trim((string) $selectedSize);
        $customNote = trim((string) $customNote);
        $unit = CakeshopHelper::resolveProductUnitPrice($product->id, (float) $product->price, $selectedSize);
        $discount = CakeshopHelper::getActiveProductDiscount($product->id);
        $pricing = CakeshopHelper::calculateDiscountSnapshot($unit, $discount);

        $existing = DB::table('customer_cart_items')
            ->where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->where('selected_size', $selectedSize ?: null)
            ->where('custom_note', $customNote ?: null)
            ->first();

        if ($existing) {
            DB::table('customer_cart_items')->where('id', $existing->id)->update([
                'quantity' => min(99, (int) $existing->quantity + max(1, $quantity)),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('customer_cart_items')->insert([
                'cart_id' => $cart->id,
                'shop_id' => $product->shop_id ?? null,
                'product_id' => $productId,
                'quantity' => max(1, min(99, $quantity)),
                'selected_size' => $selectedSize ?: null,
                'unit_price_snapshot' => $pricing['original_unit_price'],
                'final_unit_price_snapshot' => $pricing['final_unit_price'],
                'discount_amount_snapshot' => $pricing['discount_amount'],
                'discount_label_snapshot' => $pricing['discount_label'],
                'custom_note' => $customNote ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('customer_carts')->where('id', $cart->id)->update([
            'shop_id' => $cart->shop_id ?: ($product->shop_id ?? null),
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'message' => 'Added to cart.'];
    }

    public function removeEmptyShop(int $cartId): void
    {
        if (!DB::table('customer_cart_items')->where('cart_id', $cartId)->exists()) {
            DB::table('customer_carts')->where('id', $cartId)->update(['shop_id' => null, 'updated_at' => now()]);
        }
    }
}
