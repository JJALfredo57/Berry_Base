<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderRefundService
{
    public function __construct(private MobileNotificationService $notifications)
    {
    }

    public function hasPaidAmount(object $order): bool
    {
        return $this->paidAmount($order) > 0;
    }

    public function paidAmount(object $order): float
    {
        if (($order->payment_status ?? '') === 'Paid') {
            return round((float) ($order->total_price ?? 0), 2);
        }

        if (($order->deposit_status ?? '') === 'paid' || ($order->payment_status ?? '') === 'Partial Payment') {
            return round((float) ($order->deposit_amount ?? 0), 2);
        }

        return 0.0;
    }

    public function cancelUnpaid(object $order, string $reason, string $actor = 'guest'): void
    {
        DB::transaction(function () use ($order, $reason, $actor) {
            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'Cancelled',
                'cancel_requested' => 1,
                'cancel_reason' => $reason,
                'cancel_status' => 'accepted',
                'cancel_admin_note' => 'Cancelled before payment. No refund needed.',
                'cancel_requested_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => 'Cancelled',
                'notes' => ucfirst($actor) . ' cancelled this order before payment. No refund needed.',
                'created_at' => now(),
            ]);
        });

        $fresh = DB::table('orders')->where('id', $order->id)->first() ?: $order;
        app(ProductStockService::class)->releaseForOrder($fresh);
        $this->notifyStaff($fresh, 'Order Cancelled Before Payment', "Order #{$order->id} was cancelled before any payment was collected.", [
            'event' => 'cancelled_unpaid',
            'order_id' => (string) $order->id,
        ]);
        $this->notifications->notifyOrderCustomer($fresh, 'Order Cancelled', "Order #{$order->id} was cancelled. No refund is needed.", [
            'event' => 'cancelled_unpaid',
        ]);
    }

    public function requestPaidRefund(object $order, array $data): object
    {
        $paidAmount = $this->paidAmount($order);
        $sellerId = !empty($order->shop_id)
            ? DB::table('shops')->where('id', $order->shop_id)->value('seller_id')
            : null;

        return DB::transaction(function () use ($order, $data, $paidAmount, $sellerId) {
            $refundId = DB::table('order_refunds')->insertGetId([
                'order_id' => $order->id,
                'shop_id' => $order->shop_id ?? null,
                'seller_user_id' => $sellerId,
                'track_code' => $order->track_code ?? null,
                'customer_name' => $order->guest_name ?? $data['customer_name'] ?? null,
                'customer_phone' => $order->guest_phone ?? null,
                'refund_gcash_name' => $data['refund_gcash_name'],
                'refund_gcash_number' => $data['refund_gcash_number'],
                'cancel_reason' => $data['cancel_reason'],
                'refund_amount' => $paidAmount,
                'payment_amount_paid' => $paidAmount,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->where('id', $order->id)->update([
                'cancel_requested' => 1,
                'cancel_reason' => $data['cancel_reason'],
                'cancel_status' => 'pending',
                'cancel_admin_note' => null,
                'cancel_requested_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => $order->status,
                'notes' => 'Cancellation and refund request submitted. Refund GCash details are waiting for seller review.',
                'created_at' => now(),
            ]);

            return DB::table('order_refunds')->where('id', $refundId)->first();
        });
    }

    public function recordNoShow(object $order, string $note, string $actor, ?string $evidencePath = null): ?object
    {
        $refund = DB::transaction(function () use ($order, $note, $actor, $evidencePath) {
            $fresh = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
            if (!$fresh) {
                throw new \RuntimeException('Order not found while recording a no-show.');
            }

            if (($fresh->status ?? '') === 'No Show') {
                return $this->pendingForOrder((string) $fresh->id);
            }

            $paidAmount = $this->paidAmount($fresh);
            $retainedDeposit = min($paidAmount, round((float) ($fresh->total_price ?? 0) * 0.5, 2));
            $refundAmount = round(max(0, $paidAmount - $retainedDeposit), 2);

            if ($refundAmount > 0 && !Schema::hasTable('order_refunds')) {
                throw new \RuntimeException('Refund tracking is not available; the no-show refund cannot be recorded.');
            }

            DB::table('orders')->where('id', $fresh->id)->update([
                'status' => 'No Show',
                'issue_type' => 'no_show',
                'issue_photo' => $evidencePath,
                'issue_note' => $note,
                'issue_status' => 'resolved',
                'issue_reported_at' => now(),
                'rider_pin' => null,
                'updated_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $fresh->id,
                'status' => 'No Show',
                'notes' => ucfirst($actor) . ' recorded a customer no-show. ' . $note,
                'created_at' => now(),
            ]);

            if ($refundAmount <= 0) {
                return null;
            }

            $existing = $this->pendingForOrder((string) $fresh->id);
            if ($existing) {
                return $existing;
            }

            $refundId = DB::table('order_refunds')->insertGetId([
                'order_id' => $fresh->id,
                'shop_id' => $fresh->shop_id ?? null,
                'seller_user_id' => !empty($fresh->shop_id)
                    ? DB::table('shops')->where('id', $fresh->shop_id)->value('seller_id')
                    : null,
                'track_code' => $fresh->track_code ?? null,
                'customer_name' => $fresh->guest_name ?? $fresh->fullname ?? null,
                'customer_phone' => $fresh->guest_phone ?? null,
                'cancel_reason' => 'No-show balance refund: return the amount above the retained 50% deposit. ' . $note,
                'refund_amount' => $refundAmount,
                'payment_amount_paid' => $paidAmount,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('order_refunds')->where('id', $refundId)->first();
        });

        $freshOrder = DB::table('orders')->where('id', $order->id)->first() ?: $order;
        $staffMessage = 'Order #' . $order->id . ' was recorded as a customer no-show. The 50% deposit is retained.';
        $customerMessage = 'The order was recorded as a no-show. The 50% deposit is retained.';
        if ($refund) {
            $staffMessage .= ' A refund of PHP ' . number_format((float) $refund->refund_amount, 2) . ' above the retained deposit is waiting for review.';
            $customerMessage .= ' Any amount paid above the deposit is queued for manual GCash refund review.';
        }
        $this->notifyStaff($freshOrder, $refund ? 'No-Show Refund Needs Review' : 'Customer No-Show', $staffMessage, [
            'event' => $refund ? 'no_show_refund_request' : 'customer_no_show',
            'order_id' => (string) $order->id,
            'refund_id' => $refund ? (string) $refund->id : null,
        ]);
        $this->notifications->notifyOrderCustomer($freshOrder, 'Customer No-Show Recorded', $customerMessage, [
            'event' => $refund ? 'no_show_refund_request' : 'customer_no_show',
            'refund_id' => $refund ? (string) $refund->id : null,
        ]);

        return $refund;
    }

    public function reject(object $order, object $refund, string $note, string $role, string $userId): void
    {
        DB::transaction(function () use ($order, $refund, $note, $role, $userId) {
            DB::table('order_refunds')->where('id', $refund->id)->update([
                'status' => 'rejected',
                'reviewed_by_role' => $role,
                'reviewed_by_user_id' => $userId,
                'review_note' => $note,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->where('id', $order->id)->update([
                'cancel_status' => 'rejected',
                'cancel_admin_note' => $note,
                'updated_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => $order->status,
                'notes' => 'Cancellation/refund request rejected. Reason: ' . $note,
                'created_at' => now(),
            ]);
        });

        $fresh = DB::table('orders')->where('id', $order->id)->first() ?: $order;
        $this->notifications->notifyOrderCustomer($fresh, 'Refund Request Rejected', "Your cancellation request for Order #{$order->id} was rejected. Reason: {$note}", [
            'event' => 'refund_rejected',
            'refund_id' => (string) $refund->id,
        ]);
    }

    public function markRefunded(object $order, object $refund, string $receiptPath, ?string $referenceNumber, string $note, string $role, string $userId, ?string $gcashName = null, ?string $gcashNumber = null): void
    {
        DB::transaction(function () use ($order, $refund, $receiptPath, $referenceNumber, $note, $role, $userId, $gcashName, $gcashNumber) {
            $preserveNoShow = ($order->status ?? '') === 'No Show';
            DB::table('order_refunds')->where('id', $refund->id)->update([
                'status' => 'refunded',
                'reviewed_by_role' => $role,
                'reviewed_by_user_id' => $userId,
                'review_note' => $note,
                'reviewed_at' => $refund->reviewed_at ?? now(),
                'receipt_path' => $receiptPath,
                'reference_number' => $referenceNumber,
                'refund_gcash_name' => $gcashName ?: ($refund->refund_gcash_name ?? null),
                'refund_gcash_number' => $gcashNumber ?: ($refund->refund_gcash_number ?? null),
                'receipt_uploaded_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->where('id', $order->id)->update([
                'status' => $preserveNoShow ? 'No Show' : 'Cancelled',
                'cancel_status' => $preserveNoShow ? ($order->cancel_status ?? null) : 'accepted',
                'cancel_admin_note' => $preserveNoShow ? ($order->cancel_admin_note ?? null) : ($note ?: 'Cancellation approved and refund sent.'),
                'updated_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => 'Refunded',
                'notes' => 'Refund sent via GCash. ' . ($referenceNumber ? 'Reference: ' . $referenceNumber : ''),
                'created_at' => now(),
            ]);
        });

        $this->createRefundLedger($order, $refund);

        $fresh = DB::table('orders')->where('id', $order->id)->first() ?: $order;
        if (($fresh->status ?? '') !== 'No Show') {
            app(ProductStockService::class)->releaseForOrder($fresh);
        }
        $this->notifications->notifyOrderCustomer($fresh, 'Refund Sent', "Your refund for Order #{$order->id} has been sent. You can view the receipt on your tracking page.", [
            'event' => 'refund_sent',
            'refund_id' => (string) $refund->id,
        ]);
        $this->notifyStaff($fresh, 'Refund Completed', "Refund for Order #{$order->id} was marked sent.", [
            'event' => 'refund_sent',
            'refund_id' => (string) $refund->id,
        ]);
    }

    public function latestForOrder(string $orderId): ?object
    {
        if (!Schema::hasTable('order_refunds')) return null;
        return DB::table('order_refunds')->where('order_id', $orderId)->orderByDesc('id')->first();
    }

    public function pendingForOrder(string $orderId): ?object
    {
        if (!Schema::hasTable('order_refunds')) return null;
        return DB::table('order_refunds')->where('order_id', $orderId)->where('status', 'pending')->orderByDesc('id')->first();
    }

    public function validateGcashNumber(string $number): bool
    {
        return (bool) preg_match('/^(09\d{9}|\+639\d{9})$/', trim($number));
    }

    public function notifyPaidRequest(object $order, object $refund): void
    {
        $amount = number_format((float) $refund->refund_amount, 2);
        $this->notifyStaff($order, 'Paid Cancellation Needs Review', "Order #{$order->id} has a paid cancellation/refund request for PHP {$amount}.", [
            'event' => 'refund_request',
            'order_id' => (string) $order->id,
            'refund_id' => (string) $refund->id,
        ]);
    }

    private function createRefundLedger(object $order, object $refund): void
    {
        if (!Schema::hasTable('seller_payout_ledgers') || empty($order->shop_id)) return;
        if (!empty($refund->payout_ledger_id)) return;

        $ledgerKey = substr((string) $order->id . '-RF' . $refund->id, 0, 40);
        if (DB::table('seller_payout_ledgers')->where('order_id', $ledgerKey)->exists()) return;

        $shop = DB::table('shops')->where('id', $order->shop_id)->first();
        $amount = round((float) ($refund->refund_amount ?? 0), 2);
        if (!$shop || $amount <= 0) return;

        $ledgerId = DB::table('seller_payout_ledgers')->insertGetId([
            'order_id' => $ledgerKey,
            'shop_id' => $order->shop_id,
            'seller_id' => $shop->seller_id,
            'gross_amount' => -$amount,
            'commission_base' => 0,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'seller_net_amount' => -$amount,
            'status' => 'available',
            'release_at' => now(),
            'notes' => 'Refund deduction for Order #' . $order->id . '.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_refunds')->where('id', $refund->id)->update([
            'payout_ledger_id' => $ledgerId,
            'updated_at' => now(),
        ]);
    }

    private function notifyStaff(object $order, string $title, string $message, array $data): void
    {
        DB::table('notifications')->insert([
            'receiver_role' => 'admin',
            'receiver_user_id' => null,
            'title' => $title . ' - Order #' . $order->id,
            'message' => $message,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $sellerId = !empty($order->shop_id) ? DB::table('shops')->where('id', $order->shop_id)->value('seller_id') : null;
        if ($sellerId) {
            $phone = DB::table('users')->where('id', $sellerId)->value('phone');
            $this->notifications->notifyUser('seller', (string) $sellerId, $phone, $title, $message, $data, null, route('seller.orders', [], false));
        }

        $admins = DB::table('users')->whereIn('role', ['admin', 'superadmin'])->select('id', 'phone', 'role')->get();
        foreach ($admins as $admin) {
            $adminUrl = $admin->role === 'superadmin'
                ? route('superadmin.dashboard', [], false)
                : route('admin.orders.index', [], false);
            $this->notifications->notifyUser($admin->role, (string) $admin->id, $admin->phone ?? null, $title, $message, $data, null, $adminUrl);
        }
    }
}
