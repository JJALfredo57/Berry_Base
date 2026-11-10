<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeliveryMessageService
{
    private const CLOSED_STATUSES = ['Cancelled', 'Delivered', 'Returned to Shop', 'No Show', 'Picked Up'];

    public function customerConversation(string $orderId, array $user): object
    {
        if (($user['role'] ?? '') !== 'customer' || empty($user['id'])) {
            throw new HttpException(403, 'Only the customer who owns this order can access this conversation.');
        }

        $order = $this->order($orderId);
        if ((string) $order->user_id !== (string) $user['id']) {
            throw new HttpException(403, 'You do not have access to this order conversation.');
        }

        $this->assertActive($order);
        return $order;
    }

    public function riderConversation(string $orderId, object $rider): object
    {
        $order = $this->order($orderId);
        if ($this->conversationRiderId($order) !== (int) $rider->id) {
            throw new HttpException(403, 'This conversation is assigned to another rider.');
        }

        $this->assertActive($order);
        return $order;
    }

    public function messages(object $order): Collection
    {
        return DB::table('delivery_messages')
            ->where('order_id', $order->id)
            ->where('rider_id', $this->conversationRiderId($order))
            ->orderBy('id')
            ->get(['id', 'sender_type', 'sender_id', 'message', 'image_path', 'read_at', 'created_at']);
    }

    public function markRead(object $order, string $recipientType): int
    {
        $opposite = $recipientType === 'customer' ? 'rider' : 'customer';
        return DB::table('delivery_messages')
            ->where('order_id', $order->id)
            ->where('rider_id', $this->conversationRiderId($order))
            ->where('sender_type', $opposite)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public function send(object $order, string $senderType, string $senderId, string $message, ?string $imagePath): object
    {
        $message = trim($message);
        if ($message === '' && !$imagePath) {
            throw new HttpException(422, 'Type a message or attach an image.');
        }
        $this->limit($order, $senderType, $senderId);

        $id = DB::transaction(function () use ($order, $senderType, $senderId, $message, $imagePath) {
            $duplicate = DB::table('delivery_messages')
                ->where('order_id', $order->id)
                ->where('rider_id', $this->conversationRiderId($order))
                ->where('sender_type', $senderType)
                ->where('sender_id', $senderId)
                ->where('message', $message)
                ->where('image_path', $imagePath)
                ->where('created_at', '>=', now()->subSeconds(8))
                ->exists();
            if ($duplicate) {
                throw new HttpException(429, 'That message was already sent.');
            }

            return DB::table('delivery_messages')->insertGetId([
                'order_id' => $order->id,
                'rider_id' => $this->conversationRiderId($order),
                'customer_user_id' => $order->user_id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'message' => $message !== '' ? $message : null,
                'image_path' => $imagePath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->notifyAfterCommit($order, $senderType, $message, $imagePath);
        return DB::table('delivery_messages')->where('id', $id)->first();
    }

    public function riderSummaries(int $riderId, Collection $orders): Collection
    {
        $activeIds = $orders->filter(fn ($order) => $this->active($order))->pluck('id')->values();
        if ($activeIds->isEmpty()) {
            return collect();
        }

        $latest = DB::table('delivery_messages as dm')
            ->whereIn('dm.order_id', $activeIds)
            ->where('dm.rider_id', $riderId)
            ->select('dm.order_id', 'dm.message', 'dm.image_path', 'dm.created_at')
            ->whereRaw('dm.id = (select max(inner_dm.id) from delivery_messages as inner_dm where inner_dm.order_id = dm.order_id and inner_dm.rider_id = dm.rider_id)')
            ->get()->keyBy('order_id');
        $unread = DB::table('delivery_messages')
            ->whereIn('order_id', $activeIds)
            ->where('rider_id', $riderId)
            ->where('sender_type', 'customer')->whereNull('read_at')
            ->selectRaw('order_id, count(*) as unread_count')->groupBy('order_id')->pluck('unread_count', 'order_id');

        return $orders->whereIn('id', $activeIds)->map(function ($order) use ($latest, $unread) {
            $order->delivery_last_message = $latest[$order->id]->message ?? null;
            $order->delivery_last_image = $latest[$order->id]->image_path ?? null;
            $order->delivery_unread_count = (int) ($unread[$order->id] ?? 0);
            return $order;
        })->values();
    }

    private function order(string $orderId): object
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order) {
            throw new HttpException(404, 'Order not found.');
        }
        return $order;
    }

    private function assertActive(object $order): void
    {
        if (!$this->active($order)) {
            throw new HttpException(422, 'This delivery conversation is no longer available.');
        }
    }

    private function active(object $order): bool
    {
        if (($order->fulfillment_type ?? '') !== 'Delivery' || !empty($order->user_id) === false || !$this->conversationRiderId($order)) {
            return false;
        }

        if (($order->status ?? '') === 'Delivered') {
            return !empty($order->delivery_rider_id);
        }

        return ($order->rider_assignment_status ?? '') === 'accepted'
            && ($order->status ?? '') === 'Out for Delivery'
            && !in_array(($order->status ?? ''), self::CLOSED_STATUSES, true);
    }

    private function limit(object $order, string $senderType, string $senderId): void
    {
        $key = "delivery-message:{$senderType}:{$senderId}:{$order->id}";
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new HttpException(429, 'Too many messages. Please wait a minute and try again.');
        }
        RateLimiter::hit($key, 60);
    }

    private function notifyAfterCommit(object $order, string $senderType, string $message, ?string $imagePath): void
    {
        try {
            $preview = $message !== '' ? mb_strimwidth($message, 0, 90, '...') : 'Image attachment';
            $notifications = app(MobileNotificationService::class);
            if ($senderType === 'customer') {
                $riderId = $this->conversationRiderId($order);
                $phone = DB::table('riders')->where('id', $riderId)->value('phone');
                $notifications->notifyRider($riderId, $phone, 'New customer message', "Order #{$order->id}: {$preview}", [
                    'event' => 'delivery_message', 'order_id' => (string) $order->id,
                ], null, route('rider.dashboard', [], false) . '#delivery-message-' . $order->id);
                return;
            }

            $notifications->notifyOrderCustomer($order, 'Message from your rider', "Order #{$order->id}: {$preview}", [
                'event' => 'delivery_message', 'order_id' => (string) $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Delivery message notification failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
        }
    }

    private function conversationRiderId(object $order): int
    {
        return (int) ($order->delivery_rider_id ?? $order->rider_id ?? 0);
    }
}
