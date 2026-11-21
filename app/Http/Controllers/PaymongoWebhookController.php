<?php

namespace App\Http\Controllers;

use App\Helpers\CakeshopHelper;
use App\Helpers\PaymentTransactionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymongoWebhookController extends Controller
{
    /** Mark a customer order paid only after a signed PayMongo event. */
    public function handle(Request $request)
    {
        $rawPayload = $request->getContent();
        if (! $this->hasValidSignature($request->header('Paymongo-Signature'), $rawPayload)) {
            Log::warning('Rejected PayMongo webhook with invalid signature.');

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON.'], 400);
        }

        $eventType = data_get($payload, 'data.attributes.type') ?: data_get($payload, 'data.type');
        if ($eventType !== 'checkout_session.payment.paid') {
            return response()->json(['ok' => true]);
        }

        $session = data_get($payload, 'data.attributes.data') ?: data_get($payload, 'data.data');
        $sessionId = data_get($session, 'id');
        $paidPayment = collect(data_get($session, 'attributes.payments', []))
            ->first(fn ($payment) => (data_get($payment, 'attributes.status') ?: data_get($payment, 'status')) === 'paid');
        if (! $sessionId || ! is_array($session) || ! $paidPayment) {
            return response()->json(['message' => 'Paid checkout session is missing.'], 422);
        }

        $order = DB::table('orders')
            ->where('deposit_paymongo_id', $sessionId)
            ->orWhere('paymongo_link_id', $sessionId)
            ->first();
        if (! $order) {
            Log::warning('PayMongo webhook has no matching order.', ['checkout_session_id' => $sessionId]);

            return response()->json(['ok' => true]);
        }

        $isDeposit = $order->deposit_paymongo_id === $sessionId;
        $isFullPayment = ! $isDeposit || abs((float) $order->deposit_amount - (float) $order->total_price) < 0.01;
        $amount = $isFullPayment ? (float) $order->total_price : (float) $order->deposit_amount;
        $type = $isFullPayment ? 'full_gcash' : 'downpayment_gcash';
        $checkoutResponse = ['data' => $session];

        DB::transaction(function () use ($order, $isFullPayment, $amount, $type, $checkoutResponse) {
            $fresh = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
            if (! $fresh || ($isFullPayment ? $fresh->payment_status === 'Paid' : $fresh->deposit_status === 'paid')) {
                return;
            }

            $nextStatus = ($fresh->status ?? '') === 'Awaiting Deposit' ? 'Pending' : $fresh->status;
            DB::table('orders')->where('id', $fresh->id)->update([
                'deposit_status' => 'paid',
                'deposit_paid_at' => now(),
                'payment_status' => $isFullPayment ? 'Paid' : 'Partial Payment',
                'paid_at' => $isFullPayment ? now() : null,
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);
            $previousOnline = (float) DB::table('payment_transactions')->where('order_id', $fresh->id)
                ->where('payment_status', 'paid')->whereRaw('UPPER(method) = ?', ['GCASH'])->sum('amount');
            $receiptAmount = $isFullPayment ? max(0, (float) $fresh->total_price - $previousOnline) : $amount;
            $receiptType = $isFullPayment && $previousOnline > 0 ? 'remaining_gcash' : $type;
            PaymentTransactionHelper::record(
                $fresh,
                $receiptType,
                'GCash',
                $receiptAmount,
                null,
                PaymentTransactionHelper::paymongoAmountsFromCheckoutSession($checkoutResponse)
            );
            DB::table('order_tracking')->insert([
                'order_id' => $fresh->id,
                'status' => $isFullPayment ? 'Full Payment Paid' : 'Deposit Paid',
                'notes' => 'Payment confirmed by signed PayMongo webhook.',
                'created_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    private function hasValidSignature(?string $header, string $payload): bool
    {
        $secret = (string) config('paymongo.webhook_secret', '');
        if ($secret === '' || ! $header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key && $value !== null) {
                $parts[$key] = $value;
            }
        }
        $timestamp = $parts['t'] ?? null;
        $signature = CakeshopHelper::getPaymongoMode() === 'live' ? ($parts['li'] ?? '') : ($parts['te'] ?? '');
        if (! $timestamp || ! $signature || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
    }
}
