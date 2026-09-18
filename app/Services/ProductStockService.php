<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductStockService
{
    public function isTracked(?object $product): bool
    {
        return $product && property_exists($product, 'available_quantity') && $product->available_quantity !== null;
    }

    public function stockLabel(?object $product): string
    {
        if (!$this->isTracked($product)) return 'Available';

        $qty = max(0, (int) $product->available_quantity);
        if ($qty === 0) return 'Out of stock';
        if ($qty <= 3) return "Only {$qty} left";
        return "{$qty} available";
    }

    public function validateProductQuantity(string $productId, int $quantity): array
    {
        $quantity = max(1, $quantity);
        $product = DB::table('products')->where('id', $productId)->first();
        if (!$product) {
            return ['ok' => false, 'message' => 'Product not found.'];
        }

        if (!$this->isTracked($product)) {
            return ['ok' => true, 'product' => $product];
        }

        $available = max(0, (int) $product->available_quantity);
        if ($available <= 0) {
            return ['ok' => false, 'message' => "{$product->name} is currently out of stock."];
        }
        if ($quantity > $available) {
            return ['ok' => false, 'message' => "Only {$available} {$product->name} available. Please reduce the quantity."];
        }

        return ['ok' => true, 'product' => $product];
    }

    public function validateItems(iterable $items): array
    {
        foreach ($this->groupItems($items) as $productId => $quantity) {
            $result = $this->validateProductQuantity((string) $productId, (int) $quantity);
            if (!$result['ok']) return $result;
        }

        return ['ok' => true];
    }

    public function reserveItems(iterable $items): array
    {
        if (!Schema::hasColumn('products', 'available_quantity')) {
            return ['ok' => true];
        }

        return DB::transaction(function () use ($items) {
            foreach ($this->groupItems($items) as $productId => $quantity) {
                $quantity = max(1, (int) $quantity);
                $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    return ['ok' => false, 'message' => 'Product not found.'];
                }
                if (!$this->isTracked($product)) {
                    continue;
                }

                $available = max(0, (int) $product->available_quantity);
                if ($available < $quantity) {
                    $message = $available <= 0
                        ? "{$product->name} is currently out of stock."
                        : "Only {$available} {$product->name} available. Please reduce the quantity.";
                    return ['ok' => false, 'message' => $message];
                }

                DB::table('products')->where('id', $productId)->update([
                    'available_quantity' => $available - $quantity,
                    'updated_at' => now(),
                ]);
            }

            return ['ok' => true];
        });
    }

    public function releaseForOrder(object|string $order): bool
    {
        if (!Schema::hasColumn('orders', 'stock_released_at') || !Schema::hasColumn('products', 'available_quantity')) {
            return false;
        }

        $orderId = is_object($order) ? (string) $order->id : (string) $order;

        return DB::transaction(function () use ($orderId) {
            $fresh = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$fresh || !empty($fresh->stock_released_at)) {
                return false;
            }

            $items = $this->itemsForOrder($fresh);
            foreach ($this->groupItems($items) as $productId => $quantity) {
                $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                if (!$this->isTracked($product)) {
                    continue;
                }

                DB::table('products')->where('id', $productId)->update([
                    'available_quantity' => max(0, (int) $product->available_quantity) + max(1, (int) $quantity),
                    'is_available' => true,
                    'updated_at' => now(),
                ]);
            }

            DB::table('orders')->where('id', $orderId)->update([
                'stock_released_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    public function itemsForOrder(object $order): array
    {
        if (Schema::hasTable('order_items')) {
            $rows = DB::table('order_items')->where('order_id', $order->id)->get();
            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => max(1, (int) $item->quantity),
                ])->all();
            }
        }

        return [[
            'product_id' => $order->product_id ?? null,
            'quantity' => max(1, (int) ($order->quantity ?? 1)),
        ]];
    }

    private function groupItems(iterable $items): Collection
    {
        return collect($items)
            ->map(fn ($item) => is_array($item) ? $item : (array) $item)
            ->filter(fn ($item) => !empty($item['product_id']))
            ->groupBy(fn ($item) => (string) $item['product_id'])
            ->map(fn ($group) => $group->sum(fn ($item) => max(1, (int) ($item['quantity'] ?? 1))));
    }
}
