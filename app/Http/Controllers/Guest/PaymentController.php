<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\PaymentTransactionHelper;
use App\Services\MobileNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentController extends Controller
{
    public function payGcash(string $trackCode)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();

        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"))
            ->first();

        if (!$order) abort(404);

        if ($order->payment_status === 'Paid') {
            return redirect()->route('track.order', $trackCode)
                ->with('msg', 'This order has already been paid.');
        }

        if ($order->payment_method !== 'GCash') {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'This order is set to Cash on Delivery.');
        }

        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY')) {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'GCash payment is not configured yet. Please contact the shop.');
        }

        $amountCentavos = (int) round((float) $order->total_price * 100);
        if ($amountCentavos < 10000) {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Minimum GCash payment is ₱100.00.');
        }

        $mobileParam = $this->mobileReturnParam();
        $successUrl = url('/track/' . $trackCode . '/payment-return?status=success' . $mobileParam);
        $cancelUrl  = url('/track/' . $trackCode . '/payment-return?status=cancelled' . $mobileParam);

        // PayMongo checkout already renders +63, so send only 9XXXXXXXXX.
        $rawPhone = $order->guest_phone ?? '';
        $phone    = $this->formatPaymongoCheckoutPhone($rawPhone);

        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => [
                        'name'  => $order->guest_name ?? 'Customer',
                        'phone' => $phone,
                    ],
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount'   => $amountCentavos,
                        'name'     => $order->product_name . ' — Order #' . $order->id,
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => $this->getPaymongoCheckoutMethods(),
                    'pass_on_fees'         => true,
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'description'          => 'Order #' . $order->id,
                    'reference_number'     => 'ORDER-' . $order->id,
                    'send_email_receipt'   => false,
                    'show_description'     => true,
                    'show_line_items'      => true,
                ],
            ],
        ];

        $ch = curl_init('https://api.paymongo.com/v2/checkout_sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'accept: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $res      = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('Guest GCash', ['track' => $trackCode, 'http' => $httpCode, 'res' => $res]);

        if ($errno) {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Network error connecting to PayMongo. Try again.');
        }

        $data = json_decode($res, true);

        if (isset($data['errors'])) {
            $errMsg = $data['errors'][0]['detail'] ?? 'PayMongo API error.';
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'PayMongo: ' . $errMsg);
        }

        $sessionId   = $data['data']['id'] ?? null;
        $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

        if (!$sessionId || !$checkoutUrl) {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Could not create GCash session. Check PayMongo keys.');
        }

        DB::table('orders')->where('id', $order->id)->update([
            'paymongo_link_id' => $sessionId,
        ]);

        return redirect()->away($checkoutUrl);
    }


    /**
     * Customer sets their own deposit amount (min 50%, max 100% of total)
     * then redirects to PayMongo GCash payment
     */
    public function setDeposit(Request $request, string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return back()->with('error', 'Order not found.');

        if (($order->status ?? '') === 'Cancelled' || in_array(($order->cancel_status ?? ''), ['pending', 'accepted'], true)) {
            return back()->with('error', 'Payment is no longer available because this order has been cancelled or has a pending cancellation request.');
        }

        if (!in_array($order->status, ['Pending', 'Pending Review', 'Awaiting Deposit']))
            return back()->with('error', 'This order cannot be modified at this stage.');

        if ($order->payment_status === 'Paid')
            return back()->with('error', 'This order is already fully paid.');

        $payableTotal = (float) $order->total_price;
        try {
            $customOrder = DB::table('custom_orders')->where('order_id', $order->id)->first();
            if ($customOrder && $customOrder->review_status === 'approved'
                && $customOrder->price_confirmed === 'accepted'
                && (float) $customOrder->admin_price > 0) {
                $payableTotal = (float) $customOrder->admin_price;
            }
        } catch (\Throwable $e) {}

        $depositAmount = round((float) $request->input('deposit_amount', 0), 2);
        $minDeposit    = round($payableTotal * 0.5, 2);
        $maxDeposit    = round($payableTotal, 2);

        if ($maxDeposit <= 0) {
            return back()->with('error', 'Invalid order total. Please contact the shop.');
        }
        if ($depositAmount < 100) {
            return back()->with('error', 'Minimum GCash payment is PHP 100.00.');
        }

        // Validate: must be at least 50% and at most 100%
        if ($depositAmount < $minDeposit) {
            return back()->with('error', 'Minimum deposit is 50% of total (₱' . number_format($minDeposit, 2) . ').');
        }
        if ($depositAmount > $maxDeposit) {
            return back()->with('error', 'Payment cannot exceed the order total (PHP ' . number_format($maxDeposit, 2) . ').');
        }

        $isFullPayment = abs($depositAmount - $maxDeposit) < 0.01;

        // All payment methods (COP, COD, GCash) pay the deposit through PayMongo.
        DB::table('orders')->where('id', $order->id)->update([
            'deposit_required' => 1,
            'deposit_amount'   => $depositAmount,
            'total_price'       => $payableTotal,
            'deposit_status'   => 'pending',
        ]);

        try {
            DB::table('order_tracking')->insert([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'notes'      => $isFullPayment
                    ? "Customer chose to pay full amount ₱{$depositAmount} via GCash (PayMongo)."
                    : "Customer set deposit of ₱{$depositAmount} via GCash (min 50%).",
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {}

        // Redirect to PayMongo for actual payment
        return redirect()->route('guest.pay_deposit', $trackCode);
    }

    public function payDeposit(string $trackCode)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();

        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"))
            ->first();

        if (!$order) abort(404);
        if (($order->status ?? '') === 'Cancelled' || in_array(($order->cancel_status ?? ''), ['pending', 'accepted'], true)) {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Payment is no longer available because this order has been cancelled or has a pending cancellation request.');
        }
        if (!$order->deposit_required)
            return redirect()->route('track.order', $trackCode)->with('error', 'No deposit required for this order.');
        if ($order->deposit_status === 'paid')
            return redirect()->route('track.order', $trackCode)->with('msg', 'Deposit has already been paid.');
        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY'))
            return redirect()->route('track.order', $trackCode)->with('error', 'GCash payment is not configured yet.');

        $amountCentavos = (int) round((float) $order->deposit_amount * 100);
        if ($amountCentavos < 10000)
            return redirect()->route('track.order', $trackCode)->with('error', 'Minimum GCash payment is ₱100.00.');

        $mobileParam = $this->mobileReturnParam();
        $successUrl = url('/track/' . $trackCode . '/deposit-return?status=success' . $mobileParam);
        $cancelUrl  = url('/track/' . $trackCode . '/deposit-return?status=cancelled' . $mobileParam);

        $phone = $this->formatPaymongoCheckoutPhone($order->guest_phone ?? '');

        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => [
                        'name'  => $order->guest_name ?? 'Customer',
                        'phone' => $phone,
                    ],
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount'   => $amountCentavos,
                        'name'     => 'Deposit — ' . $order->product_name . ' (Order #' . $order->id . ')',
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => $this->getPaymongoCheckoutMethods(),
                    'pass_on_fees'         => true,
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'description'          => 'Deposit for Order #' . $order->id,
                    'reference_number'     => 'DEP-' . $order->id,
                    'send_email_receipt'   => false,
                    'show_description'     => true,
                    'show_line_items'      => true,
                ],
            ],
        ];

        $ch = curl_init('https://api.paymongo.com/v2/checkout_sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'accept: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $res      = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno)
            return redirect()->route('track.order', $trackCode)->with('error', 'Network error. Please try again.');

        $data        = json_decode($res, true);
        $sessionId   = $data['data']['id'] ?? null;
        $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

        if (!$sessionId || !$checkoutUrl) {
            $errMsg = $data['errors'][0]['detail'] ?? 'Could not create payment session.';
            return redirect()->route('track.order', $trackCode)->with('error', $errMsg);
        }

        DB::table('orders')->where('id', $order->id)->update([
            'deposit_paymongo_id' => $sessionId,
        ]);

        return redirect()->away($checkoutUrl);
    }

    public function depositReturn(Request $request, string $trackCode)
    {
        $urlStatus = $request->input('status', '');

        // Use left join so orders without a product (e.g. custom) still load
        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
            ->first();

        if (!$order) abort(404);

        if ($urlStatus === 'cancelled')
            return redirect()->route('track.order', $trackCode)->with('error', 'Deposit payment cancelled. You can try again.');

        // Idempotency — already paid, just send to kitchen if not yet done
        if ($order->deposit_status === 'paid') {
            if (!$order->kitchen_sent) {
                $this->sendToKitchen($order);
            }
            return redirect()->route('track.order', $trackCode)->with('msg', 'Deposit already paid!');
        }

        // Verify payment with PayMongo API
        $secretKey     = CakeshopHelper::getPaymongoSecretKey();
        $sessionStatus = '';
        $paymentStatus = '';
        $pmReference   = null;
        $apiVerified   = false;

        if ($order->deposit_paymongo_id && $secretKey) {
            try {
                $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . $order->deposit_paymongo_id);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        'accept: application/json',
                        'Authorization: Basic ' . base64_encode($secretKey . ':'),
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 30,
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
                $res = $raw ? json_decode($raw, true) : null;

                $sessionStatus = $res['data']['attributes']['status'] ?? '';
                $paymentStatus = $res['data']['attributes']['payment_intent']['attributes']['status'] ?? '';
                $pmReference   = $res['data']['attributes']['payments'][0]['attributes']['reference_number']
                                 ?? ($res['data']['attributes']['reference_number'] ?? null);
                $apiVerified   = in_array($sessionStatus, ['completed', 'active'])
                                 || $paymentStatus === 'succeeded';
            } catch (\Exception $e) {}
        }

        // Trust PayMongo's success_url redirect if API check is inconclusive but deposit was initiated
        $paymentConfirmed = ($sessionStatus === 'completed' || $paymentStatus === 'succeeded')
            || ($apiVerified && $order->deposit_required)
            || ($urlStatus === 'success' && $order->deposit_required && $order->deposit_paymongo_id);

        if (!$paymentConfirmed)
            return redirect()->route('track.order', $trackCode)->with('error', 'Payment could not be confirmed. Please contact the shop if payment was deducted.');

        // ── Mark deposit as paid ────────────────────────────────────────
        $isFullPayment = abs((float)$order->deposit_amount - (float)$order->total_price) < 0.01;
        DB::table('orders')->where('id', $order->id)->update([
            'deposit_status'  => 'paid',
            'deposit_paid_at' => now(),
            'payment_status'  => $isFullPayment ? 'Paid' : 'Partial Payment',
            'paid_at'         => $isFullPayment ? now() : null,
        ]);
        PaymentTransactionHelper::record(
            $order,
            $isFullPayment ? 'full_gcash' : 'downpayment_gcash',
            'GCash',
            (float) $order->deposit_amount,
            $pmReference
        );
        try {
            $pushOrder = DB::table('orders')->where('id', $order->id)->first();
            if ($pushOrder) {
                app(MobileNotificationService::class)->notifyPaymentComplete($pushOrder);
            }
        } catch (\Throwable $e) {
            Log::warning('Guest deposit payment push failed: ' . $e->getMessage());
        }

        try {
            DB::table('order_tracking')->insert([
                'order_id'   => $order->id,
                'status'     => 'Deposit Paid',
                'notes'      => 'Deposit of ₱' . number_format($order->deposit_amount, 2) . ' paid via GCash.',
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {}

        // ── Move 'Awaiting Deposit' → 'Pending' so seller can confirm ──
        if ($order->status === 'Awaiting Deposit') {
            DB::table('orders')->where('id', $order->id)->update(['status' => 'Pending']);
            try {
                DB::table('order_tracking')->insert([
                    'order_id'   => $order->id,
                    'status'     => 'Pending',
                    'notes'      => 'Order activated and pending baker confirmation.',
                    'created_at' => now(),
                ]);
            } catch (\Exception $e) {}
        }

        // ── Auto-confirm custom orders already in pending state ─────────
        if (in_array($order->status, ['Pending', 'Pending Review'])) {
            DB::table('orders')->where('id', $order->id)->update(['status' => 'Confirmed']);
            try {
                DB::table('order_tracking')->insert([
                    'order_id'   => $order->id,
                    'status'     => 'Confirmed',
                    'notes'      => 'Auto-confirmed after deposit payment via GCash.',
                    'created_at' => now(),
                ]);
            } catch (\Exception $e) {}
        }

        // ── Send to kitchen ─────────────────────────────────────────────
        if (!$order->kitchen_sent) {
            // Reload order so product_name is fresh after potential join issue
            $freshOrder = DB::table('orders as o')
                ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.id', $order->id)
                ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
                ->first();
            $this->sendToKitchen($freshOrder ?? $order, $isFullPayment);
        }

        // ── Notify admin ────────────────────────────────────────────────
        try {
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'Deposit Paid - Order #' . $order->id,
                'message'          => ($order->guest_name ?? 'Guest') . ' paid PHP ' . number_format($order->deposit_amount, 2) . ' for Order #' . $order->id . '. Auto-confirmed.',
                'is_read' => false,
                'created_at'       => now(),
            ]);
        } catch (\Exception $e) {}

        // ── SMS to customer ─────────────────────────────────────────────
        try {
            $guestPhone = $order->guest_phone ?? null;
            if ($guestPhone) {
                $siteName  = config('app.name', 'Cake Shop');
                $shopName  = \App\Helpers\SmsHelper::getShopName($order->shop_id ?? null);
                $header    = \App\Helpers\SmsHelper::header($siteName, $shopName);
                $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
                $guestName = $order->guest_name ?? 'Customer';
                $hasMobileDevice = \Illuminate\Support\Facades\Schema::hasTable('device_sessions')
                    && DB::table('device_sessions')
                        ->where('role', 'guest_customer')
                        ->where('guest_track_code', strtoupper($trackCode))
                        ->where('is_push_enabled', true)
                        ->whereNull('revoked_at')
                        ->exists();
                if (!$hasMobileDevice) \App\Helpers\SmsHelper::send($guestPhone,
                    "{$header}\n"
                    . "Hi {$guestName}! Your payment has been received.\n\n"
                    . "Order No.: #{$order->id}{$shopLine}\n"
                    . 'Amount Paid: PHP ' . number_format($order->deposit_amount, 2) . "\n"
                    . "Status: Confirmed\n\n"
                    . "Your Tracking Code: {$trackCode}\n"
                    . 'Use this code to track your order on our website.'
                );
            }
        } catch (\Exception $e) {}

        // ── Return receipt ──────────────────────────────────────────────
        $receipt = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $order->id)
            ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
            ->first();

        $vatSettings = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();

        if ($appReturn = $this->mobileAppReturn($request, '/track/' . $trackCode . '?payment=success')) return $appReturn;

        return view('guest.deposit_receipt', [
            'trackCode'   => $trackCode,
            'receipt'     => $receipt ?? $order,
            'vatSettings' => $vatSettings,
            'pmReference' => $pmReference,
        ]);
    }

    private function sendToKitchen(object $order, bool $isFullPayment = false): void
    {
        try {
            $addons    = DB::table('order_addons')->where('order_id', $order->id)->get();
            $addonList = $addons->count() > 0
                ? "\nADD-ONS:\n" . $addons->map(fn($a) => '  • ' . $a->addon_name . ($a->addon_price > 0 ? ' (+₱' . $a->addon_price . ')' : ' (FREE)'))->implode("\n")
                : '';

            $productName = $order->product_name ?? DB::table('products')->where('id', $order->product_id)->value('name') ?? 'Custom Cake';
            $fullname    = $order->guest_name ?? DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Guest';
            $phone       = $order->guest_phone ?? DB::table('users')->where('id', $order->user_id)->value('phone') ?? '';
            $sizeInfo    = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
            $noteInfo    = $order->custom_note   ? "\nSPECIAL NOTE: {$order->custom_note}" : '';
            $schedInfo   = $order->schedule_date
                ? "\nSCHEDULE: " . date('M d, Y', strtotime($order->schedule_date)) .
                  ($order->schedule_time ? ' at ' . date('g:i A', strtotime($order->schedule_time)) : '')
                : '';
            if ($order->payment_method === 'GCash') {
                $payLine = $isFullPayment
                    ? 'GCash Full PHP ' . number_format($order->deposit_amount, 2) . ' - Fully Paid'
                    : 'GCash Deposit PHP ' . number_format($order->deposit_amount, 2) . ' - Paid (Balance remaining)';
            } else {
                $payLine = CakeshopHelper::shortPaymentCode($order->payment_method, $order->fulfillment_type ?? null)
                    . ' Deposit PHP ' . number_format($order->deposit_amount, 2) . ' acknowledged';
            }

            DB::table('kitchen_tickets')->where('order_id', $order->id)->delete();
            DB::table('kitchen_tickets')->insert([
                'shop_id'       => $order->shop_id ?? null,
                'order_id'      => $order->id,
                'product_name'  => $productName,
                'product_image' => $order->product_image ?? null,
                'quantity'      => $order->quantity ?? 1,
                'instructions'  => "=== KITCHEN ORDER TICKET ===\nOrder #: {$order->id}\nCustomer: {$fullname}" . ($phone ? " ({$phone})" : '') . "\nProduct: {$productName}\nQty: {$order->quantity}{$sizeInfo}{$noteInfo}{$addonList}{$schedInfo}\nFulfillment: {$order->fulfillment_type}\nPayment: {$payLine}\n===========================",
                'status'        => 'pending',
                'sent_at'       => now()->format('Y-m-d H:i:s'),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => true]);
        } catch (\Exception $e) {}
    }

    public function payRemaining(string $trackCode)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();

        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"))
            ->first();

        if (!$order) abort(404);
        if ($order->payment_method !== 'GCash')
            return redirect()->route('track.order', $trackCode)->with('error', 'This order uses COD payment.');
        if ($order->payment_status === 'Paid')
            return redirect()->route('track.order', $trackCode)->with('msg', 'This order has already been fully paid.');

        // Allowed statuses for payment
        $isPickup   = $order->fulfillment_type === 'Pickup';
        $payStatus  = $isPickup ? 'Pickup' : 'Out for Delivery';
        if ($order->status !== $payStatus)
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Payment is not available at this stage yet.');

        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY'))
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'GCash payment is not configured yet.');

        // Calculate amount to pay
        $depositPaid = $order->deposit_status === 'paid';
        $payAmount   = $depositPaid
            ? max(0, (float)$order->total_price - (float)$order->deposit_amount)
            : (float)$order->total_price;

        $amountCentavos = (int) round($payAmount * 100);
        if ($amountCentavos < 10000)
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Minimum GCash payment is ₱100.00.');

        $label      = $depositPaid ? 'Remaining Balance' : 'Full Payment';
        $mobileParam = $this->mobileReturnParam();
        $successUrl = url('/track/' . $trackCode . '/remaining-return?status=success' . $mobileParam);
        $cancelUrl  = url('/track/' . $trackCode . '/remaining-return?status=cancelled' . $mobileParam);
        $phone      = $this->formatPaymongoCheckoutPhone($order->guest_phone ?? '');

        $payload = [
            'data' => [
                'attributes' => [
                    'billing'    => ['name' => $order->guest_name ?? 'Customer', 'phone' => $phone],
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount'   => $amountCentavos,
                        'name'     => "{$label} — {$order->product_name} (Order #{$order->id})",
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => $this->getPaymongoCheckoutMethods(),
                    'pass_on_fees'         => true,
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'description'          => "{$label} for Order #{$order->id}",
                    'reference_number'     => 'REM-' . $order->id,
                    'send_email_receipt'   => false,
                    'show_description'     => true,
                    'show_line_items'      => true,
                ],
            ],
        ];

        $ch = curl_init('https://api.paymongo.com/v2/checkout_sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'accept: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $res      = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno)
            return redirect()->route('track.order', $trackCode)->with('error', 'Network error. Please try again.');

        $data        = json_decode($res, true);
        $sessionId   = $data['data']['id'] ?? null;
        $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

        if (!$sessionId || !$checkoutUrl) {
            $errMsg = $data['errors'][0]['detail'] ?? 'Could not create payment session.';
            return redirect()->route('track.order', $trackCode)->with('error', $errMsg);
        }

        DB::table('orders')->where('id', $order->id)->update([
            'paymongo_link_id' => $sessionId,
        ]);

        return redirect()->away($checkoutUrl);
    }

    public function remainingReturn(Request $request, string $trackCode)
    {
        $status = $request->input('status', '');

        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"), 'p.image_path as product_image')
            ->first();

        if (!$order) abort(404);

        if ($status === 'cancelled')
            return redirect()->route('track.order', $trackCode)->with('error', 'Payment cancelled. You can try again.');

        if ($order->payment_status === 'Paid') {
            $order->receipt_paid_amount = $this->latestPaymentTransactionAmount($order->id, 'remaining_gcash')
                ?? $this->latestPaymentTransactionAmount($order->id, 'full_gcash')
                ?? (float) $order->total_price;
            $receiptAddons = DB::table('order_addons')->where('order_id', $order->id)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();
            if ($appReturn = $this->mobileAppReturn($request, '/track/' . $trackCode . '?payment=success')) return $appReturn;
            return view('guest.payment_receipt', ['success'=>true,'trackCode'=>$trackCode,'receipt'=>$order,'receiptAddons'=>$receiptAddons,'vatSettings'=>$vatSettings,'pmReference'=>null]);
        }

        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        if (!$order->paymongo_link_id || !$secretKey)
            return redirect()->route('track.order', $trackCode)->with('error', 'Could not verify payment.');

        $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions/{$order->paymongo_link_id}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['accept: application/json','Authorization: Basic ' . base64_encode($secretKey . ':')],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $sessionStatus = $res['data']['attributes']['status'] ?? '';
        $paymentStatus = $res['data']['attributes']['payment_intent']['attributes']['status'] ?? '';
        $pmReference   = $res['data']['attributes']['payments'][0]['attributes']['reference_number']
                         ?? ($res['data']['attributes']['reference_number'] ?? null);

        if ($sessionStatus === 'completed' || $paymentStatus === 'succeeded') {
            $depositPaid = ($order->deposit_status ?? '') === 'paid';
            $paidAmount = $depositPaid
                ? max(0, (float) $order->total_price - (float) $order->deposit_amount)
                : (float) $order->total_price;

            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => 'Paid',
                'paid_at'        => now(),
            ]);
            PaymentTransactionHelper::record(
                $order,
                $depositPaid ? 'remaining_gcash' : 'full_gcash',
                'GCash',
                $paidAmount,
                $pmReference
            );
            try {
                $pushOrder = DB::table('orders')->where('id', $order->id)->first();
                if ($pushOrder) {
                    app(MobileNotificationService::class)->notifyPaymentComplete($pushOrder);
                }
            } catch (\Throwable $e) {
                Log::warning('Guest remaining payment push failed: ' . $e->getMessage());
            }

            // ── AUTO CONFIRM + SEND TO KITCHEN (remaining balance paid) ─
            if (in_array($order->status, ['Pending', 'Pending Review'])) {
                DB::table('orders')->where('id', $order->id)->update(['status' => 'Confirmed']);
                DB::table('order_tracking')->insert([
                    'order_id'   => $order->id,
                    'status'     => 'Confirmed',
                    'notes'      => 'Auto-confirmed after full GCash payment (remaining balance).',
                    'created_at' => now(),
                ]);

                if (!$order->kitchen_sent) {
                    $addons = DB::table('order_addons')->where('order_id', $order->id)->get();
                    $addonList = $addons->count() > 0
                        ? "\nADD-ONS:\n" . $addons->map(fn($a) => "  • {$a->addon_name}" . ($a->addon_price > 0 ? " (+₱{$a->addon_price})" : " (FREE)"))->implode("\n")
                        : '';
                    $productName = $order->product_name ?? DB::table('products')->where('id', $order->product_id)->value('name') ?? 'Custom Cake';
                    $fullname    = $order->guest_name ?? DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Guest';
                    $phone       = $order->guest_phone ?? DB::table('users')->where('id', $order->user_id)->value('phone') ?? '';
                    $sizeInfo    = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
                    $noteInfo    = $order->custom_note   ? "\nSPECIAL NOTE: {$order->custom_note}" : '';

                    DB::table('kitchen_tickets')->where('order_id', $order->id)->delete();
                    DB::table('kitchen_tickets')->insert([
                        'shop_id'       => $order->shop_id ?? null,
                        'order_id'     => $order->id,
                        'product_name' => $productName,
                        'product_image'=> $order->product_image ?? null,
                        'quantity'     => $order->quantity ?? 1,
                        'instructions' => "=== KITCHEN ORDER TICKET ===\nOrder #: {$order->id}\nCustomer: {$fullname}" . ($phone ? " ({$phone})" : '') . "\nProduct: {$productName}\nQty: {$order->quantity}{$sizeInfo}{$noteInfo}{$addonList}\nFulfillment: {$order->fulfillment_type}\nPayment: GCash ✓ Fully Paid\n===========================",
                        'status'       => 'pending',
                        'sent_at'      => now()->format('Y-m-d H:i:s'),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => true]);
                }
            }
            // ── END AUTO CONFIRM ────────────────────────────────────────

            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'GCash Payment Received - Order #' . $order->id,
                'message'          => ($order->guest_name ?? 'Guest') . ' completed GCash payment for Order #' . $order->id . '. Order auto-confirmed and sent to kitchen.',
                'is_read' => false,
                'created_at'       => now(),
            ]);

            $order = DB::table('orders as o')
                ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.id', $order->id)
                ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"), 'p.image_path as product_image')
                ->first();
            $order->receipt_paid_amount = $paidAmount;

            $receiptAddons = DB::table('order_addons')->where('order_id', $order->id)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();

            if ($appReturn = $this->mobileAppReturn($request, '/track/' . $trackCode . '?payment=success')) return $appReturn;
            return view('guest.payment_receipt', [
                'success'       => true,
                'trackCode'     => $trackCode,
                'receipt'       => $order,
                'receiptAddons' => $receiptAddons,
                'vatSettings'   => $vatSettings,
                'pmReference'   => $pmReference,
            ]);
        }

        return redirect()->route('track.order', $trackCode)->with('error', 'Payment was not completed. Please try again.');
    }

    public function generatePaymentQr(Request $request, string $trackCode)
    {
        if (!Schema::hasTable('customer_payment_qrs')) {
            return response()->json(['ok' => false, 'error' => 'QR payment is not ready yet. Please try the GCash payment button.'], 503);
        }

        $order = $this->qrOrder($trackCode);
        if (!$order) {
            return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);
        }

        $resolved = $this->resolveQrPayment($request, $order);
        if (!$resolved['ok']) {
            return response()->json(['ok' => false, 'error' => $resolved['error']], 422);
        }

        $active = $this->activeCustomerQr($order, $resolved['payment_type'], (float) $resolved['amount']);
        if ($active) {
            $sync = $this->syncCustomerPaymentQr($active);
            if ($sync['paid']) {
                return response()->json($this->customerQrPayload($active, true, $sync['message']));
            }

            $fresh = DB::table('customer_payment_qrs')->where('id', $active->id)->first();
            if ($fresh && ($fresh->status ?? '') === 'awaiting_payment' && (empty($fresh->paymongo_expires_at) || now()->lt($fresh->paymongo_expires_at))) {
                return response()->json($this->customerQrPayload($fresh, false, 'QR is still active.'));
            }
        }

        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        $publicKey = CakeshopHelper::getPaymongoPublicKey();
        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY')) {
            return response()->json(['ok' => false, 'error' => 'GCash payment is not configured yet. Please contact the shop.'], 422);
        }

        $amountCentavos = (int) round((float) $resolved['amount'] * 100);
        if ($amountCentavos < 10000) {
            return response()->json(['ok' => false, 'error' => 'Minimum GCash payment is PHP 100.00.'], 422);
        }

        $clientApiKey = ($publicKey && !str_contains($publicKey, 'YOUR_PUBLIC_KEY')) ? $publicKey : $secretKey;
        $reference = strtoupper($resolved['payment_type']) . '-QR-' . $order->id . '-' . now()->format('His');
        $description = $resolved['label'] . ' for Order #' . $order->id;

        try {
            $intent = $this->paymongoRequest('POST', 'https://api.paymongo.com/v1/payment_intents', $secretKey, [
                'data' => [
                    'attributes' => [
                        'amount' => $amountCentavos,
                        'currency' => 'PHP',
                        'payment_method_allowed' => ['qrph'],
                        'description' => $description,
                    ],
                ],
            ]);

            $intentId = data_get($intent, 'data.id');
            $clientKey = data_get($intent, 'data.attributes.client_key');
            if (!$intentId || !$clientKey) {
                throw new \RuntimeException(data_get($intent, 'errors.0.detail', 'Could not create PayMongo payment intent.'));
            }

            $method = $this->paymongoRequest('POST', 'https://api.paymongo.com/v1/payment_methods', $clientApiKey, [
                'data' => [
                    'attributes' => [
                        'type' => 'qrph',
                        'expiry_seconds' => 1800,
                    ],
                ],
            ]);

            $methodId = data_get($method, 'data.id');
            if (!$methodId) {
                throw new \RuntimeException(data_get($method, 'errors.0.detail', 'Could not create PayMongo QR method.'));
            }

            $attached = $this->paymongoRequest('POST', "https://api.paymongo.com/v1/payment_intents/{$intentId}/attach", $clientApiKey, [
                'data' => [
                    'attributes' => [
                        'payment_method' => $methodId,
                        'client_key' => $clientKey,
                    ],
                ],
            ]);

            $qrImage = data_get($attached, 'data.attributes.next_action.code.image_url');
            $actionUrl = data_get($attached, 'data.attributes.next_action.redirect.url')
                ?: data_get($attached, 'data.attributes.next_action.url');
            $paymongoStatus = data_get($attached, 'data.attributes.status', 'awaiting_next_action');
            if (!$qrImage) {
                throw new \RuntimeException(data_get($attached, 'errors.0.detail', 'PayMongo did not return a QR image.'));
            }

            DB::table('customer_payment_qrs')
                ->where('order_id', $order->id)
                ->where('payment_type', $resolved['payment_type'])
                ->whereIn('status', ['awaiting_payment', 'expired'])
                ->update(['status' => 'expired', 'updated_at' => now()]);

            $qrId = DB::table('customer_payment_qrs')->insertGetId([
                'order_id' => $order->id,
                'track_code' => strtoupper($trackCode),
                'payment_type' => $resolved['payment_type'],
                'amount' => $resolved['amount'],
                'status' => 'awaiting_payment',
                'reference_number' => $reference,
                'paymongo_payment_intent_id' => $intentId,
                'paymongo_payment_method_id' => $methodId,
                'paymongo_client_key' => $clientKey,
                'paymongo_qr_image' => $qrImage,
                'paymongo_action_url' => $actionUrl,
                'paymongo_status' => $paymongoStatus,
                'paymongo_reference' => $reference,
                'paymongo_expires_at' => now()->addMinutes(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $qr = DB::table('customer_payment_qrs')->where('id', $qrId)->first();
            return response()->json($this->customerQrPayload($qr, false, 'Scan this QR with GCash.'));
        } catch (\Throwable $e) {
            Log::error('Customer QR payment generation failed', [
                'track_code' => $trackCode,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Could not generate QR. PayMongo: ' . $e->getMessage()], 500);
        }
    }

    public function checkPaymentQr(Request $request, string $trackCode)
    {
        if (!Schema::hasTable('customer_payment_qrs')) {
            return response()->json(['ok' => false, 'error' => 'QR payment is not ready yet.'], 503);
        }

        $order = $this->qrOrder($trackCode);
        if (!$order) {
            return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);
        }

        $qrId = (int) $request->input('qr_id', 0);
        $qr = DB::table('customer_payment_qrs')
            ->where('order_id', $order->id)
            ->when($qrId > 0, fn ($q) => $q->where('id', $qrId))
            ->when($qrId <= 0, fn ($q) => $q->where('status', 'awaiting_payment')->orderByDesc('id'))
            ->first();

        if (!$qr) {
            return response()->json(['ok' => false, 'error' => 'No active QR payment found.'], 404);
        }

        try {
            $sync = $this->syncCustomerPaymentQr($qr);
            $fresh = DB::table('customer_payment_qrs')->where('id', $qr->id)->first() ?: $qr;
            return response()->json($this->customerQrPayload($fresh, $sync['paid'], $sync['message']));
        } catch (\Throwable $e) {
            Log::error('Customer QR payment check failed', [
                'track_code' => $trackCode,
                'qr_id' => $qr->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Could not check payment status right now.'], 500);
        }
    }

    public function paymentReturn(Request $request, string $trackCode)
    {
        $status = $request->input('status', '');

        $order = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"), 'p.image_path as product_image')
            ->first();

        if (!$order) abort(404);

        if ($status === 'cancelled') {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Payment cancelled. You can try again anytime.');
        }

        // Already paid
        if ($order->payment_status === 'Paid') {
            $receiptAddons = DB::table('order_addons')->where('order_id', $order->id)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();
            if ($appReturn = $this->mobileAppReturn($request, '/track/' . $trackCode . '?payment=success')) return $appReturn;
            return view('guest.payment_receipt', [
                'success'       => true,
                'trackCode'     => $trackCode,
                'receipt'       => $order,
                'receiptAddons' => $receiptAddons,
                'vatSettings'   => $vatSettings,
                'pmReference'   => null,
            ]);
        }

        // Verify with PayMongo
        $secretKey = CakeshopHelper::getPaymongoSecretKey();

        if (!$order->paymongo_link_id || !$secretKey) {
            return redirect()->route('track.order', $trackCode)
                ->with('error', 'Could not verify payment. Please contact the shop.');
        }

        $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions/{$order->paymongo_link_id}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $sessionStatus = $res['data']['attributes']['status'] ?? '';
        $paymentStatus = $res['data']['attributes']['payment_intent']['attributes']['status'] ?? '';
        $pmReference   = $res['data']['attributes']['payments'][0]['attributes']['reference_number']
                         ?? ($res['data']['attributes']['reference_number'] ?? null);

        if ($sessionStatus === 'completed' || $paymentStatus === 'succeeded') {
            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => 'Paid',
                'paid_at'        => now(),
            ]);
            PaymentTransactionHelper::record($order, 'full_gcash', 'GCash', (float) $order->total_price, $pmReference);
            try {
                $pushOrder = DB::table('orders')->where('id', $order->id)->first();
                if ($pushOrder) {
                    app(MobileNotificationService::class)->notifyPaymentComplete($pushOrder);
                }
            } catch (\Throwable $e) {
                Log::warning('Guest full payment push failed: ' . $e->getMessage());
            }

            // ── AUTO CONFIRM + SEND TO KITCHEN ─────────────────────────
            // Only auto-confirm if order is still Pending/Pending Review
            if (in_array($order->status, ['Pending', 'Pending Review'])) {

                // 1. Update order status to Confirmed
                DB::table('orders')->where('id', $order->id)->update([
                    'status' => 'Confirmed',
                ]);

                DB::table('order_tracking')->insert([
                    'order_id'   => $order->id,
                    'status'     => 'Confirmed',
                    'notes'      => 'Auto-confirmed after GCash payment.',
                    'created_at' => now(),
                ]);

                // 2. Send to Kitchen (auto)
                if (!$order->kitchen_sent) {
                    $addons    = DB::table('order_addons')->where('order_id', $order->id)->get();
                    $addonList = $addons->count() > 0
                        ? "\nADD-ONS:\n" . $addons->map(fn($a) => "  • {$a->addon_name}" . ($a->addon_price > 0 ? " (+₱{$a->addon_price})" : " (FREE)"))->implode("\n")
                        : '';

                    $sizeInfo  = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
                    $noteInfo  = $order->custom_note   ? "\nSPECIAL NOTE: {$order->custom_note}" : '';
                    $schedInfo = $order->schedule_date
                        ? "\nSCHEDULE: " . date('M d, Y', strtotime($order->schedule_date)) .
                          ($order->schedule_time ? ' at ' . date('g:i A', strtotime($order->schedule_time)) : '')
                        : '';

                    $productName = $order->product_name ?? DB::table('products')->where('id', $order->product_id)->value('name') ?? 'Custom Cake';
                    $fullname    = $order->guest_name ?? DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Guest';
                    $phone       = $order->guest_phone ?? DB::table('users')->where('id', $order->user_id)->value('phone') ?? '';

                    $instructions = "=== KITCHEN ORDER TICKET ===\n" .
                        "Order #: {$order->id}\n" .
                        "Customer: {$fullname}" . ($phone ? " ({$phone})" : '') . "\n" .
                        "Product: {$productName}\n" .
                        "Qty: {$order->quantity}" .
                        $sizeInfo . $noteInfo . $addonList . $schedInfo .
                        "\nFulfillment: {$order->fulfillment_type}" .
                        "\nPayment: GCash ✓ Paid" .
                        "\n===========================";

                    DB::table('kitchen_tickets')->where('order_id', $order->id)->delete();
                    DB::table('kitchen_tickets')->insert([
                        'shop_id'       => $order->shop_id ?? null,
                        'order_id'     => $order->id,
                        'product_name' => $productName,
                        'product_image'=> $order->product_image ?? null,
                        'quantity'     => $order->quantity ?? 1,
                        'instructions' => $instructions,
                        'status'       => 'pending',
                        'sent_at'      => now()->format('Y-m-d H:i:s'),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => true]);
                }
            }
            // ── END AUTO CONFIRM ────────────────────────────────────────

            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'GCash Payment Received - Order #' . $order->id,
                'message'          => ($order->guest_name ?? 'Guest') . ' paid via GCash for Order #' . $order->id . '. Order auto-confirmed and sent to kitchen.',
                'is_read' => false,
                'created_at'       => now(),
            ]);

            // Refresh order
            $order = DB::table('orders as o')
                ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.id', $order->id)
                ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"), 'p.image_path as product_image')
                ->first();

            $receiptAddons = DB::table('order_addons')->where('order_id', $order->id)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();

            if ($appReturn = $this->mobileAppReturn($request, '/track/' . $trackCode . '?payment=success')) return $appReturn;
            return view('guest.payment_receipt', [
                'success'       => true,
                'trackCode'     => $trackCode,
                'receipt'       => $order,
                'receiptAddons' => $receiptAddons,
                'vatSettings'   => $vatSettings,
                'pmReference'   => $pmReference,
            ]);
        }

        return redirect()->route('track.order', $trackCode)
            ->with('error', 'Payment was not completed. Please try again.');
    }

    private function qrOrder(string $trackCode): ?object
    {
        return DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"), 'p.image_path as product_image')
            ->first();
    }

    private function resolveQrPayment(Request $request, object $order): array
    {
        if (($order->payment_method ?? '') !== 'GCash') {
            return ['ok' => false, 'error' => 'QR payment is only available for GCash orders.'];
        }
        if (($order->payment_status ?? '') === 'Paid') {
            return ['ok' => false, 'error' => 'This order has already been paid.'];
        }
        if (($order->status ?? '') === 'Cancelled' || in_array(($order->cancel_status ?? ''), ['pending', 'accepted'], true)) {
            return ['ok' => false, 'error' => 'Payment is no longer available for this order.'];
        }

        $type = $request->input('payment_type', 'remaining');
        $total = round((float) ($order->total_price ?? 0), 2);
        $depositPaid = ($order->deposit_status ?? '') === 'paid';

        if ($type === 'deposit') {
            if (!($order->deposit_required ?? false) && !in_array(($order->status ?? ''), ['Pending', 'Pending Review'], true)) {
                return ['ok' => false, 'error' => 'Deposit is not available at this stage.'];
            }
            if (($order->deposit_status ?? '') === 'paid') {
                return ['ok' => false, 'error' => 'Deposit has already been paid.'];
            }
            $min = max(100, round($total * 0.5, 2));
            $amount = round((float) $request->input('amount', $order->deposit_amount ?: $min), 2);
            if ($amount < $min) {
                return ['ok' => false, 'error' => 'Minimum deposit is PHP ' . number_format($min, 2) . '.'];
            }
            if ($amount > $total) {
                return ['ok' => false, 'error' => 'Payment cannot exceed the order total.'];
            }
            return ['ok' => true, 'payment_type' => 'deposit', 'amount' => $amount, 'label' => abs($amount - $total) < 0.01 ? 'Full Payment' : 'Deposit'];
        }

        $isPickup = ($order->fulfillment_type ?? '') === 'Pickup';
        $payStatus = $isPickup ? 'Pickup' : 'Out for Delivery';
        if (!in_array($type, ['full', 'remaining'], true)) {
            return ['ok' => false, 'error' => 'Invalid QR payment type.'];
        }
        if (($order->status ?? '') !== $payStatus && !in_array(($order->status ?? ''), ['Pending', 'Pending Review'], true)) {
            return ['ok' => false, 'error' => 'Payment is not available at this stage yet.'];
        }

        $amount = $depositPaid ? max(0, $total - (float) ($order->deposit_amount ?? 0)) : $total;
        return ['ok' => true, 'payment_type' => $depositPaid ? 'remaining' : 'full', 'amount' => round($amount, 2), 'label' => $depositPaid ? 'Remaining Balance' : 'Full Payment'];
    }

    private function activeCustomerQr(object $order, string $type, float $amount): ?object
    {
        if (!Schema::hasTable('customer_payment_qrs')) return null;

        return DB::table('customer_payment_qrs')
            ->where('order_id', $order->id)
            ->where('payment_type', $type)
            ->where('amount', round($amount, 2))
            ->where('status', 'awaiting_payment')
            ->orderByDesc('id')
            ->first();
    }

    private function customerQrPayload(object $qr, bool $paid = false, string $message = ''): array
    {
        $qrData = [
            'id' => $qr->id,
            'status' => $qr->status,
            'amount' => (float) $qr->amount,
            'payment_type' => $qr->payment_type,
            'qr_image' => $qr->paymongo_qr_image,
            'action_url' => $qr->paymongo_action_url,
            'expires_at' => $qr->paymongo_expires_at ? \Carbon\Carbon::parse($qr->paymongo_expires_at)->toIso8601String() : null,
            'reference_number' => $qr->paymongo_reference ?: $qr->reference_number,
        ];

        return [
            'ok' => true,
            'paid' => $paid,
            'message' => $message,
            'qr' => $qrData,
            'qr_id' => $qr->id,
            'status' => $qr->status,
            'amount' => (float) $qr->amount,
            'payment_type' => $qr->payment_type,
            'qr_image' => $qr->paymongo_qr_image,
            'action_url' => $qr->paymongo_action_url,
            'expires_at' => $qr->paymongo_expires_at ? \Carbon\Carbon::parse($qr->paymongo_expires_at)->toIso8601String() : null,
            'reference' => $qr->paymongo_reference ?: $qr->reference_number,
            'redirect_url' => route('track.order', $qr->track_code),
        ];
    }

    private function syncCustomerPaymentQr(object $qr): array
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        if (!$secretKey) {
            return ['paid' => false, 'message' => 'PayMongo keys are not configured.'];
        }

        $intent = $this->paymongoRequest('GET', 'https://api.paymongo.com/v1/payment_intents/' . $qr->paymongo_payment_intent_id, $secretKey);
        $status = data_get($intent, 'data.attributes.status', '');
        $reference = data_get($intent, 'data.attributes.payments.0.attributes.reference_number')
            ?: ($qr->paymongo_reference ?? $qr->reference_number ?? null);
        $paid = $status === 'succeeded';

        $updates = [
            'paymongo_status' => $status ?: null,
            'updated_at' => now(),
        ];

        if ($paid) {
            $updates = array_merge($updates, [
                'status' => 'paid',
                'paymongo_reference' => $reference,
                'paymongo_paid_at' => now(),
            ]);
        } elseif (!empty($qr->paymongo_expires_at) && now()->gte($qr->paymongo_expires_at)) {
            $updates['status'] = 'expired';
        }

        DB::table('customer_payment_qrs')->where('id', $qr->id)->update($updates);

        if ($paid) {
            $order = $this->qrOrder($qr->track_code);
            if ($order && ($order->payment_status ?? '') !== 'Paid') {
                $this->applyCustomerQrPayment($order, $qr, $reference);
            }
            return ['paid' => true, 'message' => 'Payment received. Your order has been updated.'];
        }

        if (($updates['status'] ?? null) === 'expired') {
            return ['paid' => false, 'message' => 'QR expired. Generating a new QR...'];
        }

        return ['paid' => false, 'message' => 'Payment is not completed yet.'];
    }

    private function applyCustomerQrPayment(object $order, object $qr, ?string $reference): void
    {
        $type = (string) $qr->payment_type;
        $amount = (float) $qr->amount;
        $freshOrder = $this->qrOrder($qr->track_code) ?: $order;
        $total = (float) ($freshOrder->total_price ?? $order->total_price ?? 0);
        $isFull = $type === 'full' || abs($amount - $total) < 0.01;

        if ($isFull && ($freshOrder->payment_status ?? '') === 'Paid') {
            return;
        }
        if (!$isFull && $type === 'deposit' && ($freshOrder->deposit_status ?? '') === 'paid') {
            return;
        }

        $order = $freshOrder;

        if ($type === 'deposit' && !$isFull) {
            DB::table('orders')->where('id', $order->id)->update([
                'deposit_required' => 1,
                'deposit_amount' => $amount,
                'deposit_status' => 'paid',
                'deposit_paid_at' => now(),
                'payment_status' => 'Partial Payment',
            ]);
            PaymentTransactionHelper::record($order, 'downpayment_gcash', 'GCash', $amount, $reference);
            $trackingStatus = 'Deposit Paid';
            $trackingNotes = 'Deposit of PHP ' . number_format($amount, 2) . ' paid via GCash QR.';
        } else {
            DB::table('orders')->where('id', $order->id)->update([
                'deposit_required' => $type === 'deposit' ? 1 : ($order->deposit_required ?? 0),
                'deposit_amount' => $type === 'deposit' ? $amount : ($order->deposit_amount ?? null),
                'deposit_status' => $type === 'deposit' ? 'paid' : ($order->deposit_status ?? null),
                'deposit_paid_at' => $type === 'deposit' ? now() : ($order->deposit_paid_at ?? null),
                'payment_status' => 'Paid',
                'paid_at' => now(),
            ]);
            $recordType = (($order->deposit_status ?? '') === 'paid' && $type === 'remaining') ? 'remaining_gcash' : 'full_gcash';
            PaymentTransactionHelper::record($order, $recordType, 'GCash', $amount, $reference);
            $trackingStatus = 'Payment Paid';
            $trackingNotes = 'GCash QR payment of PHP ' . number_format($amount, 2) . ' received.';
        }

        try {
            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => $trackingStatus,
                'notes' => $trackingNotes,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        if (in_array(($order->status ?? ''), ['Awaiting Deposit', 'Pending', 'Pending Review'], true)) {
            DB::table('orders')->where('id', $order->id)->update(['status' => 'Confirmed']);
            try {
                DB::table('order_tracking')->insert([
                    'order_id' => $order->id,
                    'status' => 'Confirmed',
                    'notes' => 'Auto-confirmed after GCash QR payment.',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {}

            $fresh = $this->qrOrder($qr->track_code);
            if ($fresh && !$fresh->kitchen_sent) {
                $this->sendToKitchen($fresh, $isFull);
            }
        }

        try {
            $fresh = DB::table('orders')->where('id', $order->id)->first();
            if ($fresh) {
                app(MobileNotificationService::class)->notifyPaymentComplete($fresh);
            }
            DB::table('notifications')->insert([
                'receiver_role' => 'admin',
                'receiver_user_id' => null,
                'title' => 'GCash QR Payment Received - Order #' . $order->id,
                'message' => ($order->guest_name ?? 'Guest') . ' paid PHP ' . number_format($amount, 2) . ' via GCash QR for Order #' . $order->id . '.',
                'is_read' => false,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Customer QR payment notification failed: ' . $e->getMessage());
        }
    }

    private function paymongoRequest(string $method, string $url, string $key, array $payload = []): array
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'accept: application/json',
                'Authorization: Basic ' . base64_encode($key . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('PayMongo network error: ' . $error);
        }

        $data = $raw ? json_decode($raw, true) : [];
        if ($httpCode >= 400) {
            throw new \RuntimeException(data_get($data, 'errors.0.detail', 'PayMongo returned HTTP ' . $httpCode));
        }

        return is_array($data) ? $data : [];
    }

    private function getPaymongoCheckoutMethods(): array
    {
        return ['gcash'];
    }

    private function mobileReturnParam(): string
    {
        return request()->boolean('mobile_app') ? '&mobile_app=1' : '';
    }

    private function mobileAppReturn(Request $request, string $path): ?\Illuminate\Http\RedirectResponse
    {
        if (!$request->boolean('mobile_app')) {
            return null;
        }

        $path = '/' . ltrim($path, '/');
        return redirect()->away('com.berrybase.cakeshop://' . $path);
    }

    private function latestPaymentTransactionAmount(string $orderId, string $type): ?float
    {
        try {
            $amount = DB::table('payment_transactions')
                ->where('order_id', $orderId)
                ->where('type', $type)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->value('amount');

            return $amount !== null ? (float) $amount : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatPaymongoCheckoutPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        // Normalize PH numbers for PayMongo's hosted phone field.
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '63') {
            $digits = substr($digits, 2); // strip 63 → 9XXXXXXXXX
        }
        if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
            $digits = substr($digits, 1); // strip leading 0 → 9XXXXXXXXX
        }
        // The hosted checkout prepends +63, so only pass the national number.
        if (strlen($digits) === 10 && substr($digits, 0, 1) === '9') {
            return $digits;
        }
        return '';
    }

    /**
     * Customer custom order — GCash deposit via PayMongo
     */
    public function payCustomDeposit(string $coId)
    {
        $uid   = session('user')['id'];
        $co    = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) abort(404);

        $order = DB::table('orders')->where('id', $co->order_id)->first();
        if (!$order) abort(404);

        if (($order->status ?? '') === 'Cancelled' || in_array(($order->cancel_status ?? ''), ['pending', 'accepted'], true)) {
            return redirect()->route('customer.orders')
                ->with('err', 'Payment is no longer available because this order has been cancelled or has a pending cancellation request.');
        }

        if ($order->deposit_status === 'paid')
            return redirect()->route('customer.orders')->with('msg', 'Deposit already paid!');

        $secretKey      = CakeshopHelper::getPaymongoSecretKey();
        $amountCentavos = (int) round((float)$order->deposit_amount * 100);

        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY')) {
            return redirect()->route('customer.orders')->with('err', 'GCash payment is not configured yet. Please contact the shop.');
        }

        if ($amountCentavos < 10000) {
            return redirect()->route('customer.orders')->with('err', 'Minimum GCash payment is PHP 100.00.');
        }

        $mobileParam = $this->mobileReturnParam();
        $successUrl = route('customer.custom_orders.deposit_return', $coId) . '?status=success' . $mobileParam;
        $cancelUrl  = route('customer.custom_orders.deposit_return', $coId) . '?status=cancelled' . $mobileParam;

        $payload = [
            'data' => ['attributes' => [
                'billing'      => ['name' => DB::table('users')->where('id', $uid)->value('fullname') ?? 'Customer'],
                'line_items'   => [[
                    'currency'   => 'PHP',
                    'amount'     => $amountCentavos,
                    'name'       => 'Custom Order #' . $order->id . ' — Deposit',
                    'quantity'   => 1,
                ]],
                'payment_method_types' => $this->getPaymongoCheckoutMethods(),
                'pass_on_fees' => true,
                'success_url'  => $successUrl,
                'cancel_url'   => $cancelUrl,
                'description'  => 'Custom Cake Deposit — Order #' . $order->id,
            ]]
        ];

        $ch = curl_init('https://api.paymongo.com/v2/checkout_sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $data = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $sessionId   = $data['data']['id'] ?? null;
        $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

        if (!$sessionId || !$checkoutUrl)
            return redirect()->route('customer.orders')->with('err', 'Could not create payment session. Please try again.');

        DB::table('orders')->where('id', $order->id)->update(['deposit_paymongo_id' => $sessionId]);

        return redirect()->away($checkoutUrl);
    }

    /**
     * Customer custom order GCash deposit return
     */
    public function customDepositReturn(Request $request, string $coId)
    {
        $uid    = session('user')['id'];
        $co     = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) abort(404);

        $status = $request->input('status', '');
        if ($status === 'cancelled')
            return redirect()->route('customer.orders')->with('err', 'Payment cancelled. You can try again.');

        $order = DB::table('orders')->where('id', $co->order_id)->first();
        if (!$order) abort(404);

        if ($order->deposit_status === 'paid')
            return redirect()->route('customer.orders')->with('msg', 'Deposit already paid! ✅');

        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        if (!$order->deposit_paymongo_id || !$secretKey)
            return redirect()->route('customer.orders')->with('err', 'Could not verify payment.');

        $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions/{$order->deposit_paymongo_id}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $sessionStatus = $res['data']['attributes']['status'] ?? '';
        $paymentStatus = $res['data']['attributes']['payment_intent']['attributes']['status'] ?? '';
        $pmReference   = $res['data']['attributes']['payments'][0]['attributes']['reference_number']
                         ?? ($res['data']['attributes']['reference_number'] ?? null);

        if ($sessionStatus === 'completed' || $paymentStatus === 'succeeded') {
            $isFullPayment = abs((float)$order->deposit_amount - (float)$co->admin_price) < 0.01;
            $order->total_price = $co->admin_price;

            DB::table('orders')->where('id', $order->id)->update([
                'deposit_status'  => 'paid',
                'deposit_paid_at' => now(),
                'payment_status'  => $isFullPayment ? 'Paid' : 'Partial Payment',
                'status'          => 'Confirmed',
                'total_price'     => $co->admin_price,
            ]);
            PaymentTransactionHelper::record(
                $order,
                $isFullPayment ? 'full_gcash' : 'downpayment_gcash',
                'GCash',
                (float) $order->deposit_amount,
                $pmReference
            );
            try {
                $pushOrder = DB::table('orders')->where('id', $order->id)->first();
                if ($pushOrder) {
                    app(MobileNotificationService::class)->notifyPaymentComplete($pushOrder);
                }
            } catch (\Throwable $e) {
                Log::warning('Guest custom deposit payment push failed: ' . $e->getMessage());
            }

            DB::table('order_tracking')->insert([
                'order_id'   => $order->id,
                'status'     => 'Confirmed',
                'notes'      => $isFullPayment
                    ? "GCash full payment PHP {$order->deposit_amount} received. Custom order auto-confirmed."
                    : "GCash deposit PHP {$order->deposit_amount} received. Custom order auto-confirmed. Remaining: PHP " . ($co->admin_price - $order->deposit_amount),
                'created_at' => now(),
            ]);

            // Send to kitchen
            $addons    = DB::table('order_addons')->where('order_id', $order->id)->get();
            $addonList = $addons->count() > 0
                ? "\nADD-ONS:\n" . $addons->map(fn($a) => "  • {$a->addon_name}" . ($a->addon_price > 0 ? " (+₱{$a->addon_price})" : " (FREE)"))->implode("\n")
                : '';
            $fullname    = DB::table('users')->where('id', $uid)->value('fullname') ?? 'Customer';
            $phone       = DB::table('users')->where('id', $uid)->value('phone') ?? '';
            $productName = DB::table('products')->where('id', $order->product_id)->value('name') ?? 'Custom Cake';
            $sizeInfo    = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
            $noteInfo    = $order->custom_note   ? "\nSPECIAL NOTE: {$order->custom_note}" : '';
            $schedInfo   = $order->schedule_date ? "\nSCHEDULE: " . date('M d, Y', strtotime($order->schedule_date)) : '';
            $payInfo     = $isFullPayment ? "GCash Full ₱{$order->deposit_amount} ✓ Fully Paid" : "GCash Deposit ₱{$order->deposit_amount} ✓ Paid (Balance remaining)";

            if (!$order->kitchen_sent) {
                DB::table('kitchen_tickets')->where('order_id', $order->id)->delete();
                DB::table('kitchen_tickets')->insert([
                    'shop_id'       => $order->shop_id ?? null,
                    'order_id'     => $order->id,
                    'product_name' => $productName . ' (Custom)',
                    'quantity'     => $order->quantity ?? 1,
                    'instructions' => "=== KITCHEN ORDER TICKET ===\nOrder #: {$order->id}\nCustomer: {$fullname} ({$phone})\nProduct: {$productName} (Custom)\nQty: {$order->quantity}{$sizeInfo}{$noteInfo}{$addonList}{$schedInfo}\nFulfillment: {$order->fulfillment_type}\nPayment: {$payInfo}\n===========================",
                    'status'       => 'pending',
                    'sent_at'      => now()->format('Y-m-d H:i:s'),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => true]);
            }

            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'Custom Order #' . $order->id . ' - GCash Deposit Paid',
                'message'          => "{$fullname} paid GCash deposit of PHP {$order->deposit_amount} for Custom Order #{$order->id}. Auto-confirmed.",
                'is_read' => false,
                'created_at'       => now(),
            ]);

            if ($appReturn = $this->mobileAppReturn($request, '/customer/orders?payment=success')) return $appReturn;
            return redirect()->route('customer.orders')->with('msg', 'Payment received! Your custom cake order is now confirmed.');
        }

        return redirect()->route('customer.orders')->with('err', 'Payment not completed. Please try again.');
    }


}
