<?php

namespace App\Services;

use App\Helpers\CakeshopHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomCartOrderService
{
    private function generateTrackCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        } while (DB::table('orders')->where('track_code', $code)->exists());
        return $code;
    }

    public function createFromCartItem(object $item, array $context = []): array
    {
        $meta = json_decode($item->meta ?? '[]', true) ?: [];
        if (($meta['cart_type'] ?? '') !== 'custom_cake') {
            return ['ok' => false, 'message' => 'This cart item is not a custom cake draft.'];
        }

        $shopId = $item->shop_id ?? ($meta['shop_id'] ?? null);
        $qty = max(1, (int) ($item->quantity ?? ($meta['quantity'] ?? 1)));
        $date = $meta['schedule_date'] ?? null;
        $scheduleTime = trim((string) ($meta['schedule_time'] ?? ''));
        $prep = app(PreparationWindowService::class);
        $hold = $prep->validateCustomHold($meta);
        if (!$hold['ok']) return ['ok' => false, 'message' => $hold['message']];
        $prepDate = $prep->validateDate($shopId, $date, 'custom');
        if (!$prepDate['ok']) return ['ok' => false, 'message' => $prepDate['message']];
        $capacity = app(DailyCapacityService::class)->validate($shopId, $date, $qty);
        if (!$capacity['allowed']) return ['ok' => false, 'message' => $capacity['message']];

        if ($scheduleTime !== '') {
            $scheduleCheck = app(OrderScheduleService::class)->validate($date, $scheduleTime, $shopId, 'custom', $meta['fulfillment_type'] ?? 'Pickup', isset($meta['latitude']) ? (float) $meta['latitude'] : null, isset($meta['longitude']) ? (float) $meta['longitude'] : null);
            if (!$scheduleCheck['ok']) return ['ok' => false, 'message' => $scheduleCheck['message']];
        }

        $cakeName = trim((string) ($meta['cake_name'] ?? 'Custom Cake'));
        $flavor = trim((string) ($meta['flavor'] ?? ''));
        $size = trim((string) ($meta['size'] ?? ''));
        $layers = trim((string) ($meta['layers'] ?? ''));
        $complexity = trim((string) ($meta['design_complexity'] ?? ''));
        $dedication = trim((string) ($meta['dedication'] ?? ''));
        $customNote = trim((string) ($meta['custom_note'] ?? ''));
        $addonInstructions = trim((string) ($meta['addon_instructions'] ?? ''));
        $addons = is_array($meta['addons'] ?? null) ? $meta['addons'] : [];
        $breakdown = is_array($meta['price_breakdown'] ?? null) ? $meta['price_breakdown'] : [];
        $total = max(0, (float) ($meta['estimated_total'] ?? ($item->final_unit_price_snapshot * $qty)));
        $unitPrice = (float) ($breakdown['unit_price'] ?? ($qty > 0 ? $total / $qty : $total));
        $fulfillment = $meta['fulfillment_type'] ?? 'Pickup';
        $deliveryFee = (float) ($meta['delivery_fee'] ?? 0);
        $serviceCharge = (float) ($meta['service_charge'] ?? 0);
        $trackCode = $context['track_code'] ?? $this->generateTrackCode();
        $oid = CakeshopHelper::generateId('orders');

        $parts = ["CUSTOM ORDER - {$cakeName}"];
        if ($flavor) $parts[] = "Flavor: {$flavor}";
        if ($size) $parts[] = "Size: {$size}";
        if ($layers) $parts[] = "Layers: {$layers}";
        if ($complexity) $parts[] = "Design: {$complexity}";
        if ($dedication) $parts[] = "Dedication: \"{$dedication}\"";
        if ($customNote) $parts[] = "Notes: {$customNote}";
        if ($addons && $addonInstructions) $parts[] = "Add-on instructions: {$addonInstructions}";
        $fullNote = implode(' | ', $parts);

        $orderRow = [
            'id' => $oid,
            'cart_id' => $context['cart_id'] ?? null,
            'shop_id' => $shopId,
            'user_id' => $context['user_id'] ?? null,
            'guest_name' => $context['guest_name'] ?? null,
            'guest_phone' => $context['guest_phone'] ?? null,
            'track_code' => $trackCode,
            'product_id' => $item->product_id,
            'order_type' => 'custom',
            'checkout_group_id' => $context['checkout_group_id'] ?? null,
            'processing_mode' => $context['processing_mode'] ?? null,
            'quantity' => $qty,
            'custom_note' => $fullNote,
            'total_price' => $total,
            'status' => 'Pending Review',
            'fulfillment_type' => $fulfillment,
            'delivery_zone' => $meta['delivery_zone'] ?? '',
            'delivery_fee' => $fulfillment === 'Delivery' ? $deliveryFee : 0,
            'service_charge' => $fulfillment === 'Delivery' ? $serviceCharge : 0,
            'selected_size' => $size ?: null,
            'selected_size_price' => $unitPrice,
            'delivery_address' => $meta['address'] ?? '',
            'latitude' => $meta['latitude'] ?? null,
            'schedule_date' => $date,
            'schedule_time' => $scheduleTime ?: null,
            'payment_method' => $meta['payment_method'] ?? ($context['payment_method'] ?? 'COD'),
            'payment_status' => 'Unpaid',
            'created_at' => now(),
        ];
        $orderRow = array_filter($orderRow, fn ($value, $column) => Schema::hasColumn('orders', $column), ARRAY_FILTER_USE_BOTH);
        DB::table('orders')->insert($orderRow);

        $customOrderRow = [
            'id' => CakeshopHelper::generateId('custom_orders'),
            'order_id' => $oid,
            'shop_id' => $shopId,
            'user_id' => $context['user_id'] ?? null,
            'guest_name' => $context['guest_name'] ?? null,
            'guest_phone' => $context['guest_phone'] ?? null,
            'cake_name' => $cakeName,
            'flavor' => $flavor ?: null,
            'size' => $size ?: null,
            'layers' => $layers ?: null,
            'design_complexity' => $complexity ?: null,
            'dedication' => $dedication ?: null,
            'custom_note' => trim($customNote . ($addons && $addonInstructions ? "\nAdd-on instructions: {$addonInstructions}" : '')) ?: null,
            'time_slot' => $meta['time_slot'] ?? null,
            'reference_images' => !empty($meta['reference_images']) ? json_encode($meta['reference_images']) : null,
            'estimated_price' => $total,
            'price_breakdown' => json_encode($breakdown ?: ['total' => $total, 'quantity' => $qty]),
            'review_status' => 'pending',
            'review_deadline_at' => $meta['seller_review_deadline_at'] ?? optional($prep->reviewDeadline($shopId, $date))->toDateTimeString(),
            'review_deadline_status' => 'active',
            'created_at' => now(),
        ];
        $customOrderRow = array_filter($customOrderRow, fn ($value, $column) => Schema::hasColumn('custom_orders', $column), ARRAY_FILTER_USE_BOTH);
        DB::table('custom_orders')->insert($customOrderRow);

        foreach ($addons as $addon) {
            DB::table('order_addons')->insert([
                'order_id' => $oid,
                'addon_id' => $addon['id'] ?? null,
                'addon_name' => $addon['name'] ?? 'Custom add-on',
                'addon_price' => (float) ($addon['price'] ?? 0),
                'created_at' => now(),
            ]);
        }

        DB::table('order_tracking')->insert([
            'order_id' => $oid,
            'status' => 'Pending Review',
            'notes' => 'Custom order submitted from cart. Awaiting seller review.',
            'created_at' => now(),
        ]);

        DB::table('messages')->insert([
            'order_id' => $oid,
            'sender_role' => !empty($context['guest_phone']) ? 'guest' : 'customer',
            'sender_id' => $context['user_id'] ?? null,
            'message' => "Custom cake request submitted from cart.\n{$fullNote}",
            'is_read' => false,
            'created_at' => now(),
        ]);

        $customerName = $context['guest_name'] ?? $context['customer_name'] ?? 'Customer';
        $deadlineText = !empty($meta['seller_review_deadline_at']) ? ' Review before ' . date('M d, Y h:i A', strtotime($meta['seller_review_deadline_at'])) . '.' : '';
        $notifMsg = "{$customerName} submitted a custom cake request from cart." . $deadlineText;
        DB::table('notifications')->insert([
            'receiver_role' => 'admin',
            'receiver_user_id' => null,
            'title' => 'New Custom Order #' . $oid,
            'message' => $notifMsg,
            'is_read' => false,
            'created_at' => now(),
        ]);

        if ($shopId) {
            $sellerUser = DB::table('shops')->join('users', 'users.id', '=', 'shops.seller_id')
                ->where('shops.id', $shopId)
                ->select('users.id', 'users.phone')
                ->first();
            if ($sellerUser) {
                DB::table('notifications')->insert([
                    'receiver_role' => 'seller',
                    'receiver_user_id' => $sellerUser->id,
                    'title' => 'New Custom Order #' . $oid,
                    'message' => $notifMsg,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
                app(MobileNotificationService::class)->notifyUser(
                    'seller',
                    (string) $sellerUser->id,
                    $sellerUser->phone ?? null,
                    'New Custom Order',
                    $notifMsg,
                    ['event' => 'custom_order', 'order_id' => (string) $oid],
                    null,
                    route('seller.custom_orders', [], false)
                );
            }
        }

        return ['ok' => true, 'order_id' => $oid, 'track_code' => $trackCode, 'total' => $total];
    }
}

