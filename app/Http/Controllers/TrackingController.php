<?php
namespace App\Http\Controllers;

use App\Helpers\CakeshopHelper;
use App\Helpers\PaymentTransactionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrackingController extends Controller
{
    public function show(string $trackCode)
    {
        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name', 'p.image_path', 'p.classification')
            ->first();

        if (!$order) abort(404, 'Order not found. Please check your tracking link.');

        // Persist track code in session so accept/cancel price actions work
        // even when the guest arrives via direct link (SMS/email) with no prior session
        session(['guest_track_code' => strtoupper($trackCode)]);

        $tracking = DB::table('order_tracking')
            ->where('order_id', $order->id)
            ->orderBy('created_at')->get();

        $addons = DB::table('order_addons')->where('order_id', $order->id)->get();

        $customOrder = null;
        try {
            $customOrder = DB::table('custom_orders')->where('order_id', $order->id)->first();
        } catch (\Exception $e) {}

        $isPickup    = ($order->fulfillment_type ?? '') === 'Pickup';
        $statusSteps = $isPickup
            ? ['Pending','Confirmed','Preparing','Pickup','Picked Up']
            : ['Pending','Confirmed','Preparing','Out for Delivery','Delivered'];
        $currentStep = array_search($order->status, $statusSteps);
        if ($currentStep === false) $currentStep = 0;
        $receiptQuery = $this->receiptQueryForPhone($order->guest_phone ?? '');
        $receiptCount = (clone $receiptQuery)->count();
        $recentReceipts = $receiptQuery
            ->limit(5)
            ->get();

        return view('guest.track_order', compact(
            'order','tracking','addons','customOrder','statusSteps','currentStep','recentReceipts','receiptCount'
        ));
    }

    public function receipts(Request $request, string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) abort(404, 'Order not found.');

        $search = trim($request->query('search', ''));
        $receipts = $this->receiptQueryForPhone($order->guest_phone ?? '');
        if ($search !== '') {
            if (Schema::hasTable('payment_transactions')) {
                $receipts->where(function ($q) use ($search) {
                    $q->where('o.id', 'like', '%' . $search . '%')
                        ->orWhere('pt.track_code', 'like', '%' . strtoupper($search) . '%')
                        ->orWhere('pt.type', 'like', '%' . strtolower($search) . '%')
                        ->orWhere('p.name', 'like', '%' . $search . '%');
                });
            } else {
                $receipts->where(function ($q) use ($search) {
                    $q->where('o.id', 'like', '%' . $search . '%')
                        ->orWhere('o.track_code', 'like', '%' . strtoupper($search) . '%')
                        ->orWhere('p.name', 'like', '%' . $search . '%');
                });
            }
        }

        return view('guest.receipts', [
            'order' => $order,
            'receipts' => $receipts->paginate(10)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function status(string $trackCode)
    {
        $order = DB::table('orders')
            ->where('track_code', strtoupper($trackCode))
            ->select('id','status','payment_status','deposit_status','updated_at')
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'Order not found.'], 404);
        }

        $trackingCount = DB::table('order_tracking')->where('order_id', $order->id)->count();
        $latestTrackingAt = DB::table('order_tracking')->where('order_id', $order->id)->max('created_at');
        $receiptCount = 0;

        try {
            $phone = DB::table('orders')->where('id', $order->id)->value('guest_phone');
            $receiptCount = $this->receiptQueryForPhone($phone)->count();
        } catch (\Throwable $e) {}

        $final = in_array($order->status, ['Delivered', 'Picked Up', 'Cancelled'], true);
        $active = in_array($order->status, ['Preparing', 'Out for Delivery', 'Pickup'], true);

        return response()->json([
            'ok' => true,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'deposit_status' => $order->deposit_status,
            'tracking_count' => $trackingCount,
            'receipt_count' => $receiptCount,
            'updated_at' => (string) ($order->updated_at ?? ''),
            'latest_tracking_at' => (string) ($latestTrackingAt ?? ''),
            'final' => $final,
            'interval_ms' => $final ? 0 : ($active ? 10000 : 25000),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function receipt(string $trackCode, ?int $transactionId = null)
    {
        try {
            if ($this->hasPaymentTransactions()) {
                $transactionQuery = DB::table('payment_transactions as pt')
                    ->join('orders as o', 'o.id', '=', 'pt.order_id')
                    ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
                    ->where('pt.track_code', strtoupper($trackCode));

                if ($transactionId) {
                    $transactionQuery->where('pt.id', $transactionId);
                }

                $transaction = $transactionQuery
                    ->select(
                        'pt.*',
                        'o.guest_name',
                        'o.guest_phone',
                        'o.fulfillment_type',
                        'o.schedule_date',
                        'o.schedule_time',
                        'o.address',
                        'o.quantity',
                        'o.selected_size',
                        'o.custom_note',
                        DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"),
                        'p.image_path as product_image'
                    )
                    ->orderByDesc('pt.paid_at')
                    ->orderByDesc('pt.id')
                    ->first();

                if ($transaction) {
                    $transaction->type_label = PaymentTransactionHelper::typeLabel($transaction->type);
                    $receiptAddons = DB::table('order_addons')->where('order_id', $transaction->order_id)->get();
                    $vatSettings = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();

                    return view('guest.payment_transaction_receipt', [
                        'trackCode' => $trackCode,
                        'transaction' => $transaction,
                        'receiptAddons' => $receiptAddons,
                        'vatSettings' => $vatSettings,
                    ]);
                }
            }
        } catch (\Throwable $e) {}

        $receipt = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->where(function ($q) {
                $q->where('o.payment_status', 'Paid')
                    ->orWhere('o.payment_status', 'Partial Payment')
                    ->orWhere('o.deposit_status', 'paid');
            })
            ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"), 'p.image_path as product_image')
            ->first();

        if (!$receipt) abort(404, 'Receipt not found.');

        $receiptAddons = DB::table('order_addons')->where('order_id', $receipt->id)->get();
        $vatSettings = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();
        $receipt->deposit_paid_at = $receipt->deposit_paid_at ?: ($receipt->paid_at ?: $receipt->updated_at);
        $receipt->paid_at = $receipt->paid_at ?: ($receipt->deposit_paid_at ?: $receipt->updated_at);

        if (($receipt->payment_status ?? '') === 'Paid') {
            return view('guest.payment_receipt', [
                'success' => true,
                'trackCode' => $trackCode,
                'receipt' => $receipt,
                'receiptAddons' => $receiptAddons,
                'vatSettings' => $vatSettings,
                'pmReference' => null,
            ]);
        }

        return view('guest.deposit_receipt', [
            'trackCode' => $trackCode,
            'receipt' => $receipt,
            'vatSettings' => $vatSettings,
            'pmReference' => null,
        ]);
    }

    private function receiptQueryForPhone(?string $phone)
    {
        $variants = $this->phoneVariants($phone);

        if ($this->hasPaymentTransactions()) {
            try {
                return DB::table('payment_transactions as pt')
                    ->join('orders as o', 'o.id', '=', 'pt.order_id')
                    ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
                    ->whereIn('pt.guest_phone', $variants)
                    ->select(
                        'pt.id as receipt_id',
                        'pt.order_id',
                        'pt.track_code',
                        'pt.type',
                        'pt.method',
                        'pt.amount',
                        'pt.order_total',
                        'pt.remaining_balance',
                        'pt.payment_status',
                        'pt.paid_at',
                        'pt.created_at',
                        DB::raw("COALESCE(p.name, 'Custom Cake') as product_name")
                    )
                    ->orderByDesc('pt.paid_at')
                    ->orderByDesc('pt.id');
            } catch (\Throwable $e) {}
        }

        return DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->whereIn('o.guest_phone', $variants)
            ->where(function ($q) {
                $q->where('o.payment_status', 'Paid')
                    ->orWhere('o.payment_status', 'Partial Payment')
                    ->orWhere('o.deposit_status', 'paid');
            })
            ->select(
                'o.id',
                'o.track_code',
                'o.created_at',
                'o.paid_at',
                'o.deposit_paid_at',
                'o.payment_status',
                'o.deposit_status',
                'o.total_price',
                'o.deposit_amount',
                DB::raw("COALESCE(p.name, 'Custom Cake') as product_name")
            )
            ->orderByRaw('COALESCE(o.paid_at, o.deposit_paid_at, o.updated_at, o.created_at) DESC');
    }

    private function hasPaymentTransactions(): bool
    {
        try {
            return Schema::hasTable('payment_transactions')
                && Schema::hasColumns('payment_transactions', [
                    'order_id',
                    'track_code',
                    'guest_phone',
                    'type',
                    'amount',
                    'order_total',
                    'remaining_balance',
                    'paid_at',
                ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function phoneVariants(?string $phone): array
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) $digits = '63' . $digits;
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) $digits = '63' . substr($digits, 1);
        if (!str_starts_with($digits, '63') && strlen($digits) >= 10) $digits = '63' . substr($digits, -10);

        $local = strlen($digits) === 12 && str_starts_with($digits, '63') ? '0' . substr($digits, 2) : $digits;
        return array_values(array_unique(array_filter([
            $phone,
            $digits,
            '+' . $digits,
            $local,
            preg_replace('/\D/', '', (string) $phone),
        ])));
    }

    public function requestCancel(Request $request, string $trackCode)
    {
        $reason = trim($request->input('cancel_reason', ''));
        if ($reason === '') {
            return back()->with('error', 'Please provide a reason for cancellation.');
        }

        $order = DB::table('orders')
            ->where('track_code', strtoupper($trackCode))
            ->first();

        if (!$order) {
            return back()->with('error', 'Order not found.');
        }

        $hasPaidDeposit = ($order->deposit_status ?? null) === 'paid'
            || in_array(($order->payment_status ?? ''), ['Partial Payment', 'Paid'], true);
        if ($hasPaidDeposit) {
            return back()->with('error', 'Cannot cancel this order because your deposit has already been paid.');
        }

        if (in_array($order->status, ['Preparing', 'Out for Delivery', 'Delivered', 'Cancelled', 'Picked Up'], true)) {
            return back()->with('error', "Cannot cancel this order because it is already {$order->status}.");
        }

        if (($order->cancel_requested ?? 0) && ($order->cancel_status ?? '') === 'pending') {
            return back()->with('error', 'A cancellation request is already pending for this order.');
        }

        DB::table('orders')->where('id', $order->id)->update([
            'cancel_requested'    => 1,
            'cancel_reason'       => $reason,
            'cancel_status'       => 'pending',
            'cancel_admin_note'   => null,
            'cancel_requested_at' => now()->format('Y-m-d H:i:s'),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Cancel Request - Order #' . $order->id,
            'message'          => ($order->guest_name ?? 'Guest customer') . " wants to cancel Order #{$order->id}. Reason: {$reason}",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        DB::table('messages')->insert([
            'order_id'    => $order->id,
            'sender_role' => 'guest',
            'sender_id'   => null,
            'message'     => "Cancel request submitted.\n\nReason: {$reason}",
            'is_read' => false,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity('guest', 'guest', 'Cancel Request', "Order #{$order->id} - {$reason}");
        return back()->with('msg', 'Cancel request submitted successfully. Waiting for admin approval.');
    }
}
