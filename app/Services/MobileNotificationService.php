<?php

namespace App\Services;

use App\Helpers\SmsHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MobileNotificationService
{
    public function __construct(private PushNotificationService $push)
    {
    }

    public function notifyOrderCustomer(object $order, string $title, string $message, array $data = [], ?string $smsMessage = null): array
    {
        $trackCode = strtoupper(trim((string) ($order->track_code ?? '')));
        $url = !empty($order->user_id)
            ? route('customer.orders', [], false)
            : ($trackCode ? route('track.order', $trackCode, false) : null);

        $recordId = $this->record([
            'role' => !empty($order->user_id) ? 'customer' : 'guest_customer',
            'user_id' => !empty($order->user_id) ? (string) $order->user_id : null,
            'guest_track_code' => $trackCode ?: null,
            'order_id' => (string) ($order->id ?? ''),
            'event_type' => $data['event'] ?? 'order_update',
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data,
        ]);

        $sent = $this->push->sendToOrderCustomer($order, $title, $message, $data + [
            'notification_id' => (string) $recordId,
        ]);

        $phone = $order->guest_phone
            ?? (!empty($order->user_id) ? DB::table('users')->where('id', $order->user_id)->value('phone') : null);

        return $this->finish($recordId, $sent, $phone, $smsMessage);
    }

    public function notifyGuestTrackCode(?string $trackCode, ?string $phone, string $title, string $message, array $data = [], ?string $smsMessage = null): array
    {
        $trackCode = strtoupper(trim((string) $trackCode));
        $recordId = $this->record([
            'role' => 'guest_customer',
            'guest_track_code' => $trackCode ?: null,
            'order_id' => (string) ($data['order_id'] ?? ''),
            'event_type' => $data['event'] ?? 'order_update',
            'title' => $title,
            'message' => $message,
            'url' => $trackCode ? route('track.order', $trackCode, false) : null,
            'data' => $data,
        ]);

        $sent = $this->push->sendToGuestTrackCode($trackCode, $title, $message, $data + [
            'notification_id' => (string) $recordId,
        ]);

        return $this->finish($recordId, $sent, $phone, $smsMessage);
    }

    public function notifyUser(string $role, ?string $userId, ?string $phone, string $title, string $message, array $data = [], ?string $smsMessage = null, ?string $url = null): array
    {
        $recordId = $this->record([
            'role' => $role,
            'user_id' => $userId,
            'order_id' => (string) ($data['order_id'] ?? ''),
            'event_type' => $data['event'] ?? 'general',
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data,
        ]);

        $sent = $this->push->sendToUser($role, $userId, $title, $message, $data + [
            'notification_id' => (string) $recordId,
            'url' => $url ?? '',
        ]);

        return $this->finish($recordId, $sent, $phone, $smsMessage);
    }

    public function notifyOrderSeller(object $order, string $title, string $message, array $data = [], ?string $smsMessage = null): array
    {
        $sellerId = !empty($order->shop_id)
            ? DB::table('shops')->where('id', $order->shop_id)->value('seller_id')
            : null;
        $phone = $sellerId ? DB::table('users')->where('id', $sellerId)->value('phone') : null;

        return $this->notifyUser('seller', $sellerId ? (string) $sellerId : null, $phone, $title, $message, $data + [
            'order_id' => (string) ($order->id ?? ''),
            'track_code' => (string) ($order->track_code ?? ''),
        ], $smsMessage, route('seller.orders', [], false));
    }

    public function notifyPaymentComplete(object $order, ?string $customerSmsMessage = null): array
    {
        $isFull = ($order->payment_status ?? '') === 'Paid';
        $amount = number_format((float) ($order->total_price ?? 0), 2);
        $sellerTitle = $isFull ? 'GCash Payment Complete' : 'GCash Deposit Paid';
        $sellerBody = $isFull
            ? "Order #{$order->id} is now paid. Amount: PHP {$amount}."
            : "Order #{$order->id} has a paid deposit and is ready for confirmation.";

        $seller = $this->notifyOrderSeller($order, $sellerTitle, $sellerBody, ['event' => 'payment_complete']);

        $rider = ['push_sent' => 0, 'sms_sent' => null, 'channel' => 'none'];
        if (!empty($order->rider_id)) {
            $riderPhone = DB::table('riders')->where('id', $order->rider_id)->value('phone');
            $riderUrl = !empty($order->rider_token) ? route('rider.show', [$order->id, $order->rider_token], false) : null;
            $rider = $this->notifyRider(
                (int) $order->rider_id,
                $riderPhone,
                $isFull ? 'Payment Complete' : 'Deposit Paid',
                $isFull ? "Order #{$order->id} is paid. You may continue delivery when assigned." : "Order #{$order->id} has a paid deposit.",
                ['event' => 'payment_complete', 'order_id' => (string) $order->id, 'url' => $riderUrl],
                null,
                $riderUrl
            );
        }

        $customer = $this->notifyOrderCustomer(
            $order,
            'Payment Received',
            "Your payment for Order #{$order->id} was received.",
            ['event' => 'payment_complete'],
            $customerSmsMessage
        );

        return ['seller' => $seller, 'rider' => $rider, 'customer' => $customer];
    }

    public function notifyRider(?int $riderId, ?string $phone, string $title, string $message, array $data = [], ?string $smsMessage = null, ?string $url = null): array
    {
        $recordId = $this->record([
            'role' => 'rider',
            'rider_id' => $riderId,
            'order_id' => (string) ($data['order_id'] ?? ''),
            'event_type' => $data['event'] ?? 'rider_update',
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data,
        ]);

        $sent = $this->push->sendToRider($riderId, $title, $message, $data + [
            'notification_id' => (string) $recordId,
            'url' => $url ?? '',
        ]);

        return $this->finish($recordId, $sent, $phone, $smsMessage);
    }

    private function finish(?int $recordId, int $pushSent, ?string $phone, ?string $smsMessage): array
    {
        $channel = $pushSent > 0 ? 'push' : 'none';
        $smsSent = null;

        if ($pushSent <= 0 && $phone && $smsMessage) {
            $smsSent = SmsHelper::send($phone, $smsMessage);
            $channel = $smsSent ? 'sms' : 'sms_failed';
        }

        if ($recordId && Schema::hasTable('mobile_notifications')) {
            try {
                DB::table('mobile_notifications')->where('id', $recordId)->update([
                    'delivery_channel' => $channel,
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Mobile notification channel update failed: ' . $e->getMessage());
            }
        }

        return ['push_sent' => $pushSent, 'sms_sent' => $smsSent, 'channel' => $channel, 'notification_id' => $recordId];
    }

    private function record(array $data): ?int
    {
        if (!Schema::hasTable('mobile_notifications')) {
            return null;
        }

        try {
            return (int) DB::table('mobile_notifications')->insertGetId([
                'role' => $data['role'],
                'user_id' => $data['user_id'] ?? null,
                'rider_id' => $data['rider_id'] ?? null,
                'guest_track_code' => $data['guest_track_code'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'event_type' => $data['event_type'] ?? null,
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'url' => $data['url'] ?? null,
                'data' => isset($data['data']) ? json_encode($data['data']) : null,
                'delivery_channel' => 'pending',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mobile notification record failed: ' . $e->getMessage());
            return null;
        }
    }
}
