<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderTypeService
{
    public const REGULAR = 'regular';
    public const CUSTOM = 'custom';

    public function insertValue(string $type): array
    {
        return Schema::hasColumn('orders', 'order_type') ? ['order_type' => $type] : [];
    }

    public function isCustom(object $order): bool
    {
        $type = strtolower((string) ($order->order_type ?? ''));
        if ($type === self::CUSTOM) return true;
        if ($type === self::REGULAR) return false;

        if (!empty($order->id) && Schema::hasTable('custom_orders')) {
            return DB::table('custom_orders')->where('order_id', $order->id)->exists();
        }

        if (!empty($order->product_id) && Schema::hasTable('products')) {
            $classification = DB::table('products')->where('id', $order->product_id)->value('classification');
            if (strtolower((string) $classification) === 'custom') return true;
        }

        return str_starts_with(strtoupper((string) ($order->custom_note ?? '')), 'CUSTOM ORDER');
    }

    public function isRequestBakeOrder(object $order): bool
    {
        return !empty($order->order_request_id);
    }

    public function requiresKitchen(object $order): bool
    {
        return $this->isCustom($order) || $this->isRequestBakeOrder($order);
    }
}
