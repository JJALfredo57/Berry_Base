<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SellerCancellationService
{
    public const RESPONSE_WINDOW_MINUTES = 10;

    public function expirePending(?string $shopId = null, ?string $userId = null): int
    {
        if (!Schema::hasColumn('orders', 'seller_cancel_state')) return 0;

        $query = DB::table('orders')
            ->where('seller_cancel_state', 'awaiting_customer')
            ->whereNotNull('seller_cancel_expires_at')
            ->where('seller_cancel_expires_at', '<=', now());
        if ($shopId) $query->where('shop_id', $shopId);
        if ($userId) $query->where('user_id', $userId);

        return $query->update([
            'seller_cancel_state' => 'expired',
            'seller_cancel_responded_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function start(object $order, string $reason): array
    {
        if (!Schema::hasColumn('orders', 'seller_cancel_state') || !Schema::hasColumn('orders', 'seller_cancel_expires_at')) {
            Log::error('Seller cancellation fields are missing from orders.', ['order_id' => $order->id ?? null]);
            return ['ok' => false, 'message' => 'Cancellation requests are temporarily unavailable. Please try again after the latest update is deployed.'];
        }
        $this->expirePending($order->shop_id ?? null);
        if (empty($order->user_id)) {
            return ['ok' => false, 'message' => 'This customer must be contacted directly before cancelling because no customer account is linked to this order.'];
        }
        if (($order->cancel_requested ?? 0) && ($order->cancel_status ?? '') === 'pending') {
            return ['ok' => false, 'message' => 'The customer already has a pending cancellation request for this order. Review that request before starting another one.'];
        }

        $expiresAt = now()->addMinutes(self::RESPONSE_WINDOW_MINUTES);
        $updated = DB::table('orders')
            ->where('id', $order->id)
            ->whereNotIn('status', ['Preparing', 'Ready for Rider', 'Out for Delivery', 'Delivered', 'Picked Up', 'No Show', 'Cancelled'])
            ->where(function ($query) {
                $query->whereNull('seller_cancel_state')
                    ->orWhereIn('seller_cancel_state', ['expired', 'customer_declined']);
            })
            ->where(function ($query) {
                $query->whereNull('cancel_requested')
                    ->orWhere('cancel_requested', 0)
                    ->orWhere('cancel_status', '!=', 'pending');
            })
            ->update([
                'seller_cancel_state' => 'awaiting_customer',
                'seller_cancel_expires_at' => $expiresAt,
                'seller_cancel_reason' => $reason,
                'seller_cancel_responded_at' => null,
                'updated_at' => now(),
            ]);
        if ($updated !== 1) return ['ok' => false, 'message' => 'A cancellation request is already active or this order can no longer be cancelled.'];

        $fresh = DB::table('orders')->where('id', $order->id)->first() ?: $order;
        try {
            app(MobileNotificationService::class)->notifyOrderCustomer($fresh, 'Seller requested cancellation', 'Please respond within 10 minutes and provide refund details if you accept.', ['event' => 'seller_cancellation_request', 'order_id' => (string) $order->id]);
            if (Schema::hasTable('notifications')) DB::table('notifications')->insert(['receiver_role' => 'customer', 'receiver_user_id' => $fresh->user_id, 'title' => 'Seller requested cancellation', 'message' => 'Please respond within 10 minutes. Open My Orders to accept or keep your order.', 'is_read' => false, 'created_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Seller cancellation notification failed after request was saved.', ['order_id' => $order->id, 'exception' => $e->getMessage()]);
        }

        return ['ok' => true, 'expires_at' => $expiresAt];
    }

    public function respond(object $order, string $action, array $data, OrderRefundService $refunds): array
    {
        $this->expirePending(null, (string) $order->user_id);
        $fresh = DB::table('orders')->where('id', $order->id)->first();
        if (!$fresh || ($fresh->seller_cancel_state ?? '') !== 'awaiting_customer') {
            return ['ok' => false, 'message' => 'This cancellation request has expired or was already answered. Your order will continue processing.'];
        }

        if ($action === 'decline') {
            $updated = DB::table('orders')->where('id', $fresh->id)->where('seller_cancel_state', 'awaiting_customer')->update([
                'seller_cancel_state' => 'customer_declined',
                'seller_cancel_responded_at' => now(),
                'updated_at' => now(),
            ]);
            if ($updated !== 1) return ['ok' => false, 'message' => 'This cancellation request was already answered.'];
            $this->notifySeller($fresh, 'Customer kept the order', "Customer declined the cancellation request for Order #{$fresh->id}. Continue processing the order.");
            return ['ok' => true, 'message' => 'You chose to keep the order. The seller was notified and processing can continue.'];
        }

        try {
            $result = DB::transaction(function () use ($fresh, $data, $refunds) {
                $locked = DB::table('orders')->where('id', $fresh->id)->lockForUpdate()->first();
                if (!$locked || ($locked->seller_cancel_state ?? '') !== 'awaiting_customer') {
                    return ['ok' => false, 'message' => 'This cancellation request was already answered.'];
                }

                DB::table('orders')->where('id', $locked->id)->update([
                    'seller_cancel_state' => 'customer_accepted',
                    'seller_cancel_responded_at' => now(),
                    'updated_at' => now(),
                ]);

                $refund = $refunds->requestPaidRefund($locked, [
                    'customer_name' => $data['customer_name'] ?? null,
                    'refund_gcash_name' => $data['refund_gcash_name'],
                    'refund_gcash_number' => $data['refund_gcash_number'],
                    'cancel_reason' => $locked->seller_cancel_reason ?: 'Seller requested cancellation.',
                ]);

                return ['ok' => true, 'refund' => $refund, 'order' => $locked];
            });
        } catch (\Throwable $e) {
            Log::error('Seller cancellation acceptance failed.', ['order_id' => $fresh->id, 'exception' => $e]);
            return ['ok' => false, 'message' => 'Unable to record the refund request right now. Please try again.'];
        }

        if (!($result['ok'] ?? false)) return $result;
        // The seller initiated this cancellation, so no duplicate seller approval is required.
        try {
            $sellerId = (string) (DB::table('shops')->where('id', $result['order']->shop_id)->value('seller_id') ?? '');
            $refunds->approveForAdminRefund($result['order'], $result['refund'], $sellerId);
        } catch (\Throwable $e) {
            Log::error('Seller-initiated cancellation could not enter the Super Admin refund queue.', ['order_id' => $fresh->id, 'exception' => $e]);
            return ['ok' => false, 'message' => 'Unable to queue the refund for Super Admin. Please try again.'];
        }

        try {
            $this->notifySeller($result['order'], 'Customer accepted cancellation', "Order #{$result['order']->id} is cancelled. Super Admin will process the customer refund.");
        } catch (\Throwable $e) {
            Log::warning('Seller cancellation response notification failed.', ['order_id' => $fresh->id, 'exception' => $e]);
        }

        return ['ok' => true, 'refund' => $result['refund'], 'message' => 'Cancellation accepted. The order is cancelled and Super Admin will process the refund within 3–5 working days.'];
    }

    private function notifySeller(object $order, string $title, string $message): void
    {
        $sellerId = !empty($order->shop_id) ? DB::table('shops')->where('id', $order->shop_id)->value('seller_id') : null;
        if (!$sellerId) return;
        $phone = DB::table('users')->where('id', $sellerId)->value('phone');
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->insert([
                'receiver_role' => 'seller',
                'receiver_user_id' => $sellerId,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'created_at' => now(),
            ]);
        }
        app(MobileNotificationService::class)->notifyUser('seller', (string) $sellerId, $phone, $title, $message, [
            'event' => 'seller_cancellation_response',
            'order_id' => (string) $order->id,
        ], null, route('seller.orders', [], false));
    }
}
