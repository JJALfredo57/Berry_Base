<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostDeliveryReturnService
{
    public const REASONS = ['wrong_item', 'damaged', 'missing_item', 'quality_safety', 'not_as_approved', 'delivery_damage', 'other'];
    private const OPEN_STATUSES = ['submitted', 'seller_responded', 'approved'];

    public function eligibility(object $order): array
    {
        if (!in_array((string) ($order->status ?? ''), ['Delivered', 'Picked Up'], true)) return [false, 'Only delivered or picked-up orders can be reported.'];
        $deliveredAt = $order->delivered_at ?? $order->updated_at ?? null;
        if (!$deliveredAt || Carbon::parse($deliveredAt)->addHours(2)->isPast()) return [false, 'The two-hour post-delivery reporting window has expired.'];
        return [true, null];
    }

    public function paymentBreakdown(string $orderId): array
    {
        $rows = DB::table('payment_transactions')->where('order_id', $orderId)->where('payment_status', 'paid')->get();
        $payment = round((float) $rows->sum('amount'), 2);
        $service = Schema::hasColumn('payment_transactions', 'payment_service_fee') ? round((float) $rows->sum('payment_service_fee'), 2) : 0.0;
        return ['payment' => $payment, 'service_fee' => $service, 'total' => round($payment + $service, 2)];
    }

    public function submit(object $order, string $customerId, array $data, array $evidence): object
    {
        return DB::transaction(function () use ($order, $customerId, $data, $evidence) {
            $fresh = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
            [$eligible, $message] = $this->eligibility($fresh);
            if (!$eligible) throw new \DomainException($message);
            if (DB::table('post_delivery_return_cases')->where('order_id', $fresh->id)->whereIn('status', self::OPEN_STATUSES)->lockForUpdate()->exists()) throw new \DomainException('A return/refund case is already open for this order.');
            $amounts = $this->paymentBreakdown((string) $fresh->id);
            if ($amounts['total'] <= 0) throw new \DomainException('No confirmed customer payment is available for refund.');

            $ledger = DB::table('seller_payout_ledgers')->where('order_id', $fresh->id)->lockForUpdate()->first();
            $hold = 'not_required';
            if ($ledger && in_array($ledger->status, ['pending', 'clearing', 'available'], true)) {
                DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update(['status' => 'disputed', 'notes' => 'Held for post-delivery return case.', 'updated_at' => now()]);
                $hold = 'held';
            } elseif ($ledger && in_array($ledger->status, ['requested', 'processing'], true)) {
                $hold = 'finance_review_required';
            }

            $id = DB::table('post_delivery_return_cases')->insertGetId([
                'case_number' => 'RET-TMP-' . bin2hex(random_bytes(8)), 'order_id' => $fresh->id, 'shop_id' => $fresh->shop_id, 'customer_user_id' => $customerId,
                'seller_user_id' => $fresh->shop_id ? DB::table('shops')->where('id', $fresh->shop_id)->value('seller_id') : null,
                'reason' => $data['reason'], 'description' => $data['description'], 'status' => 'submitted',
                'customer_payment_amount' => $amounts['payment'], 'service_fee_amount' => $amounts['service_fee'], 'requested_amount' => $amounts['total'],
                'refund_gcash_name' => $data['refund_gcash_name'], 'refund_gcash_number' => $data['refund_gcash_number'],
                'held_ledger_id' => $ledger->id ?? null, 'ledger_status_before_hold' => $ledger->status ?? null, 'payout_hold_status' => $hold,
                'delivered_at' => $fresh->delivered_at ?? $fresh->updated_at, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('post_delivery_return_cases')->where('id', $id)->update(['case_number' => 'RET-' . now()->format('Ymd') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
            foreach ($evidence as $item) DB::table('post_delivery_return_evidence')->insert($item + ['return_case_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('order_tracking')->insert(['order_id' => $fresh->id, 'status' => $fresh->status, 'notes' => 'Post-delivery return/refund case submitted.', 'created_at' => now()]);
            return DB::table('post_delivery_return_cases')->where('id', $id)->first();
        });
    }

    public function sellerRespond(int $caseId, string $sellerId, string $response): void
    {
        DB::transaction(function () use ($caseId, $sellerId, $response) {
            $case = DB::table('post_delivery_return_cases')->where('id', $caseId)->lockForUpdate()->first();
            if (!$case || (string) $case->seller_user_id !== $sellerId || !in_array($case->status, ['submitted', 'seller_responded'], true)) throw new \DomainException('This case is not available for seller response.');
            DB::table('post_delivery_return_cases')->where('id', $caseId)->update(['seller_response' => $response, 'status' => 'seller_responded', 'seller_responded_at' => now(), 'updated_at' => now()]);
        });
    }

    public function decide(int $caseId, string $adminId, string $decision, float $amount, ?string $note, ?string $liability): void
    {
        DB::transaction(function () use ($caseId, $adminId, $decision, $amount, $note, $liability) {
            $case = DB::table('post_delivery_return_cases')->where('id', $caseId)->lockForUpdate()->first();
            if (!$case || !in_array($case->status, ['submitted', 'seller_responded'], true)) throw new \DomainException('This case is not awaiting a decision.');
            if ($decision === 'approved' && ($amount <= 0 || $amount > (float) $case->requested_amount)) throw new \DomainException('Approved amount must not exceed the confirmed customer payment plus service fee.');
            $update = ['status' => $decision === 'approved' ? 'approved' : 'rejected', 'decision_note' => $note, 'liability' => $decision === 'approved' ? $liability : null, 'approved_amount' => $decision === 'approved' ? round($amount, 2) : null, 'decided_at' => now(), 'decided_by_user_id' => $adminId, 'updated_at' => now()];
            if ($decision === 'rejected' && $case->held_ledger_id && $case->payout_hold_status === 'held') DB::table('seller_payout_ledgers')->where('id', $case->held_ledger_id)->update(['status' => $case->ledger_status_before_hold, 'notes' => null, 'updated_at' => now()]);
            DB::table('post_delivery_return_cases')->where('id', $caseId)->update($update);
        });
    }

    public function completeRefund(int $caseId, string $adminId, string $reference, string $receiptPath, string $receiptHash): void
    {
        DB::transaction(function () use ($caseId, $adminId, $reference, $receiptPath, $receiptHash) {
            $case = DB::table('post_delivery_return_cases')->where('id', $caseId)->lockForUpdate()->first();
            if (!$case || $case->status !== 'approved') throw new \DomainException('This case is not approved for refund.');
            if (DB::table('post_delivery_return_cases')->where('id', '<>', $caseId)->where('refund_reference', $reference)->exists()) throw new \DomainException('This refund reference is already used.');
            $ledger = $case->held_ledger_id ? DB::table('seller_payout_ledgers')->where('id', $case->held_ledger_id)->lockForUpdate()->first() : null;
            // The platform pays the customer first. Seller-fault recoveries are a
            // separate negative clearing entry, so a refund never waits for the
            // seller's current payout balance and historical paid payouts remain immutable.
            $sellerRecovery = $case->liability === 'seller' ? round((float) $case->approved_amount, 2) : 0.0;
            if ($ledger && $case->payout_hold_status === 'held') {
                DB::table('seller_payout_ledgers')->where('id', $ledger->id)->update([
                    'status' => $case->ledger_status_before_hold ?: 'available',
                    'notes' => 'Original earnings restored; refund recovery is recorded separately.',
                    'updated_at' => now(),
                ]);
            }
            if ($sellerRecovery > 0) {
                $recoveryKey = substr($case->order_id . '-RT' . $caseId, 0, 40);
                if (!DB::table('seller_payout_ledgers')->where('order_id', $recoveryKey)->exists()) {
                    DB::table('seller_payout_ledgers')->insert([
                        'order_id' => $recoveryKey, 'shop_id' => $case->shop_id, 'seller_id' => $case->seller_user_id,
                        'gross_amount' => 0, 'commission_base' => 0, 'commission_rate' => 0, 'commission_amount' => 0,
                        'seller_net_amount' => -$sellerRecovery, 'status' => 'clearing', 'release_at' => now()->addDays(3),
                        'notes' => 'Platform-funded customer refund recovery for ' . $case->case_number,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            DB::table('post_delivery_return_cases')->where('id', $caseId)->update(['status' => 'refunded', 'seller_recovery_amount' => $sellerRecovery, 'platform_cost_amount' => max(0, round((float) $case->approved_amount - $sellerRecovery, 2)), 'refund_reference' => $reference, 'refund_receipt_path' => $receiptPath, 'refund_receipt_hash' => $receiptHash, 'refunded_at' => now(), 'refunded_by_user_id' => $adminId, 'updated_at' => now()]);
        });
    }
}
