<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function payGcash(string $trackCode)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();

        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name')
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

        $successUrl = url('/track/' . $trackCode . '/payment-return?status=success');
        $cancelUrl  = url('/track/' . $trackCode . '/payment-return?status=cancelled');

        // PayMongo expects E.164 format: +639XXXXXXXXX (no double prefix)
        $rawPhone = $order->guest_phone ?? '';
        $phone    = $this->formatPhoneE164($rawPhone);

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

        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
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

        if (!in_array($order->status, ['Pending', 'Pending Review']))
            return back()->with('error', 'This order cannot be modified at this stage.');

        if ($order->payment_status === 'Paid')
            return back()->with('error', 'This order is already fully paid.');

        $depositAmount = (float) $request->input('deposit_amount', 0);
        $minDeposit    = round((float)$order->total_price * 0.5, 2);
        $maxDeposit    = (float)$order->total_price;

        // Validate: must be at least 50% and at most 100%
        if ($depositAmount < $minDeposit) {
            return back()->with('error', 'Minimum deposit is 50% of total (₱' . number_format($minDeposit, 2) . ').');
        }
        if ($depositAmount > $maxDeposit) {
            $depositAmount = $maxDeposit;
        }

        // Round to 2 decimal places
        $depositAmount = round($depositAmount, 2);
        $isFullPayment = abs($depositAmount - $maxDeposit) < 0.01;

        // Save deposit info
        DB::table('orders')->where('id', $order->id)->update([
            'deposit_required' => 1,
            'deposit_amount'   => $depositAmount,
            'deposit_status'   => 'pending',
        ]);

        try {
            DB::table('order_tracking')->insert([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'notes'      => $isFullPayment
                                ? "Customer chose to pay full amount ₱{$depositAmount} via GCash."
                                : "Customer set deposit of ₱{$depositAmount} via GCash (min 50%).",
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {}

        // Redirect to PayMongo deposit payment
        return redirect()->route('guest.pay_deposit', $trackCode);
    }

    public function payDeposit(string $trackCode)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();

        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name')
            ->first();

        if (!$order) abort(404);
        if (!$order->deposit_required)
            return redirect()->route('track.order', $trackCode)->with('error', 'No deposit required for this order.');
        if ($order->deposit_status === 'paid')
            return redirect()->route('track.order', $trackCode)->with('msg', 'Deposit has already been paid.');
        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY'))
            return redirect()->route('track.order', $trackCode)->with('error', 'GCash payment is not configured yet.');

        $amountCentavos = (int) round((float) $order->deposit_amount * 100);
        if ($amountCentavos < 10000)
            return redirect()->route('track.order', $trackCode)->with('error', 'Minimum GCash payment is ₱100.00.');

        $successUrl = url('/track/' . $trackCode . '/deposit-return?status=success');
        $cancelUrl  = url('/track/' . $trackCode . '/deposit-return?status=cancelled');

        $phone = $this->formatPhoneE164($order->guest_phone ?? '');

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

        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
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
                'is_read'          => 0,
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
                \App\Helpers\SmsHelper::send($guestPhone,
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
            $payLine = $isFullPayment
                ? 'GCash Full ₱' . number_format($order->deposit_amount, 2) . ' ✓ Fully Paid'
                : 'GCash Deposit ₱' . number_format($order->deposit_amount, 2) . ' ✓ Paid (Balance remaining)';

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
            DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => 1]);
        } catch (\Exception $e) {}
    }

    public function payRemaining(string $trackCode)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();

        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name')
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
        $successUrl = url('/track/' . $trackCode . '/remaining-return?status=success');
        $cancelUrl  = url('/track/' . $trackCode . '/remaining-return?status=cancelled');
        $phone      = $this->formatPhoneE164($order->guest_phone ?? '');

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

        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
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
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
            ->first();

        if (!$order) abort(404);

        if ($status === 'cancelled')
            return redirect()->route('track.order', $trackCode)->with('error', 'Payment cancelled. You can try again.');

        if ($order->payment_status === 'Paid') {
            $receiptAddons = DB::table('order_addons')->where('order_id', $order->id)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();
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
            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => 'Paid',
                'paid_at'        => now(),
            ]);

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
                    DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => 1]);
                }
            }
            // ── END AUTO CONFIRM ────────────────────────────────────────

            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'GCash Payment Received - Order #' . $order->id,
                'message'          => ($order->guest_name ?? 'Guest') . ' completed GCash payment for Order #' . $order->id . '. Order auto-confirmed and sent to kitchen.',
                'is_read'          => 0,
                'created_at'       => now(),
            ]);

            $order = DB::table('orders as o')
                ->join('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.id', $order->id)
                ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
                ->first();

            $receiptAddons = DB::table('order_addons')->where('order_id', $order->id)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();

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

    public function paymentReturn(Request $request, string $trackCode)
    {
        $status = $request->input('status', '');

        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.track_code', strtoupper($trackCode))
            ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
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

                    DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => 1]);
                }
            }
            // ── END AUTO CONFIRM ────────────────────────────────────────

            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'GCash Payment Received - Order #' . $order->id,
                'message'          => ($order->guest_name ?? 'Guest') . ' paid via GCash for Order #' . $order->id . '. Order auto-confirmed and sent to kitchen.',
                'is_read'          => 0,
                'created_at'       => now(),
            ]);

            // Refresh order
            $order = DB::table('orders as o')
                ->join('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.id', $order->id)
                ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
                ->first();

            $receiptAddons = DB::table('order_addons')->where('order_id', $order->id)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();

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

    private function getPaymongoCheckoutMethods(): array
    {
        return ['gcash'];
    }

    private function formatPhoneE164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        // Remove leading country code duplicates
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '63') {
            $digits = substr($digits, 2); // strip 63 → 9XXXXXXXXX
        }
        if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
            $digits = substr($digits, 1); // strip leading 0 → 9XXXXXXXXX
        }
        // Now should be 10 digits starting with 9
        if (strlen($digits) === 10 && substr($digits, 0, 1) === '9') {
            return '+63' . $digits;
        }
        // Fallback — return as-is if already E.164
        return $phone;
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

        if ($order->deposit_status === 'paid')
            return redirect()->route('customer.orders')->with('msg', 'Deposit already paid!');

        $secretKey      = CakeshopHelper::getPaymongoSecretKey();
        $amountCentavos = (int) round((float)$order->deposit_amount * 100);

        $successUrl = route('customer.custom_orders.deposit_return', $coId) . '?status=success';
        $cancelUrl  = route('customer.custom_orders.deposit_return', $coId) . '?status=cancelled';

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
                'success_url'  => $successUrl,
                'cancel_url'   => $cancelUrl,
                'description'  => 'Custom Cake Deposit — Order #' . $order->id,
            ]]
        ];

        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
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

        if ($sessionStatus === 'completed' || $paymentStatus === 'succeeded') {
            $isFullPayment = abs((float)$order->deposit_amount - (float)$co->admin_price) < 0.01;

            DB::table('orders')->where('id', $order->id)->update([
                'deposit_status'  => 'paid',
                'deposit_paid_at' => now(),
                'payment_status'  => $isFullPayment ? 'Paid' : 'Partial Payment',
                'status'          => 'Confirmed',
                'total_price'     => $co->admin_price,
            ]);

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
                DB::table('orders')->where('id', $order->id)->update(['kitchen_sent' => 1]);
            }

            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'Custom Order #' . $order->id . ' - GCash Deposit Paid',
                'message'          => "{$fullname} paid GCash deposit of PHP {$order->deposit_amount} for Custom Order #{$order->id}. Auto-confirmed.",
                'is_read'          => 0,
                'created_at'       => now(),
            ]);

            return redirect()->route('customer.orders')->with('msg', '✅ Payment received! Your custom cake order is now confirmed. 🎂');
        }

        return redirect()->route('customer.orders')->with('err', 'Payment not completed. Please try again.');
    }


}
