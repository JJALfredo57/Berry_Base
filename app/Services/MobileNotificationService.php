<?php

namespace App\Services;

use App\Helpers\SmsHelper;
use App\Helpers\CakeshopHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class MobileNotificationService
{
    public function __construct(private PushNotificationService $push)
    {
    }

    public function notifyOrderCustomer(object $order, string $title, string $message, array $data = [], ?string $smsMessage = null): array
    {
        $trackCode = strtoupper(trim((string) ($order->track_code ?? '')));
        $url = $this->orderCustomerUrl($order, $data);

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
        $url = $trackCode ? route('track.order', $trackCode, false) . $this->eventHash($data['event'] ?? null) : null;
        $recordId = $this->record([
            'role' => 'guest_customer',
            'guest_track_code' => $trackCode ?: null,
            'order_id' => (string) ($data['order_id'] ?? ''),
            'event_type' => $data['event'] ?? 'order_update',
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data,
        ]);

        $sent = $this->push->sendToGuestTrackCode($trackCode, $title, $message, $data + [
            'notification_id' => (string) $recordId,
            'url' => $url ?? '',
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

        $payload = $data + [
            'order_id' => (string) ($order->id ?? ''),
            'track_code' => (string) ($order->track_code ?? ''),
        ];

        return $this->notifyUser('seller', $sellerId ? (string) $sellerId : null, $phone, $title, $message, $payload, $smsMessage, $this->orderSellerUrl($order, $payload));
    }

    public function notifyOrderSellerByPriority(object $order, string $title, string $message, array $data = [], ?string $smsMessage = null): array
    {
        $sellerId = !empty($order->shop_id)
            ? DB::table('shops')->where('id', $order->shop_id)->value('seller_id')
            : null;
        $seller = $sellerId ? DB::table('users')->where('id', $sellerId)->select('id', 'email', 'phone', 'fullname')->first() : null;

        $payload = $data + [
            'order_id' => (string) ($order->id ?? ''),
            'track_code' => (string) ($order->track_code ?? ''),
        ];
        $url = $this->orderSellerUrl($order, $payload);

        $recordId = $this->record([
            'role' => 'seller',
            'user_id' => $sellerId ? (string) $sellerId : null,
            'order_id' => (string) ($order->id ?? ''),
            'event_type' => $payload['event'] ?? 'seller_update',
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $payload,
        ]);

        $sent = $this->push->sendToUser('seller', $sellerId ? (string) $sellerId : null, $title, $message, $payload + [
            'notification_id' => (string) $recordId,
            'url' => $url ?? '',
        ]);

        if ($sent > 0) {
            $this->updateChannel($recordId, 'push');
            return ['push_sent' => $sent, 'email_sent' => null, 'sms_sent' => null, 'channel' => 'push', 'notification_id' => $recordId];
        }

        if ($seller && filter_var((string) $seller->email, FILTER_VALIDATE_EMAIL)) {
            $emailSent = $this->sendNotificationEmail((string) $seller->email, $title, $message, $url);
            if ($emailSent) {
                $this->updateChannel($recordId, 'email');
                return ['push_sent' => 0, 'email_sent' => true, 'sms_sent' => null, 'channel' => 'email', 'notification_id' => $recordId];
            }
        }

        $phone = $seller->phone ?? null;
        $smsSent = null;
        if ($phone) {
            $smsSent = SmsHelper::send($phone, $smsMessage ?: $message);
        }

        $channel = $smsSent ? 'sms' : 'none';
        $this->updateChannel($recordId, $channel);
        return ['push_sent' => 0, 'email_sent' => false, 'sms_sent' => $smsSent, 'channel' => $channel, 'notification_id' => $recordId];
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

        $this->updateChannel($recordId, $channel);

        return ['push_sent' => $pushSent, 'sms_sent' => $smsSent, 'channel' => $channel, 'notification_id' => $recordId];
    }

    private function updateChannel(?int $recordId, string $channel): void
    {
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
    }

    private function sendNotificationEmail(string $email, string $title, string $message, ?string $url = null): bool
    {
        try {
            $settings = CakeshopHelper::getSettings();
            $siteName = $settings['site_title'] ?? config('app.name', 'Cake Shop');
            $fromAddr = config('mail.from.address', 'no-reply@cakeshop.com');
            $fromName = config('mail.from.name', $siteName);

            $button = $url
                ? '<p style="margin:24px 0"><a href="' . e(url($url)) . '" style="display:inline-block;background:#7B3A0F;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700">Open in Dashboard</a></p>'
                : '';
            $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #eee;border-radius:12px;overflow:hidden">'
                . '<div style="background:#7B3A0F;padding:20px 24px;color:#fff"><h2 style="margin:0;font-size:20px">' . e($siteName) . '</h2></div>'
                . '<div style="padding:24px;background:#fff"><h3 style="margin-top:0;color:#111827">' . e($title) . '</h3>'
                . '<p style="color:#374151;font-size:15px;line-height:1.55">' . nl2br(e($message)) . '</p>'
                . $button
                . '<p style="color:#9ca3af;font-size:12px;margin-top:24px">This notification was sent because no active seller app session was available.</p>'
                . '</div></div>';

            Mail::send([], [], function ($mail) use ($email, $siteName, $fromAddr, $fromName, $html, $title) {
                $mail->to($email)
                    ->from($fromAddr, $fromName)
                    ->subject($siteName . ' - ' . $title)
                    ->html($html);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Seller notification email failed: ' . $e->getMessage());
            return false;
        }
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

    private function orderCustomerUrl(object $order, array $data = []): ?string
    {
        $event = (string) ($data['event'] ?? '');
        if (!empty($order->user_id)) {
            if ($event === 'message' && !empty($order->id)) {
                return route('customer.messages.thread', $order->id, false);
            }

            return route('customer.orders', [], false) . $this->eventHash($event);
        }

        $trackCode = strtoupper(trim((string) ($order->track_code ?? '')));
        return $trackCode ? route('track.order', $trackCode, false) . $this->eventHash($event) : null;
    }

    private function orderSellerUrl(object $order, array $data = []): string
    {
        $event = (string) ($data['event'] ?? '');
        if ($event === 'message' && !empty($order->id)) {
            return route('seller.messages.thread', $order->id, false);
        }

        if (in_array($event, ['payment_complete', 'refund_request', 'cancel_request', 'order_cancelled'], true)) {
            return route('seller.orders', [], false) . '#order-' . rawurlencode((string) ($order->id ?? ''));
        }

        return route('seller.orders', [], false);
    }

    private function eventHash(?string $event): string
    {
        return match ((string) $event) {
            'message' => '#messages',
            'payment_complete', 'payment_request', 'deposit_request' => '#payment',
            'refund_sent', 'refund_request', 'cancel_request', 'order_cancelled' => '#refund',
            'review_request' => '#review',
            default => '',
        };
    }
}
