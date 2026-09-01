<?php
namespace App\Http\Controllers;

use App\Helpers\CakeshopHelper;
use App\Helpers\PaymentTransactionHelper;
use App\Helpers\SmsHelper;
use App\Services\OrderRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class TrackingController extends Controller
{
    public function recoverForm()
    {
        $recovery = session('track_recovery', []);
        $orders = collect();

        if (!empty($recovery['verified']) && !empty($recovery['order_ids'])) {
            $orders = $this->recoveryOrdersByIds($recovery['order_ids']);
        }

        return view('guest.recover_tracking', [
            'recovery' => $recovery,
            'orders' => $orders,
            'devMode' => $this->isDevMode(),
        ]);
    }

    public function recoverSubmit(Request $request)
    {
        $action = $request->input('action', 'find');

        return match ($action) {
            'verify' => $this->verifyRecoveryOtp($request),
            'deliver' => $this->deliverRecoveredCode($request),
            'reset' => $this->resetRecovery(),
            default => $this->startRecovery($request),
        };
    }

    private function startRecovery(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'order_date' => ['required', 'date', 'before_or_equal:today'],
            'guest_name' => ['required', 'string', 'min:2', 'max:120'],
            'order_type' => ['nullable', 'in:any,regular,custom'],
        ], [
            'order_date.before_or_equal' => 'Order date cannot be in the future.',
            'guest_name.required' => 'Please enter the name used for the order.',
        ]);

        $phone = $this->normalizePhone($data['phone']);
        if (!$phone) {
            return back()->withInput()->with('error', 'Please enter a valid Philippine mobile number.');
        }

        $rateKey = 'track-recovery-find:' . sha1($request->ip() . '|' . $phone);
        if (RateLimiter::tooManyAttempts($rateKey, 6)) {
            return back()->withInput()->with('error', 'Too many recovery attempts. Please wait a few minutes before trying again.');
        }
        RateLimiter::hit($rateKey, 600);

        $matches = $this->matchingRecoveryOrders($phone, $data['order_date'], $data['guest_name'], $data['order_type'] ?? 'any');
        if ($matches->isEmpty()) {
            return back()->withInput()->with('error', 'We could not continue recovery with those details. Please check your information or contact support.');
        }

        $otpKey = 'track-recovery-otp:' . sha1($request->ip() . '|' . $phone);
        if (RateLimiter::tooManyAttempts($otpKey, 3)) {
            return back()->withInput()->with('error', 'OTP was requested too many times. Please wait a few minutes before trying again.');
        }
        RateLimiter::hit($otpKey, 600);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $recipientPhone = $matches->first()->guest_phone ?: $phone;
        $siteName = config('app.name', 'Cake Shop');
        $smsResult = SmsHelper::sendOtpWithResult($recipientPhone, $otp, $siteName, trim($data['guest_name']), '', '', '');

        if (!$smsResult['ok']) {
            return back()->withInput()->with('error', $smsResult['error'] ?? 'OTP could not be sent. Please try again or contact support.');
        }

        session([
            'track_recovery' => [
                'phone' => $phone,
                'phone_masked' => $this->maskPhone($recipientPhone),
                'order_date' => $data['order_date'],
                'guest_name' => trim($data['guest_name']),
                'order_type' => $data['order_type'] ?? 'any',
                'order_ids' => $matches->pluck('id')->values()->all(),
                'otp_hash' => hash('sha256', $otp),
                'otp_expires_at' => now()->addMinutes(10)->toDateTimeString(),
                'otp_attempts' => 0,
                'verified' => false,
                'step' => 'otp',
            ],
        ]);

        return redirect()->route('track.recover')->with('msg', $this->isDevMode()
            ? 'Developer mode: OTP is shown on this page.'
            : 'OTP sent to the phone number used for this order.');
    }

    private function verifyRecoveryOtp(Request $request)
    {
        $recovery = session('track_recovery', []);
        if (empty($recovery['otp_hash']) || empty($recovery['order_ids'])) {
            return redirect()->route('track.recover')->with('error', 'Please start recovery again.');
        }

        $otp = trim((string) $request->input('otp_code', ''));
        if (!preg_match('/^\d{6}$/', $otp)) {
            return back()->with('error', 'Please enter the 6-digit OTP.');
        }

        if (now()->greaterThan(\Carbon\Carbon::parse($recovery['otp_expires_at'] ?? now()->subMinute()))) {
            session()->forget('track_recovery');
            return redirect()->route('track.recover')->with('error', 'OTP expired. Please start recovery again.');
        }

        $attempts = (int) ($recovery['otp_attempts'] ?? 0) + 1;
        if (!hash_equals((string) $recovery['otp_hash'], hash('sha256', $otp))) {
            $recovery['otp_attempts'] = $attempts;
            session(['track_recovery' => $recovery]);

            if ($attempts >= 5) {
                session()->forget('track_recovery');
                return redirect()->route('track.recover')->with('error', 'Too many wrong OTP attempts. Please start recovery again.');
            }

            return back()->with('error', 'Invalid OTP. Please try again.');
        }

        $recovery['verified'] = true;
        $recovery['step'] = 'deliver';
        unset($recovery['otp_hash']);
        session(['track_recovery' => $recovery]);

        return redirect()->route('track.recover')->with('msg', 'Phone verified. Choose the order you want to recover.');
    }

    private function deliverRecoveredCode(Request $request)
    {
        $recovery = session('track_recovery', []);
        if (empty($recovery['verified']) || empty($recovery['order_ids'])) {
            return redirect()->route('track.recover')->with('error', 'Please verify your phone first.');
        }

        $data = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        if (!in_array($data['order_id'], $recovery['order_ids'], true)) {
            return back()->with('error', 'Please choose one of the verified orders.');
        }

        $order = $this->recoveryOrdersByIds([$data['order_id']])->first();
        if (!$order || empty($order->track_code)) {
            return back()->with('error', 'Selected order is no longer available for recovery.');
        }

        $recovery['selected_order_id'] = $order->id;
        $recovery['recovered_code'] = $order->track_code;
        $recovery['step'] = 'done';
        unset($recovery['delivery_method']);
        session(['track_recovery' => $recovery]);

        return redirect()->route('track.recover')->with('msg', 'Tracking code recovered.');
    }

    private function resetRecovery()
    {
        session()->forget('track_recovery');
        return redirect()->route('track.recover');
    }

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

        $refund = app(OrderRefundService::class)->latestForOrder((string) $order->id);

        return view('guest.track_order', compact(
            'order','tracking','addons','customOrder','statusSteps','currentStep','recentReceipts','receiptCount','refund'
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
            ->select('id','status','payment_status','deposit_status','total_price','deposit_amount','paid_at','deposit_paid_at','updated_at')
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
            'total_price' => (string) round((float) ($order->total_price ?? 0), 2),
            'deposit_amount' => (string) round((float) ($order->deposit_amount ?? 0), 2),
            'paid_at' => (string) ($order->paid_at ?? ''),
            'deposit_paid_at' => (string) ($order->deposit_paid_at ?? ''),
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
                        'o.delivery_address as address',
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
        $receipt->receipt_paid_amount = null;

        try {
            if (Schema::hasTable('payment_transactions') && Schema::hasColumn('payment_transactions', 'amount')) {
                $receipt->receipt_paid_amount = DB::table('payment_transactions')
                    ->where('track_code', strtoupper($trackCode))
                    ->when($transactionId, fn ($q) => $q->where('id', $transactionId))
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->value('amount');
            }
        } catch (\Throwable $e) {}

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
                        'pt.payment_service_fee',
                        'pt.customer_paid_amount',
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
                    'payment_service_fee',
                    'customer_paid_amount',
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

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '63' . $digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '63' . substr($digits, 1);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            // Already normalized.
        } else {
            return null;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '639') ? '+' . $digits : null;
    }

    private function matchingRecoveryOrders(string $phone, string $date, string $name, string $orderType = 'any')
    {
        $variants = $this->phoneVariants($phone);
        $nameKey = $this->nameKey($name);

        return DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('custom_orders as co', 'co.order_id', '=', 'o.id')
            ->whereNotNull('o.guest_phone')
            ->whereNotNull('o.track_code')
            ->whereIn('o.guest_phone', $variants)
            ->whereDate('o.created_at', $date)
            ->when($orderType === 'regular', fn ($q) => $q->whereNull('co.id'))
            ->when($orderType === 'custom', fn ($q) => $q->whereNotNull('co.id'))
            ->select(
                'o.id',
                'o.guest_name',
                'o.guest_phone',
                'o.track_code',
                'o.status',
                'o.total_price',
                'o.created_at',
                DB::raw("COALESCE(co.cake_name, p.name, 'Custom Cake') as product_name"),
                DB::raw("CASE WHEN co.id IS NULL THEN 'Regular Cake Order' ELSE 'Custom Cake Order' END as order_type")
            )
            ->orderByDesc('o.created_at')
            ->limit(12)
            ->get()
            ->filter(fn ($order) => $this->nameMatches($nameKey, $order->guest_name ?? ''))
            ->values();
    }

    private function recoveryOrdersByIds(array $ids)
    {
        if (empty($ids)) return collect();

        return DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('custom_orders as co', 'co.order_id', '=', 'o.id')
            ->whereIn('o.id', $ids)
            ->select(
                'o.id',
                'o.guest_name',
                'o.guest_phone',
                'o.track_code',
                'o.status',
                'o.total_price',
                'o.created_at',
                DB::raw("COALESCE(co.cake_name, p.name, 'Custom Cake') as product_name"),
                DB::raw("CASE WHEN co.id IS NULL THEN 'Regular Cake Order' ELSE 'Custom Cake Order' END as order_type")
            )
            ->orderByDesc('o.created_at')
            ->get();
    }

    private function nameKey(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z\s]/', ' ', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function nameMatches(string $expected, string $actual): bool
    {
        $actual = $this->nameKey($actual);
        if ($expected === '' || $actual === '') return false;
        if ($expected === $actual) return true;

        $expectedParts = array_values(array_filter(explode(' ', $expected)));
        $actualParts = array_values(array_filter(explode(' ', $actual)));
        if (count($expectedParts) >= 2) {
            $first = $expectedParts[0];
            $last = $expectedParts[count($expectedParts) - 1];
            return in_array($first, $actualParts, true) && in_array($last, $actualParts, true);
        }

        return strlen($expected) >= 4 && str_contains($actual, $expected);
    }

    private function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 6) return 'your phone';
        return '+' . substr($digits, 0, 4) . str_repeat('*', max(4, strlen($digits) - 7)) . substr($digits, -3);
    }

    private function isDevMode(): bool
    {
        try {
            return !empty(DB::table('platform_settings')->value('dev_mode'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function requestCancel(Request $request, string $trackCode, OrderRefundService $refunds)
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

        if (in_array($order->status, ['Out for Delivery', 'Delivered', 'Cancelled', 'Picked Up'], true)) {
            return back()->with('error', "Cannot cancel this order because it is already {$order->status}.");
        }

        if (($order->cancel_requested ?? 0) && ($order->cancel_status ?? '') === 'pending') {
            return back()->with('error', 'A cancellation request is already pending for this order.');
        }

        if (!$refunds->hasPaidAmount($order)) {
            $refunds->cancelUnpaid($order, $reason, 'customer');
            CakeshopHelper::logActivity('guest', 'guest', 'Cancel Order', "Order #{$order->id} cancelled before payment - {$reason}");
            return back()->with('msg', 'Order cancelled successfully. No refund is needed because no payment was collected.');
        }

        $gcashName = trim((string) $request->input('refund_gcash_name', ''));
        $gcashNumber = trim((string) $request->input('refund_gcash_number', ''));
        if ($gcashName === '' || $gcashNumber === '') {
            return back()->with('error', 'Please enter the GCash account name and mobile number for refund.')->withInput();
        }
        if (!$refunds->validateGcashNumber($gcashNumber)) {
            return back()->with('error', 'Please enter a valid GCash mobile number, like 09XXXXXXXXX or +639XXXXXXXXX.')->withInput();
        }

        $refund = $refunds->requestPaidRefund($order, [
            'cancel_reason' => $reason,
            'refund_gcash_name' => $gcashName,
            'refund_gcash_number' => $gcashNumber,
        ]);
        $refunds->notifyPaidRequest($order, $refund);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Refund Request - Order #' . $order->id,
            'message'          => ($order->guest_name ?? 'Guest customer') . " wants to cancel paid Order #{$order->id}. Refund to {$gcashName} / {$gcashNumber}. Reason: {$reason}",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        DB::table('messages')->insert([
            'order_id'    => $order->id,
            'sender_role' => 'guest',
            'sender_id'   => null,
            'message'     => "Cancellation/refund request submitted.\n\nReason: {$reason}\nRefund GCash: {$gcashName} / {$gcashNumber}",
            'is_read' => false,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity('guest', 'guest', 'Refund Request', "Order #{$order->id} - {$reason}");
        return back()->with('msg', 'Cancellation and refund request submitted successfully. Waiting for seller review.');
    }

    public function refundReceipt(string $trackCode, int $refundId)
    {
        $refund = DB::table('order_refunds')
            ->where('id', $refundId)
            ->where('track_code', strtoupper($trackCode))
            ->where('status', 'refunded')
            ->first();

        if (!$refund || empty($refund->receipt_path)) abort(404);

        $response = Http::timeout(20)->get($refund->receipt_path);
        if (!$response->successful()) abort(404);

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type') ?: 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="refund-receipt-'.$refund->order_id.'.jpg"',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
