<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private function getPaymongoCheckoutMethods(): array
    {
        return ['gcash'];
    }

    /**
     * Initiate GCash payment via PayMongo Checkout Session API
     * Docs: https://developers.paymongo.com/reference/checkout-session-resource
     */
    public function payGcash(Request $request)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        $mode      = CakeshopHelper::getPaymongoMode();
        $orderId   = trim($request->input('order_id', ''));
        $uid       = session('user')['id'];

        // --- Validate ---
        if (!$orderId) {
            return redirect()->route('customer.orders')->with('error', 'Invalid order.');
        }
        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY')) {
            return redirect()->route('customer.orders')
                ->with('error', 'GCash payment is not configured yet. Please contact the administrator to set up PayMongo keys.');
        }

        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $orderId)
            ->where('o.user_id', $uid)
            ->select('o.*', 'p.name as product_name')
            ->first();

        if (!$order) {
            return redirect()->route('customer.orders')->with('error', 'Order not found.');
        }

        // Amount in centavos (PayMongo requires integer centavos)
        $amountCentavos = (int) round((float) $order->total_price * 100);

        if ($amountCentavos < 10000) { // PayMongo minimum is PHP 100.00
            return redirect()->route('customer.orders')
                ->with('error', 'Minimum GCash payment amount is ₱100.00.');
        }

        $successUrl = url('/customer/payment-return?order_id=' . $orderId . '&status=success');
        $cancelUrl  = url('/customer/payment-return?order_id=' . $orderId . '&status=cancelled');
        $customer   = DB::table('users')->where('id', $uid)->first();

        // --- Build Checkout Session payload ---
        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => [
                        'name'  => $customer->fullname ?? 'Customer',
                        'email' => $customer->email ?? '',
                        'phone' => $customer->phone ?? '',
                    ],
                    'line_items' => [
                        [
                            'currency'  => 'PHP',
                            'amount'    => $amountCentavos,
                            'name'      => $order->product_name . ' — Order #' . $orderId,
                            'quantity'  => 1,
                        ],
                    ],
                    'payment_method_types' => $this->getPaymongoCheckoutMethods(),
                    'success_url' => $successUrl,
                    'cancel_url'  => $cancelUrl,
                    'description' => 'CakeShop Order #' . $orderId,
                    'reference_number' => 'ORDER-' . $orderId,
                    'send_email_receipt' => true,
                    'show_description'   => true,
                    'show_line_items'    => true,
                ],
            ],
        ];

        // --- Call PayMongo API ---
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
            CURLOPT_SSL_VERIFYPEER => true, // for local dev; remove in production
            CURLOPT_TIMEOUT        => 30,
        ]);

        $res   = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // --- Log for debugging ---
        Log::info('PayMongo Checkout Session', [
            'order_id'  => $orderId,
            'http_code' => $httpCode,
            'curl_errno'=> $errno,
            'response'  => $res,
        ]);

        if ($errno) {
            Log::error('PayMongo cURL error: ' . $error);
            return redirect()->route('customer.orders')
                ->with('error', 'Network error connecting to PayMongo. Check internet connection.');
        }

        $data = json_decode($res, true);

        // --- Check for API errors ---
        if (isset($data['errors'])) {
            $errMsg = $data['errors'][0]['detail'] ?? 'PayMongo API error.';
            Log::error('PayMongo API error: ' . $errMsg);
            return redirect()->route('customer.orders')
                ->with('error', 'PayMongo: ' . $errMsg);
        }

        $sessionId   = $data['data']['id'] ?? null;
        $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

        if (!$sessionId || !$checkoutUrl) {
            Log::error('PayMongo: no session id or checkout_url. Response: ' . $res);
            return redirect()->route('customer.orders')
                ->with('error', 'Could not create GCash payment session. Check your PayMongo keys.');
        }

        // Save session ID to order
        DB::table('orders')->where('id', $orderId)->update([
            'paymongo_link_id' => $sessionId,
        ]);

        // Redirect customer to GCash payment page
        return redirect()->away($checkoutUrl);
    }

    /**
     * Initiate deposit payment for COD / Pickup orders
     */
    public function payDeposit(Request $request, string $id)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        $uid       = session('user')['id'];

        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $id)
            ->where('o.user_id', $uid)
            ->select('o.*', 'p.name as product_name')
            ->first();

        if (!$order)
            return redirect()->route('customer.orders')->with('error', 'Order not found.');
        if (!$order->deposit_required)
            return redirect()->route('customer.orders')->with('error', 'No deposit required for this order.');
        if ($order->deposit_status === 'paid')
            return redirect()->route('customer.orders')->with('msg', 'Deposit already paid. Your order is pending confirmation.');
        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY'))
            return redirect()->route('customer.orders')->with('error', 'Online payment is not configured yet. Contact the shop.');

        $amountCentavos = (int) round((float) $order->deposit_amount * 100);
        if ($amountCentavos < 10000)
            return redirect()->route('customer.orders')->with('error', 'Minimum deposit amount is ₱100.00.');

        $successUrl = url('/customer/deposit-return?order_id=' . $id . '&status=success');
        $cancelUrl  = url('/customer/deposit-return?order_id=' . $id . '&status=cancelled');
        $customer   = DB::table('users')->where('id', $uid)->first();

        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => [
                        'name'  => $customer->fullname ?? 'Customer',
                        'email' => $customer->email ?? '',
                        'phone' => $customer->phone ?? '',
                    ],
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount'   => $amountCentavos,
                        'name'     => 'Deposit (50%) — ' . $order->product_name . ' Order #' . $id,
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => $this->getPaymongoCheckoutMethods(),
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'description'          => 'Deposit for Order #' . $id . ' (' . $order->payment_method . ')',
                    'reference_number'     => 'DEP-' . $id,
                    'send_email_receipt'   => true,
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
        $errMsg   = curl_error($ch);
        curl_close($ch);

        Log::info('PayMongo Deposit Session', ['order_id' => $id, 'response' => $res]);

        if ($errno) {
            Log::error('PayMongo deposit cURL error: ' . $errMsg);
            return redirect()->route('customer.orders')->with('error', 'Network error. Please try again.');
        }

        $data        = json_decode($res, true);
        $sessionId   = $data['data']['id'] ?? null;
        $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

        if (!$sessionId || !$checkoutUrl) {
            $apiErr = $data['errors'][0]['detail'] ?? 'Could not create payment session.';
            return redirect()->route('customer.orders')->with('error', 'PayMongo: ' . $apiErr);
        }

        DB::table('orders')->where('id', $id)->update(['deposit_paymongo_id' => $sessionId]);

        return redirect()->away($checkoutUrl);
    }

    /**
     * Handle return from PayMongo after deposit payment
     */
    public function depositReturn(Request $request)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        $orderId   = trim($request->input('order_id', ''));
        $urlStatus = $request->input('status', '');
        $uid       = session('user')['id'];

        if (!$orderId) return redirect()->route('customer.orders');

        if ($urlStatus === 'cancelled') {
            return redirect()->route('customer.orders')
                ->with('error', 'Deposit payment cancelled. You can try again from your orders page.');
        }

        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $orderId)
            ->where('o.user_id', $uid)
            ->select('o.*', 'p.name as product_name')
            ->first();

        if (!$order) return redirect()->route('customer.orders');

        if ($order->deposit_status === 'paid') {
            return redirect()->route('customer.orders')
                ->with('msg', 'Deposit already paid! Your order is now pending confirmation from the baker.');
        }

        $sessionStatus = '';
        $paymentStatus = '';

        if ($order->deposit_paymongo_id && $secretKey) {
            $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . $order->deposit_paymongo_id);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'Authorization: Basic ' . base64_encode($secretKey . ':'),
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $apiRes        = json_decode(curl_exec($ch), true);
            curl_close($ch);
            $sessionStatus = $apiRes['data']['attributes']['status'] ?? '';
            $paymentStatus = $apiRes['data']['attributes']['payment_intent']['attributes']['status'] ?? '';
        }

        $paymentConfirmed = ($sessionStatus === 'completed' || $paymentStatus === 'succeeded')
            || ($urlStatus === 'success' && $order->deposit_required && $order->deposit_paymongo_id);

        if (!$paymentConfirmed) {
            return redirect()->route('customer.orders')
                ->with('error', 'Payment could not be confirmed. Contact the shop if your GCash was deducted.');
        }

        $isFullPayment = abs((float) $order->deposit_amount - (float) $order->total_price) < 0.01;

        DB::table('orders')->where('id', $orderId)->update([
            'deposit_status' => 'paid',
            'status'         => 'Pending',
            'payment_status' => $isFullPayment ? 'Paid' : 'Partial Payment',
            'paid_at'        => $isFullPayment ? now() : null,
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $orderId,
            'status'     => 'Deposit Paid',
            'notes'      => 'Deposit of ₱' . number_format($order->deposit_amount, 2) . ' paid via GCash.',
            'created_at' => now(),
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $orderId,
            'status'     => 'Pending',
            'notes'      => 'Order is now pending baker confirmation.',
            'created_at' => now(),
        ]);

        $custName = session('user')['fullname'] ?? 'Customer';
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Deposit Paid — Order #' . $orderId,
            'message'          => "{$custName} paid ₱" . number_format($order->deposit_amount, 2) . " deposit for Order #{$orderId}.",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'Deposit Payment', "Deposit paid for Order #{$orderId}.");

        return redirect()->route('customer.orders')
            ->with('msg', 'Deposit paid! Your order is now pending confirmation from the baker. The remaining balance will be collected upon ' . ($order->fulfillment_type === 'Delivery' ? 'delivery' : 'pickup') . '.');
    }

    /**
     * Handle return from PayMongo after payment
     */
    public function paymentReturn(Request $request)
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        $orderId   = trim($request->input('order_id', ''));
        $status    = $request->input('status', '');
        $uid       = session('user')['id'];

        if (!$orderId) {
            return redirect()->route('customer.orders');
        }

        // If customer cancelled
        if ($status === 'cancelled') {
            return view('customer.payment_return', ['success' => false, 'orderId' => $orderId, 'cancelled' => true, 'receipt' => null, 'receiptAddons' => collect()]);
        }

        $row = DB::table('orders')
            ->where('id', $orderId)
            ->where('user_id', $uid)
            ->select('paymongo_link_id', 'payment_status')
            ->first();

        if (!$row) {
            return redirect()->route('customer.orders');
        }

        // Already marked paid — don't re-check
        if ($row->payment_status === 'Paid') {
            $receipt = DB::table('orders as o')
                ->join('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.id', $orderId)
                ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
                ->first();
            $receiptAddons = DB::table('order_addons')->where('order_id', $orderId)->get();
            $vatSettings   = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();
            return view('customer.payment_return', ['success' => true, 'orderId' => $orderId, 'cancelled' => false, 'receipt' => $receipt, 'receiptAddons' => $receiptAddons, 'vatSettings' => $vatSettings, 'pmReference' => null]);
        }

        if (!$row->paymongo_link_id || !$secretKey) {
            return view('customer.payment_return', ['success' => false, 'orderId' => $orderId, 'cancelled' => false, 'receipt' => null, 'receiptAddons' => collect()]);
        }

        // --- Verify checkout session with PayMongo ---
        $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions/{$row->paymongo_link_id}");
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

        $sessionStatus  = $res['data']['attributes']['status'] ?? '';
        $paymentStatus  = $res['data']['attributes']['payment_intent']['attributes']['status'] ?? '';

        Log::info('PayMongo payment return', [
            'order_id'       => $orderId,
            'session_status' => $sessionStatus,
            'payment_status' => $paymentStatus,
        ]);

        // Mark as paid if session is completed or payment succeeded
        if ($sessionStatus === 'completed' || $paymentStatus === 'succeeded') {
            DB::table('orders')->where('id', $orderId)->update([
                'payment_status' => 'Paid',
                'paid_at'        => now(),
            ]);

            // Notify admin
            $custName = session('user')['fullname'] ?? 'Customer';
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'GCash Payment Received — Order #' . $orderId,
                'message'          => "{$custName} paid via GCash for Order #{$orderId}.",
                'is_read' => false,
                'created_at'       => now(),
            ]);

            CakeshopHelper::logActivity($uid, 'customer', 'GCash Payment', "Order #{$orderId} paid via GCash.");

            $receipt = DB::table('orders as o')
                ->join('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.id', $orderId)
                ->select('o.*', 'p.name as product_name', 'p.image_path as product_image')
                ->first();
            $receiptAddons   = DB::table('order_addons')->where('order_id', $orderId)->get();
            $vatSettings     = DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();
            $pmReference     = $res['data']['attributes']['payments'][0]['attributes']['reference_number']
                               ?? ($res['data']['attributes']['reference_number'] ?? null);

            return view('customer.payment_return', [
                'success'       => true,
                'orderId'       => $orderId,
                'cancelled'     => false,
                'receipt'       => $receipt,
                'receiptAddons' => $receiptAddons,
                'vatSettings'   => $vatSettings,
                'pmReference'   => $pmReference,
            ]);
        }

        return view('customer.payment_return', ['success' => false, 'orderId' => $orderId, 'cancelled' => false, 'receipt' => null, 'receiptAddons' => collect()]);
    }
}
