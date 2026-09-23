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

    public function isSizeTracked(?object $size): bool
    {
        return $size && property_exists($size, 'available_quantity') && $size->available_quantity !== null;
    }

    public function stockLabel(?object $product): string
    {
        if (!$this->isTracked($product)) return 'Available';

        $qty = max(0, (int) $product->available_quantity);
        if ($qty === 0) return 'Out of stock';
        if ($qty <= 3) return "Only {$qty} left";
        return "{$qty} available";
    }

    public function validateProductQuantity(string $productId, int $quantity, ?string $selectedSize = null): array
    {
        $quantity = max(1, $quantity);
        $product = DB::table('products')->where('id', $productId)->first();
        if (!$product) {
            return ['ok' => false, 'message' => 'Product not found.'];
        }

        $stock = $this->stockTarget($product, $selectedSize);
        if (!$stock['ok']) return $stock;

        if (($stock['type'] ?? '') === 'size') {
            $size = $stock['size'];
            if (!$this->isSizeTracked($size)) {
                return ['ok' => true, 'product' => $product, 'size' => $size];
            }

            $available = max(0, (int) $size->available_quantity);
            if ($available <= 0) {
                return ['ok' => false, 'message' => "{$product->name} ({$size->label}) is currently out of stock."];
            }
            if ($quantity > $available) {
                return ['ok' => false, 'message' => "Only {$available} {$product->name} ({$size->label}) available. Please reduce the quantity."];
            }

            return ['ok' => true, 'product' => $product, 'size' => $size];
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
        foreach ($this->groupItems($items) as $group) {
            $result = $this->validateProductQuantity((string) $group['product_id'], (int) $group['quantity'], $group['selected_size']);
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
            foreach ($this->groupItems($items) as $group) {
                $productId = (string) $group['product_id'];
                $quantity = max(1, (int) $group['quantity']);
                $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    return ['ok' => false, 'message' => 'Product not found.'];
                }

                $stock = $this->stockTarget($product, $group['selected_size'], true);
                if (!$stock['ok']) return $stock;

                if (($stock['type'] ?? '') === 'size') {
                    $size = $stock['size'];
                    if (!$this->isSizeTracked($size)) {
                        continue;
                    }

                    $available = max(0, (int) $size->available_quantity);
                    if ($available < $quantity) {
                        $message = $available <= 0
                            ? "{$product->name} ({$size->label}) is currently out of stock."
                            : "Only {$available} {$product->name} ({$size->label}) available. Please reduce the quantity.";
                        return ['ok' => false, 'message' => $message];
                    }

                    DB::table('product_sizes')->where('id', $size->id)->update([
                        'available_quantity' => $available - $quantity,
                        'updated_at' => now(),
                    ]);
                    continue;
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
            foreach ($this->groupItems($items) as $group) {
                $productId = (string) $group['product_id'];
                $quantity = max(1, (int) $group['quantity']);
                $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    continue;
                }

                $stock = $this->stockTarget($product, $group['selected_size'], true);
                if (!$stock['ok']) {
                    continue;
                }
                if (($stock['type'] ?? '') === 'size') {
                    $size = $stock['size'];
                    if (!$this->isSizeTracked($size)) {
                        continue;
                    }
                    DB::table('product_sizes')->where('id', $size->id)->update([
                        'available_quantity' => max(0, (int) $size->available_quantity) + $quantity,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
                    continue;
                }

                if (!$this->isTracked($product)) {
                    continue;
                }

                DB::table('products')->where('id', $productId)->update([
                    'available_quantity' => max(0, (int) $product->available_quantity) + $quantity,
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
                    'selected_size' => $item->selected_size ?? null,
                ])->all();
            }
        }

        return [[
            'product_id' => $order->product_id ?? null,
            'quantity' => max(1, (int) ($order->quantity ?? 1)),
            'selected_size' => $order->selected_size ?? null,
        ]];
    }

    public function availableForCartItem(object $item): ?int
    {
        $product = DB::table('products')->where('id', $item->product_id)->first();
        if (!$product) return null;

        $stock = $this->stockTarget($product, $item->selected_size ?? null);
        if (($stock['type'] ?? '') === 'size') {
            return $this->isSizeTracked($stock['size']) ? max(0, (int) $stock['size']->available_quantity) : null;
        }

        return $this->isTracked($product) ? max(0, (int) $product->available_quantity) : null;
    }

    private function stockTarget(object $product, ?string $selectedSize, bool $lock = false): array
    {
        $selectedSize = trim((string) $selectedSize);
        if (!Schema::hasTable('product_sizes')) {
            return ['ok' => true, 'type' => 'product'];
        }

        $sizesQuery = DB::table('product_sizes')->where('product_id', $product->id)->where('is_active', true);
        if (Schema::hasColumn('product_sizes', 'archived_at')) {
            $sizesQuery->whereNull('archived_at');
        }
        $hasSizes = (clone $sizesQuery)->exists();
        if (!$hasSizes) {
            return ['ok' => true, 'type' => 'product'];
        }

        if ($selectedSize === '') {
            return ['ok' => false, 'message' => "Please choose a size for {$product->name}."];
        }

        $sizeQuery = (clone $sizesQuery)->whereRaw('LOWER(label) = ?', [strtolower($selectedSize)]);
        if ($lock) $sizeQuery->lockForUpdate();
        $size = $sizeQuery->first();
        if (!$size) {
            return ['ok' => false, 'message' => 'Selected size is no longer available. Please choose another size.'];
        }

        return ['ok' => true, 'type' => 'size', 'size' => $size];
    }

    private function groupItems(iterable $items): Collection
    {
        return collect($items)
            ->map(fn ($item) => is_array($item) ? $item : (array) $item)
            ->filter(fn ($item) => !empty($item['product_id']))
            ->groupBy(fn ($item) => (string) $item['product_id'] . '|' . strtolower(trim((string) ($item['selected_size'] ?? ''))))
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'product_id' => (string) $first['product_id'],
                    'selected_size' => trim((string) ($first['selected_size'] ?? '')) ?: null,
                    'quantity' => $group->sum(fn ($item) => max(1, (int) ($item['quantity'] ?? 1))),
                ];
            })
            ->values();
    }
}
