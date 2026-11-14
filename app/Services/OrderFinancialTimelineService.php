<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderFinancialTimelineService
{
    /** A single safe, chronological source for customer/seller/admin payment and refund history. */
    public function forOrder(string $orderId, string $audience = 'customer'): array
    {
        $events = [];
        if (Schema::hasTable('payment_transactions')) {
            foreach (DB::table('payment_transactions')->where('order_id', $orderId)->orderBy('paid_at')->get() as $payment) {
                $events[] = ['at' => $payment->paid_at ?? $payment->created_at, 'type' => 'payment', 'title' => 'Payment received', 'amount' => (float) $payment->amount, 'detail' => $payment->method ?? 'Payment'];
            }
        }
        if (Schema::hasTable('order_refunds')) {
            foreach (DB::table('order_refunds')->where('order_id', $orderId)->orderBy('created_at')->get() as $refund) {
                $events[] = ['at' => $refund->created_at, 'type' => 'refund_requested', 'title' => 'Refund requested', 'amount' => (float) $refund->refund_amount, 'detail' => ucfirst(str_replace('_', ' ', (string) $refund->status))];
                if (!empty($refund->seller_approved_at)) $events[] = ['at'=>$refund->seller_approved_at,'type'=>'refund_approved','title'=>'Seller approved refund','amount'=>(float)$refund->refund_amount,'detail'=>'Awaiting platform processing'];
                if (!empty($refund->admin_refund_completed_at) || ($refund->status ?? '') === 'refunded') $events[] = ['at'=>$refund->admin_refund_completed_at ?? $refund->updated_at,'type'=>'refunded','title'=>'Refund completed','amount'=>(float)$refund->refund_amount,'detail'=>$this->reference($refund->reference_number ?? null, $audience)];
            }
        }
        if (Schema::hasTable('order_tracking')) {
            foreach (DB::table('order_tracking')->where('order_id', $orderId)->whereIn('status', ['Cancelled','Refunded'])->orderBy('created_at')->get() as $track) $events[] = ['at'=>$track->created_at,'type'=>'status','title'=>$track->status,'amount'=>null,'detail'=>$track->notes];
        }
        usort($events, fn ($a,$b) => strcmp((string)$a['at'], (string)$b['at']));
        return $events;
    }

    private function reference(?string $reference, string $audience): string
    {
        if (!$reference) return 'Receipt recorded';
        return $audience === 'superadmin' ? 'Reference: '.$reference : 'Reference: ••••'.substr($reference, -4);
    }
}
