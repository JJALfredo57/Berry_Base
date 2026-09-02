<?php

namespace App\Services;

use App\Helpers\SmsHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RiderAssignmentService
{
    private const ACCEPTANCE_MINUTES = 10;

    public function __construct(private MobileNotificationService $notifications)
    {
    }

    public function assign(object $order, object $rider, ?object $shop = null, string $actorRole = 'seller'): array
    {
        $orderId = (string) $order->id;
        $token = $order->rider_token ?: bin2hex(random_bytes(16));
        $pin = SmsHelper::generateRiderPin();
        $expiresAt = now()->addMinutes(self::ACCEPTANCE_MINUTES);
        $previousStatus = $this->previousStatus($order);

        $updates = $this->filterExistingColumns('orders', [
            'rider_id' => $rider->id,
            'rider_token' => $token,
            'rider_pin' => $pin,
            'rider_sms_sent' => false,
            'rider_assignment_status' => 'pending',
            'rider_assignment_previous_status' => $previousStatus,
            'rider_assigned_at' => now(),
            'rider_assignment_expires_at' => $expiresAt,
            'rider_accepted_at' => null,
            'rider_declined_at' => null,
            'rider_decline_reason' => null,
            'status' => 'Out for Delivery',
            'updated_at' => now(),
        ]);

        DB::table('orders')->where('id', $orderId)->update($updates);
        $this->track($orderId, 'Rider Assignment Pending', "Assigned to rider: {$rider->name}. Waiting for rider confirmation.");

        $freshOrder = DB::table('orders')->where('id', $orderId)->first() ?: $order;
        $url = route('rider.show', [$orderId, $token], false);
        $sms = $this->buildRiderSms($freshOrder, $rider, $shop, $pin, $token);
        $notice = $this->notifications->notifyRider(
            (int) $rider->id,
            $rider->phone ?? null,
            'Delivery Needs Confirmation',
            "Order #{$orderId} is assigned to you. Please accept or decline within " . self::ACCEPTANCE_MINUTES . ' minutes.',
            ['event' => 'rider_assignment_pending', 'order_id' => $orderId, 'url' => $url],
            $sms,
            $url
        );

        DB::table('orders')->where('id', $orderId)->update($this->filterExistingColumns('orders', [
            'rider_sms_sent' => in_array(($notice['channel'] ?? 'none'), ['push', 'sms'], true),
            'updated_at' => now(),
        ]));

        return ['token' => $token, 'pin' => $pin, 'expires_at' => $expiresAt, 'notice' => $notice];
    }

    public function accept(object $order): void
    {
        if (($order->rider_assignment_status ?? '') === 'accepted') {
            return;
        }

        DB::table('orders')->where('id', $order->id)->update($this->filterExistingColumns('orders', [
            'rider_assignment_status' => 'accepted',
            'rider_accepted_at' => now(),
            'rider_declined_at' => null,
            'rider_decline_reason' => null,
            'status' => 'Out for Delivery',
            'updated_at' => now(),
        ]));

        $riderName = DB::table('riders')->where('id', $order->rider_id)->value('name') ?: 'Rider';
        $this->track((string) $order->id, 'Rider Accepted Delivery', "{$riderName} accepted the delivery assignment.");
        $this->notifySeller($order, 'Rider Accepted Delivery', "{$riderName} accepted Order #{$order->id}.", 'rider_assignment_accepted');
    }

    public function decline(object $order, string $reason): void
    {
        $riderName = DB::table('riders')->where('id', $order->rider_id)->value('name') ?: 'Rider';
        $nextStatus = $this->restoreStatus($order);

        DB::table('orders')->where('id', $order->id)->update($this->filterExistingColumns('orders', [
            'rider_id' => null,
            'rider_assignment_status' => 'declined',
            'rider_declined_at' => now(),
            'rider_decline_reason' => $reason,
            'status' => $nextStatus,
            'updated_at' => now(),
        ]));

        $this->track((string) $order->id, 'Rider Declined Delivery', "{$riderName} declined the delivery assignment. Reason: {$reason}");
        $this->notifySeller($order, 'Rider Declined Delivery', "{$riderName} declined Order #{$order->id}. Please assign another rider.", 'rider_assignment_declined');
    }

    public function expirePendingAssignments(?string $shopId = null, ?int $riderId = null): int
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'rider_assignment_status')) {
            return 0;
        }

        $query = DB::table('orders')
            ->where('rider_assignment_status', 'pending')
            ->whereNotNull('rider_assignment_expires_at')
            ->where('rider_assignment_expires_at', '<=', now());

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }
        if ($riderId) {
            $query->where('rider_id', $riderId);
        }

        $orders = $query->get();
        foreach ($orders as $order) {
            $this->expire($order);
        }

        return $orders->count();
    }

    public function requiresAcceptance(object $order): bool
    {
        return Schema::hasColumn('orders', 'rider_assignment_status')
            && ($order->rider_assignment_status ?? '') === 'pending';
    }

    public function canRiderWork(object $order): bool
    {
        $status = (string) ($order->rider_assignment_status ?? '');
        return $status === '' || in_array($status, ['accepted'], true);
    }

    private function expire(object $order): void
    {
        $riderName = DB::table('riders')->where('id', $order->rider_id)->value('name') ?: 'Rider';
        $nextStatus = $this->restoreStatus($order);

        DB::table('orders')->where('id', $order->id)->update($this->filterExistingColumns('orders', [
            'rider_id' => null,
            'rider_assignment_status' => 'expired',
            'status' => $nextStatus,
            'updated_at' => now(),
        ]));

        $this->track((string) $order->id, 'Rider No Response', "{$riderName} did not respond before the assignment expired.");
        $this->notifySeller($order, 'Rider Did Not Respond', "{$riderName} did not respond for Order #{$order->id}. Please assign another rider.", 'rider_assignment_expired');
    }

    private function notifySeller(object $order, string $title, string $message, string $event): void
    {
        try {
            $this->notifications->notifyOrderSellerByPriority($order, $title, $message, ['event' => $event]);
        } catch (\Throwable $e) {
            Log::warning('Seller rider assignment notification failed: ' . $e->getMessage());
        }
    }

    private function buildRiderSms(object $order, object $rider, ?object $shop, string $pin, string $token): string
    {
        $siteName = config('app.name', 'Cake Shop');
        $shopName = SmsHelper::getShopName($shop->id ?? ($order->shop_id ?? null));
        $header = SmsHelper::header($siteName, $shopName);
        $custName = $order->guest_name
            ?? (!empty($order->user_id) ? DB::table('users')->where('id', $order->user_id)->value('fullname') : null)
            ?? 'Customer';
        $custPhone = $order->guest_phone
            ?? (!empty($order->user_id) ? DB::table('users')->where('id', $order->user_id)->value('phone') : null)
            ?? '';
        $addr = $order->delivery_address ?? $order->address ?? 'N/A';

        return SmsHelper::buildRiderSms($header, $order->id, $custName, $custPhone, $addr, SmsHelper::paymentLine($order), $pin, $rider->phone ?? '', $token);
    }

    private function previousStatus(object $order): string
    {
        $status = (string) ($order->status ?? 'Preparing');
        return in_array($status, ['Out for Delivery', 'Delivered', 'Cancelled'], true) ? 'Preparing' : $status;
    }

    private function restoreStatus(object $order): string
    {
        $previous = (string) ($order->rider_assignment_previous_status ?? '');
        return $previous !== '' ? $previous : 'Preparing';
    }

    private function track(string $orderId, string $status, string $notes): void
    {
        if (!Schema::hasTable('order_tracking')) {
            return;
        }

        DB::table('order_tracking')->insert($this->filterExistingColumns('order_tracking', [
            'order_id' => $orderId,
            'status' => $status,
            'notes' => $notes,
            'sender_role' => 'system',
            'receiver_role' => 'seller',
            'created_at' => now(),
        ]));
    }

    private function filterExistingColumns(string $table, array $values): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
            ->all();
    }
}
