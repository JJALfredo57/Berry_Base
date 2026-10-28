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
        // A fresh token and PIN per assignment so a previous rider's link and PIN stop working.
        $token = bin2hex(random_bytes(16));
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
            'status' => 'Ready for Rider',
            'updated_at' => now(),
        ]);

        // Atomic guard: only one request can win the assignment, so the rider is notified once.
        $claim = DB::table('orders')->where('id', $orderId);
        if (Schema::hasColumn('orders', 'rider_assignment_status')) {
            $claim->where(function ($q) {
                $q->whereNull('rider_assignment_status')
                    ->orWhereIn('rider_assignment_status', ['', 'declined', 'expired', 'cancelled']);
            });
        }
        if ($claim->update($updates) < 1) {
            return ['ok' => false, 'message' => $this->alreadyAssignedMessage($orderId)];
        }

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

        return ['ok' => true, 'token' => $token, 'pin' => $pin, 'expires_at' => $expiresAt, 'notice' => $notice];
    }

    /** True when a rider is already assigned and waiting to respond, or has accepted. */
    public function isLocked(object $order): bool
    {
        return in_array((string) ($order->rider_assignment_status ?? ''), ['pending', 'accepted'], true);
    }

    /** Current pending/accepted assignment for the UI, or null when a rider may be chosen. */
    public function lockInfo(string $orderId): ?object
    {
        if (!Schema::hasColumn('orders', 'rider_assignment_status')) {
            return null;
        }
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order || !$this->isLocked($order)) {
            return null;
        }
        $order->rider_name = $order->rider_id ? (DB::table('riders')->where('id', $order->rider_id)->value('name') ?: 'Rider') : 'Rider';
        return $order;
    }

    /** Delivery orders whose rider declined or let the request expire and still need a new rider. */
    public function countNeedingReassignment(string $shopId): int
    {
        if (!Schema::hasColumn('orders', 'rider_assignment_status')) {
            return 0;
        }

        return (int) DB::table('orders')
            ->where('shop_id', $shopId)
            ->where('fulfillment_type', 'Delivery')
            ->whereIn('rider_assignment_status', ['declined', 'expired'])
            ->whereNull('rider_id')
            ->whereNotIn('status', ['Out for Delivery', 'Delivered', 'Picked Up', 'Cancelled', 'No Show'])
            ->count();
    }

    /** Declined/expired assignment that still needs the seller to pick another rider. */
    public function alertInfo(string $orderId): ?object
    {
        if (!Schema::hasColumn('orders', 'rider_assignment_status')) {
            return null;
        }
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order || !empty($order->rider_id)
            || !in_array((string) ($order->rider_assignment_status ?? ''), ['declined', 'expired'], true)
            || in_array((string) ($order->status ?? ''), ['Out for Delivery', 'Delivered', 'Picked Up', 'Cancelled', 'No Show'], true)) {
            return null;
        }

        $isDeclined = $order->rider_assignment_status === 'declined';
        $note = Schema::hasTable('order_tracking')
            ? DB::table('order_tracking')->where('order_id', $orderId)
                ->where('status', $isDeclined ? 'Rider Declined Delivery' : 'Rider No Response')
                ->orderByDesc('created_at')->value('notes')
            : null;
        $name = $note ? trim((string) preg_split('/ (declined the delivery|did not respond)/', $note)[0]) : '';

        return (object) [
            'state' => $order->rider_assignment_status,
            'rider_name' => $name !== '' ? $name : 'The rider',
            'reason' => $isDeclined ? ($order->rider_decline_reason ?? null) : null,
            'at' => $isDeclined ? ($order->rider_declined_at ?? null) : null,
        ];
    }

    public function alreadyAssignedMessage(string $orderId): string
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        $name = ($order && $order->rider_id) ? DB::table('riders')->where('id', $order->rider_id)->value('name') : null;
        $name = $name ?: 'a rider';
        $state = ($order->rider_assignment_status ?? '') === 'accepted' ? 'already accepted by' : 'already assigned to';
        $suffix = ($order->rider_assignment_status ?? '') === 'pending' ? ' and is waiting for a response. Cancel the assignment first to choose another rider.' : '.';
        return "Order #{$orderId} is {$state} {$name}{$suffix}";
    }

    /** Seller/admin cancels a pending assignment; the rider is told and the request disappears from their side. */
    public function cancelPending(object $order, string $actorRole = 'seller'): array
    {
        $orderId = (string) $order->id;
        $riderId = $order->rider_id ?? null;
        $rider = $riderId ? DB::table('riders')->where('id', $riderId)->first() : null;
        $nextStatus = $this->restoreStatus($order);

        $cancelled = DB::table('orders')
            ->where('id', $orderId)
            ->where('rider_assignment_status', 'pending')
            ->update($this->filterExistingColumns('orders', [
                'rider_id' => null,
                'rider_token' => null,
                'rider_pin' => null,
                'rider_sms_sent' => false,
                'rider_assignment_status' => 'cancelled',
                'rider_assignment_expires_at' => null,
                'status' => $nextStatus,
                'updated_at' => now(),
            ]));

        if ($cancelled < 1) {
            return ['ok' => false, 'message' => 'This assignment can no longer be cancelled. The rider may have already responded.'];
        }

        $riderName = $rider->name ?? 'Rider';
        $this->track($orderId, 'Rider Assignment Cancelled', "Assignment to {$riderName} was cancelled by the {$actorRole}.");

        if ($rider) {
            try {
                $this->notifications->notifyRider(
                    (int) $rider->id,
                    $rider->phone ?? null,
                    'Assignment Cancelled',
                    "Order #{$orderId} is no longer assigned to you. No action needed.",
                    ['event' => 'rider_assignment_cancelled', 'order_id' => $orderId],
                    "Order #{$orderId} assignment was cancelled by the seller. No action needed.",
                    null
                );
            } catch (\Throwable $e) {
                Log::warning('Rider assignment cancel notice failed: ' . $e->getMessage());
            }
        }

        return ['ok' => true, 'rider_name' => $riderName];
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
        $this->track((string) $order->id, 'Out for Delivery', "{$riderName} is now out for delivery.");
        $this->notifySeller($order, 'Rider Accepted Delivery', "{$riderName} accepted Order #{$order->id}.", 'rider_assignment_accepted');
        try {
            $freshOrder = DB::table('orders')->where('id', $order->id)->first() ?: $order;
            $this->notifications->notifyOrderCustomer(
                $freshOrder,
                'Order Out for Delivery',
                "Order #{$order->id} is now out for delivery.",
                ['event' => 'order_status']
            );
        } catch (\Throwable $e) {
            Log::warning('Customer rider acceptance notification failed: ' . $e->getMessage());
        }
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
        $isSurprise = !empty($order->is_surprise_delivery);
        $custName = $isSurprise
            ? ($order->recipient_name ?? 'Recipient')
            : ($order->guest_name
                ?? (!empty($order->user_id) ? DB::table('users')->where('id', $order->user_id)->value('fullname') : null)
                ?? 'Customer');
        $custPhone = $isSurprise
            ? (($order->recipient_phone ?: null) ?? $order->guest_phone ?? '')
            : ($order->guest_phone
                ?? (!empty($order->user_id) ? DB::table('users')->where('id', $order->user_id)->value('phone') : null)
                ?? '');
        $addr = $isSurprise
            ? ($order->recipient_address ?? $order->delivery_address ?? $order->address ?? 'N/A')
            : ($order->delivery_address ?? $order->address ?? 'N/A');
        if ($isSurprise) {
            $addr .= ' | SURPRISE: contact sender first. Do not mention price.';
        }

        return SmsHelper::buildRiderSms($header, $order->id, $custName, $custPhone, $addr, SmsHelper::paymentLine($order), $pin, $rider->phone ?? '', $token);
    }

    private function previousStatus(object $order): string
    {
        $status = (string) ($order->status ?? 'Preparing');
        return in_array($status, ['Out for Delivery', 'Delivered', 'Cancelled'], true) ? 'Ready for Rider' : $status;
    }

    private function restoreStatus(object $order): string
    {
        $previous = (string) ($order->rider_assignment_previous_status ?? '');
        return $previous !== '' ? $previous : 'Ready for Rider';
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
