<?php
namespace App\Http\Controllers;

use App\Helpers\SmsHelper;
use App\Helpers\CakeshopHelper;
use App\Helpers\PaymentTransactionHelper;
use App\Services\MobileNotificationService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RiderController extends Controller
{
    use UploadsFiles;

    /** Access delivery by alphanumeric PIN entered on the catalog sidebar */
    public function accessByPin(Request $request)
    {
        $pin = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', $request->input('pin', ''))));

        if (strlen($pin) < 6) {
            return back()->with('rider_err', 'Please enter your delivery PIN from your SMS.');
        }

        $order = DB::table('orders')
            ->where('rider_pin', $pin)
            ->whereIn('status', ['Out for Delivery', 'Attempted Delivery'])
            ->whereNotNull('rider_token')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            return back()->with('rider_err', 'PIN not found or delivery already completed. Check your SMS and try again.');
        }

        return redirect()->route('rider.show', [$order->id, $order->rider_token]);
    }

    /** Show the rider portal login page */
    public function loginPage()
    {
        return view('rider.login');
    }

    /** Verify phone + PIN submitted from the rider portal login page */
    public function loginVerify(Request $request)
    {
        $phone = trim($request->input('phone', ''));
        $pin   = trim($request->input('pin', ''));

        if (!$phone || !$pin) {
            return back()->with('err', 'Please enter both your phone number and PIN.')->withInput();
        }

        $clean = preg_replace('/\D/', '', $phone);
        if (str_starts_with($clean, '0'))   $clean = '63' . substr($clean, 1);
        if (!str_starts_with($clean, '63')) $clean = '63' . $clean;
        $formats = [$phone, '+' . $clean, $clean, '0' . substr($clean, 2)];

        $rider = DB::table('riders')
            ->where('is_active', true)
            ->where(function ($q) use ($formats) {
                foreach ($formats as $f) $q->orWhere('phone', $f);
            })->first();

        if (!$rider) {
            return back()->with('err', 'Phone number not found. Please check and try again.')->withInput();
        }

        $order = DB::table('orders')
            ->where('rider_id', $rider->id)
            ->where('rider_pin', $pin)
            ->whereIn('status', ['Out for Delivery', 'Attempted Delivery'])
            ->whereNotNull('rider_token')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            return back()->with('err', 'No active delivery found for this PIN. It may have expired or already been completed.')->withInput();
        }

        return redirect()->route('rider.show', [$order->id, $order->rider_token]);
    }

    /** Resolve a pasted PHONE|PIN access code from the catalog sidebar */
    public function accessByCode(Request $request)
    {
        $raw = trim($request->input('code', ''));

        if (!str_contains($raw, '|')) {
            return back()->with('rider_err', 'Invalid code. Paste the full delivery code from your SMS (e.g. 09171234567|492847).');
        }

        [$phone, $pin] = explode('|', $raw, 2);
        $phone = trim($phone);
        $pin   = trim($pin);

        if (!$phone || !$pin) {
            return back()->with('rider_err', 'Incomplete code. Make sure you copied the full delivery code.');
        }

        $clean = preg_replace('/\D/', '', $phone);
        if (str_starts_with($clean, '0'))   $clean = '63' . substr($clean, 1);
        if (!str_starts_with($clean, '63')) $clean = '63' . $clean;
        $formats = [$phone, '+' . $clean, $clean, '0' . substr($clean, 2)];

        $rider = DB::table('riders')
            ->where('is_active', true)
            ->where(function ($q) use ($formats) {
                foreach ($formats as $f) $q->orWhere('phone', $f);
            })->first();

        if (!$rider) {
            return back()->with('rider_err', 'Code not recognized. Check that you pasted the correct code.');
        }

        $order = DB::table('orders')
            ->where('rider_id', $rider->id)
            ->where('rider_pin', $pin)
            ->whereIn('status', ['Out for Delivery', 'Attempted Delivery'])
            ->whereNotNull('rider_token')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            return back()->with('rider_err', 'No active delivery found. The code may have expired or already been used.');
        }

        return redirect()->route('rider.show', [$order->id, $order->rider_token]);
    }

    /** Show rider delivery page */
    public function show(string $orderId, string $token)
    {
        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('riders as r', 'r.id', '=', 'o.rider_id')
            ->where('o.id', $orderId)
            ->where('o.rider_token', $token)
            ->select('o.*', 'p.name as product_name', 'r.name as rider_name')
            ->first();

        if (!$order) abort(404, 'Invalid delivery link.');

        $addons = DB::table('order_addons')->where('order_id', $orderId)->get();
        $settings = CakeshopHelper::getSettings();
        $remittance = Schema::hasTable('rider_remittances')
            ? DB::table('rider_remittances')->where('order_id', $orderId)->first()
            : null;
        $shopPayout = !empty($order->shop_id)
            ? DB::table('shops')->where('id', $order->shop_id)->select('payout_account_name', 'payout_account_number')->first()
            : null;

        if (!in_array($order->status, ['Out for Delivery'], true)) {
            $needsRemittance = $remittance
                && in_array(($remittance->status ?? ''), ['pending', 'submitted', 'rejected', 'awaiting_payment', 'qr_expired'], true);

            return view('rider.delivery', [
                'order' => $order,
                'addons' => $addons,
                'settings' => $settings,
                'remittance' => $remittance,
                'shopPayout' => $shopPayout,
                'done' => !$needsRemittance,
                'remittanceOnly' => $needsRemittance,
            ]);
        }

        return view('rider.delivery', compact('order','addons','settings','remittance','shopPayout'));
    }

    public function paymentStatus(string $orderId, string $token)
    {
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->where('rider_token', $token)
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'error' => 'Invalid delivery link.'], 404);
        }

        $totalAmount = (float) ($order->total_price ?? 0);
        $depositAmount = (float) ($order->deposit_amount ?? 0);
        $isPaid = ($order->payment_status ?? '') === 'Paid';
        $depositPaid = in_array($order->payment_status ?? '', ['Partial Payment', 'Paid'], true)
            || ($order->deposit_status ?? '') === 'paid';
        $cashMethod = in_array($order->payment_method, ['COD', 'COP'], true);
        $remainingAmount = $isPaid ? 0 : max(0, $totalAmount - ($depositPaid ? $depositAmount : 0));

        if ($isPaid) {
            $state = [
                'banner_class' => 'pay-ok',
                'icon' => '✅',
                'label' => 'Payment Settled',
                'amount' => 0,
                'note' => 'No collection needed',
            ];
        } elseif ($depositPaid && $depositAmount > 0) {
            $state = [
                'banner_class' => $cashMethod ? 'pay-cod' : 'pay-gcash',
                'icon' => $cashMethod ? '💵' : '📱',
                'label' => $cashMethod ? 'Collect Remaining Balance' : 'GCash Remaining Balance Pending',
                'amount' => $remainingAmount,
                'note' => 'Deposit of ₱' . number_format($depositAmount, 2) . ' already paid',
            ];
        } elseif ($cashMethod) {
            $state = [
                'banner_class' => 'pay-cod',
                'icon' => '💵',
                'label' => 'Collect Cash from Customer',
                'amount' => $totalAmount,
                'note' => '',
            ];
        } else {
            $state = [
                'banner_class' => 'pay-gcash',
                'icon' => '📱',
                'label' => 'GCash — Not Yet Paid',
                'amount' => $totalAmount,
                'note' => 'Customer needs to pay via GCash',
            ];
        }

        return response()->json([
            'ok' => true,
            'payment_status' => $order->payment_status,
            'deposit_status' => $order->deposit_status,
            'paid_at' => $order->paid_at,
            'remaining_amount' => $remainingAmount,
            'total_amount' => $totalAmount,
            'deposit_amount' => $depositAmount,
        ] + $state)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /** Rider marks as delivered */
    public function markDelivered(Request $request, string $orderId, string $token)
    {
        $order = DB::table('orders')->where('id',$orderId)->where('rider_token',$token)->first();
        if (!$order || $order->status !== 'Out for Delivery')
            return response()->json(['ok'=>false,'error'=>'Invalid or already updated.']);

        try {
            $totalAmount = (float) ($order->total_price ?? 0);
            $depositAmount = (float) ($order->deposit_amount ?? 0);
            $depositPaid = in_array($order->payment_status ?? '', ['Partial Payment', 'Paid'], true)
                || ($order->deposit_status ?? '') === 'paid';
            $remainingAmount = max(0, $totalAmount - ($depositPaid ? $depositAmount : 0));

            if (($order->payment_method ?? '') === 'GCash'
                && ($order->payment_status ?? '') !== 'Paid'
                && $remainingAmount > 0.009) {
                return response()->json([
                    'ok' => false,
                    'error' => 'GCash payment is not fully paid yet. Ask the customer to complete the remaining balance before marking this order as delivered.',
                    'payment_blocked' => true,
                    'remaining_amount' => $remainingAmount,
                ], 422);
            }

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $file  = $request->file('photo');
                $photoPath = $this->uploadFile($file, 'uploads/delivery');
            }

            $riderNote = trim($request->input('note', '')) ?: null;

            $upd = [
                'status'           => 'Delivered',
                'delivered_at'     => now()->format('Y-m-d H:i:s'),
                'review_requested' => 1,
                'delivery_photo'   => $photoPath,
            ];

            // COD → auto Paid
            if ($order->payment_method === 'COD' && $order->payment_status !== 'Paid') {
                $upd['payment_status'] = 'Paid';
                $upd['paid_at'] = now()->format('Y-m-d H:i:s');
            }

            DB::table('orders')->where('id',$orderId)->update($upd);
            PaymentTransactionHelper::recordFinalCashIfNeeded($order, 'Delivered');
            $remittance = $this->createCashRemittanceIfNeeded($order);

            $trackingNotes = 'Marked as delivered by rider.';
            if ($photoPath)   $trackingNotes .= ' Proof of delivery photo uploaded.';
            if ($riderNote)   $trackingNotes .= ' Note: ' . $riderNote;

            DB::table('order_tracking')->insert([
                'order_id'   => $orderId,
                'status'     => 'Delivered',
                'notes'      => $trackingNotes,
                'created_at' => now(),
            ]);

            // Update rider delivery count
            if ($order->rider_id) {
                DB::table('riders')->where('id',$order->rider_id)->increment('deliveries_count');
            }

            $siteName = config('app.name','Cake Shop');
            $riderName = DB::table('riders')->where('id',$order->rider_id)->value('name') ?? 'Rider';

            // No SMS to customer on delivery — per plan, visible on tracking page
            // No SMS to admin — visible in admin panel notifications

            // Admin notification
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => "Order #{$orderId} Delivered",
                'message'          => "Rider {$riderName} marked Order #{$orderId} as delivered.",
                'is_read' => false,
                'created_at'       => now(),
            ]);

            try {
                $pushOrder = DB::table('orders')->where('id', $orderId)->first();
                if ($pushOrder) {
                    $mobile = app(MobileNotificationService::class);
                    $mobile->notifyOrderCustomer($pushOrder, 'Order Delivered', "Order #{$orderId} has been delivered.", ['event' => 'delivered']);
                    $mobile->notifyOrderSeller($pushOrder, 'Order Delivered', "Rider {$riderName} marked Order #{$orderId} as delivered.", ['event' => 'delivered']);
                }
            } catch (\Throwable $e) {
                Log::warning('Rider delivered push failed: ' . $e->getMessage());
            }

            return response()->json([
                'ok' => true,
                'needs_remittance' => (bool) $remittance,
                'remittance_amount' => $remittance ? (float) $remittance->amount : 0,
            ]);

        } catch (\Throwable $e) {
            Log::error('Rider markDelivered: ' . $e->getMessage());
            return response()->json(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
        }
    }

    public function submitRemittance(Request $request, string $orderId, string $token)
    {
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->where('rider_token', $token)
            ->first();

        if (!$order || !in_array(($order->status ?? ''), ['Delivered', 'Picked Up'], true)) {
            return back()->with('err', 'Remittance is only available after a completed delivery.');
        }

        if (!Schema::hasTable('rider_remittances')) {
            return back()->with('err', 'Remittance tracking is not ready yet. Please contact the seller.');
        }

        $remittance = DB::table('rider_remittances')
            ->where('order_id', $orderId)
            ->where('rider_id', $order->rider_id)
            ->first();

        if (!$remittance) {
            return back()->with('err', 'No cash remittance is required for this order.');
        }

        if (($remittance->status ?? '') === 'confirmed') {
            return back()->with('msg', 'This remittance was already confirmed by the seller.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'remittance_method' => ['required', 'in:cash_handover'],
            'rider_note' => ['nullable', 'string', 'max:500'],
        ], [
            'remittance_method.required' => 'Choose cash handover if you gave the money directly to the shop.',
            'remittance_method.in' => 'GCash remittance must use the PayMongo QR option.',
        ]);

        $expected = round((float) $remittance->amount, 2);
        $submitted = round((float) $validated['amount'], 2);
        if (abs($expected - $submitted) > 0.009) {
            return back()->withErrors(['amount' => 'Amount must match the collected cash: PHP ' . number_format($expected, 2) . '.'])->withInput();
        }

        DB::table('rider_remittances')->where('id', $remittance->id)->update($this->filterExistingColumns('rider_remittances', [
            'remittance_method' => $validated['remittance_method'],
            'status' => 'submitted',
            'reference_number' => null,
            'receipt_path' => null,
            'rider_note' => trim((string) ($validated['rider_note'] ?? '')) ?: null,
            'submitted_at' => now(),
            'rejected_at' => null,
            'seller_note' => null,
            'updated_at' => now(),
        ]));

        $this->addOrderTrackingSafe($orderId, 'Cash Handover Submitted', 'Rider marked COD cash as handed directly to the shop.');

        try {
            $freshOrder = DB::table('orders')->where('id', $orderId)->first();
            if ($freshOrder) {
                app(MobileNotificationService::class)->notifyOrderSeller(
                    $freshOrder,
                    'Cash Handover Confirmation Needed',
                    "Rider marked COD cash as handed to the shop for Order #{$orderId}. Please confirm only if received.",
                    ['event' => 'rider_cash_handover_submitted']
                );
                $sellerId = !empty($freshOrder->shop_id)
                    ? DB::table('shops')->where('id', $freshOrder->shop_id)->value('seller_id')
                    : null;
                if ($sellerId) {
                    DB::table('notifications')->insert([
                        'receiver_role' => 'seller',
                        'receiver_user_id' => $sellerId,
                        'order_id' => $orderId,
                        'title' => 'Cash Handover Confirmation Needed',
                        'message' => "Rider marked COD cash as handed to the shop for Order #{$orderId}. Confirm only if the cash was received.",
                        'is_read' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Rider remittance seller notification failed: ' . $e->getMessage());
        }

        return back()->with('msg', 'Cash handover submitted. The seller will confirm once received.');
    }

    public function generateRemittanceQr(Request $request, string $orderId, string $token)
    {
        $context = $this->validatedRemittanceContext($orderId, $token);
        if ($context instanceof \Illuminate\Http\RedirectResponse) return $context;

        [$order, $remittance] = $context;
        if (($remittance->status ?? '') === 'confirmed') {
            return back()->with('msg', 'This remittance was already verified.');
        }

        if (!Schema::hasColumn('rider_remittances', 'paymongo_payment_intent_id')
            || !Schema::hasColumn('rider_remittances', 'paymongo_qr_image')) {
            return back()->with('err', 'GCash QR remittance setup is not ready yet. Please run the latest database migration.');
        }

        if (($remittance->status ?? '') === 'awaiting_payment'
            && !empty($remittance->paymongo_qr_image)
            && !empty($remittance->paymongo_expires_at)
            && now()->lt($remittance->paymongo_expires_at)) {
            return back()->with('msg', 'GCash QR is still active. Scan it or tap Check Payment Status after paying.');
        }

        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        $publicKey = CakeshopHelper::getPaymongoPublicKey();
        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY')) {
            return back()->with('err', 'GCash QR remittance is not configured yet. Please ask the admin to set PayMongo secret key.');
        }
        $clientApiKey = ($publicKey && !str_contains($publicKey, 'YOUR_PUBLIC_KEY')) ? $publicKey : $secretKey;

        $amount = round((float) ($remittance->amount ?? 0), 2);
        $amountCentavos = (int) round($amount * 100);
        if ($amountCentavos < 100) {
            return back()->with('err', 'Remittance amount must be at least PHP 1.00 for PayMongo QR.');
        }

        $reference = 'REMIT-' . $remittance->id . '-' . $order->id;
        $description = 'Rider COD remittance for Order #' . $order->id;

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

            DB::table('rider_remittances')->where('id', $remittance->id)->update($this->filterExistingColumns('rider_remittances', [
                'remittance_method' => 'gcash_paymongo',
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
                'rejected_at' => null,
                'seller_note' => null,
                'updated_at' => now(),
            ]));

            $this->addOrderTrackingSafe($order->id, 'GCash QR Remittance Created', 'Rider generated a PayMongo QR Ph code for COD remittance.');
            $this->notifySellerSafe($order, 'COD Remittance QR Generated', "Rider generated a GCash QR remittance for Order #{$order->id}. Waiting for PayMongo confirmation.", 'rider_remittance_qr_created');

            return back()->with('msg', 'GCash QR generated. Scan it using GCash, then tap Check Payment Status.');
        } catch (\Throwable $e) {
            Log::error('PayMongo rider remittance QR failed', [
                'order_id' => $order->id,
                'remittance_id' => $remittance->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('err', 'Could not generate GCash QR. PayMongo: ' . $e->getMessage());
        }
    }

    public function checkRemittanceQr(Request $request, string $orderId, string $token)
    {
        $context = $this->validatedRemittanceContext($orderId, $token);
        if ($context instanceof \Illuminate\Http\RedirectResponse) return $context;

        [$order, $remittance] = $context;
        if (($remittance->status ?? '') === 'confirmed') {
            return back()->with('msg', 'This remittance was already verified.');
        }
        if (empty($remittance->paymongo_payment_intent_id)) {
            return back()->with('err', 'Generate a GCash QR first before checking payment status.');
        }

        try {
            $result = $this->syncPaymongoRemittance($remittance, $order);
            return back()->with($result['ok'] ? 'msg' : 'err', $result['message']);
        } catch (\Throwable $e) {
            Log::error('PayMongo rider remittance status check failed', [
                'order_id' => $order->id,
                'remittance_id' => $remittance->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('err', 'Could not check PayMongo payment status right now.');
        }
    }

    public function paymongoRemittanceWebhook(Request $request)
    {
        $payload = $request->all();
        $eventType = data_get($payload, 'data.attributes.type') ?: data_get($payload, 'data.type') ?: data_get($payload, 'type');
        $paymentIntentId = data_get($payload, 'data.attributes.data.attributes.payment_intent_id')
            ?: data_get($payload, 'data.attributes.data.relationships.payment_intent.data.id')
            ?: data_get($payload, 'data.attributes.data.id');
        $remittanceId = data_get($payload, 'data.attributes.data.attributes.metadata.remittance_id')
            ?: data_get($payload, 'data.attributes.data.attributes.metadata.remittanceId');

        if (!$paymentIntentId && !$remittanceId) {
            Log::info('PayMongo remittance webhook ignored', ['event' => $eventType]);
            return response()->json(['ok' => true]);
        }

        $remittance = null;
        if (Schema::hasTable('rider_remittances')) {
            $query = DB::table('rider_remittances');
            if ($paymentIntentId) {
                $query->where('paymongo_payment_intent_id', $paymentIntentId);
            } elseif ($remittanceId) {
                $query->where('id', $remittanceId);
            }
            $remittance = $query->first();
        }
        if (!$remittance) {
            return response()->json(['ok' => true]);
        }

        $order = DB::table('orders')->where('id', $remittance->order_id)->first();
        if ($order) {
            $this->syncPaymongoRemittance($remittance, $order);
        }

        return response()->json(['ok' => true]);
    }

    private function validatedRemittanceContext(string $orderId, string $token): array|\Illuminate\Http\RedirectResponse
    {
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->where('rider_token', $token)
            ->first();

        if (!$order || !in_array(($order->status ?? ''), ['Delivered', 'Picked Up'], true)) {
            return back()->with('err', 'Remittance is only available after a completed delivery.');
        }

        if (!Schema::hasTable('rider_remittances')) {
            return back()->with('err', 'Remittance tracking is not ready yet. Please contact the seller.');
        }

        $remittance = DB::table('rider_remittances')
            ->where('order_id', $orderId)
            ->where('rider_id', $order->rider_id)
            ->first();

        if (!$remittance) {
            return back()->with('err', 'No cash remittance is required for this order.');
        }

        return [$order, $remittance];
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

    private function syncPaymongoRemittance(object $remittance, object $order): array
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        if (!$secretKey) {
            return ['ok' => false, 'message' => 'PayMongo keys are not configured.'];
        }

        $intent = $this->paymongoRequest('GET', 'https://api.paymongo.com/v1/payment_intents/' . $remittance->paymongo_payment_intent_id, $secretKey);
        $status = data_get($intent, 'data.attributes.status', '');
        $paid = $status === 'succeeded';

        $updates = [
            'paymongo_status' => $status ?: null,
            'updated_at' => now(),
        ];

        if ($paid) {
            $reference = data_get($intent, 'data.attributes.payments.0.attributes.reference_number')
                ?: ($remittance->paymongo_reference ?? $remittance->reference_number ?? null);

            $updates = array_merge($updates, [
                'status' => 'confirmed',
                'remittance_method' => 'gcash_paymongo',
                'reference_number' => $reference,
                'paymongo_reference' => $reference,
                'paymongo_paid_at' => now(),
                'confirmed_at' => now(),
                'submitted_at' => now(),
                'rejected_at' => null,
                'seller_note' => 'Auto-verified via PayMongo GCash QR.',
            ]);
        } elseif (!empty($remittance->paymongo_expires_at) && now()->gte($remittance->paymongo_expires_at)) {
            $updates['status'] = 'qr_expired';
        }

        DB::table('rider_remittances')->where('id', $remittance->id)->update($this->filterExistingColumns('rider_remittances', $updates));

        if ($paid) {
            $orderUpdates = $this->filterExistingColumns('orders', [
                'settled_at' => now(),
                'updated_at' => now(),
            ]);
            if (!empty($orderUpdates)) {
                DB::table('orders')->where('id', $order->id)->update($orderUpdates);
            }
            $this->addOrderTrackingSafe($order->id, 'GCash Remittance Verified', 'COD remittance was auto-verified by PayMongo QR payment.');
            $this->notifySellerSafe($order, 'COD Remittance Paid via GCash', "PayMongo verified the GCash remittance for Order #{$order->id}.", 'rider_remittance_paid');

            return ['ok' => true, 'message' => 'GCash remittance verified by PayMongo.'];
        }

        if (($updates['status'] ?? null) === 'qr_expired') {
            return ['ok' => false, 'message' => 'This GCash QR expired. Generate a new QR and try again.'];
        }

        return ['ok' => false, 'message' => 'Payment is not completed yet. Scan the QR in GCash, then check again.'];
    }

    private function filterExistingColumns(string $table, array $values): array
    {
        if (!Schema::hasTable($table)) return [];

        return collect($values)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
            ->all();
    }

    private function addOrderTrackingSafe(string $orderId, string $status, ?string $notes = null): void
    {
        try {
            if (!Schema::hasTable('order_tracking')) return;

            $tracking = $this->filterExistingColumns('order_tracking', [
                'order_id' => $orderId,
                'status' => $status,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            if (!empty($tracking)) {
                DB::table('order_tracking')->insert($tracking);
            }
        } catch (\Throwable $e) {
            Log::warning('Rider remittance tracking insert failed: ' . $e->getMessage());
        }
    }

    private function notifySellerSafe(object $order, string $title, string $message, string $event): void
    {
        try {
            $sellerId = !empty($order->shop_id)
                ? DB::table('shops')->where('id', $order->shop_id)->value('seller_id')
                : null;

            if ($sellerId && Schema::hasTable('notifications')) {
                $notification = $this->filterExistingColumns('notifications', [
                    'receiver_role' => 'seller',
                    'receiver_user_id' => $sellerId,
                    'order_id' => $order->id,
                    'title' => $title,
                    'message' => $message,
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if (!empty($notification)) {
                    DB::table('notifications')->insert($notification);
                }
            }

            app(MobileNotificationService::class)->notifyOrderSeller($order, $title, $message, ['event' => $event]);
        } catch (\Throwable $e) {
            Log::warning('Rider remittance seller notification failed: ' . $e->getMessage());
        }
    }

    private function createCashRemittanceIfNeeded(object $order): ?object
    {
        if (!Schema::hasTable('rider_remittances')) {
            return null;
        }

        $isCashDelivery = ($order->fulfillment_type ?? '') === 'Delivery'
            && strtoupper((string) ($order->payment_method ?? '')) === 'COD';
        if (!$isCashDelivery || empty($order->shop_id)) {
            return null;
        }

        $total = (float) ($order->total_price ?? 0);
        $depositPaid = ($order->deposit_status ?? '') === 'paid';
        $deposit = $depositPaid ? (float) ($order->deposit_amount ?? 0) : 0;
        $amount = round(max(0, $total - $deposit), 2);
        if ($amount <= 0) {
            return null;
        }

        DB::table('rider_remittances')->updateOrInsert(
            ['order_id' => $order->id],
            [
                'shop_id' => $order->shop_id,
                'rider_id' => $order->rider_id,
                'amount' => $amount,
                'collection_method' => 'Cash',
                'status' => 'pending',
                'collected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        try {
            $sellerId = DB::table('shops')->where('id', $order->shop_id)->value('seller_id');
            $alreadyNotified = DB::table('notifications')
                ->where('receiver_role', 'seller')
                ->where('receiver_user_id', $sellerId)
                ->where('order_id', $order->id)
                ->where('title', 'COD Remittance Pending')
                ->exists();

            if ($sellerId && !$alreadyNotified) {
                DB::table('notifications')->insert([
                    'receiver_role' => 'seller',
                    'receiver_user_id' => $sellerId,
                    'order_id' => $order->id,
                    'title' => 'COD Remittance Pending',
                    'message' => 'Rider collected cash for Order #' . $order->id . '. Confirm after the rider remits the cash to the seller.',
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('COD remittance pending notification failed: ' . $e->getMessage());
        }

        return DB::table('rider_remittances')->where('order_id', $order->id)->first();
    }

    /** Rider reports an issue */
    public function reportIssue(Request $request, string $orderId, string $token)
    {
        $order = DB::table('orders')->where('id',$orderId)->where('rider_token',$token)->first();
        if (!$order || $order->status !== 'Out for Delivery')
            return response()->json(['ok'=>false,'error'=>'Invalid or already updated.']);

        try {
            $issueType = $request->input('issue_type'); // damaged/not_home/other
            $note      = trim($request->input('note',''));

            $photoPath = null;
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $file  = $request->file('photo');
                $photoPath = $this->uploadFile($file, 'uploads/delivery');
            }

            $newStatus = match($issueType) {
                'not_home' => 'Attempted Delivery',
                default    => 'Issue Reported',
            };

            DB::table('orders')->where('id',$orderId)->update([
                'status'            => $newStatus,
                'issue_type'        => $issueType,
                'issue_photo'       => $photoPath,
                'issue_note'        => $note ?: null,
                'issue_status'      => 'pending',
                'issue_reported_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id'   => $orderId,
                'status'     => $newStatus,
                'notes'      => "Rider reported: {$issueType}" . ($note ? " - {$note}" : ''),
                'created_at' => now(),
            ]);

            $siteName  = config('app.name','Cake Shop');
            $riderName = DB::table('riders')->where('id',$order->rider_id)->value('name') ?? 'Rider';
            $issueLabel = match($issueType) {
                'damaged'  => 'Damaged Cake',
                'not_home' => 'Customer Not Home',
                default    => 'Other Issue',
            };

            $customerSms = null;
            $custPhone = $order->guest_phone ?? null;
            if ($custPhone) {
                $shopName = SmsHelper::getShopName($order->shop_id ?? null);
                $header   = SmsHelper::header($siteName, $shopName);
                $shopLine = $shopName ? "\nShop: {$shopName}" : '';
                $custName = $order->guest_name ?? 'Customer';
                $customerSms = $issueType === 'not_home'
                    ? "{$header}\nHi {$custName}, we attempted to deliver your order but no one was available.\n\nOrder No.: #{$orderId}{$shopLine}\n\nOur team will contact you shortly to arrange a reschedule. We apologize for the inconvenience."
                    : "{$header}\nHi {$custName}, we encountered an issue with your delivery.\n\nOrder No.: #{$orderId}{$shopLine}\n\nOur team will contact you shortly to resolve this. We sincerely apologize for the inconvenience.";
            }

            // No SMS to admin — visible in admin panel notifications

            // Admin notification
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => "Delivery Issue — Order #{$orderId}",
                'message'          => "Rider {$riderName} reported: {$issueLabel}." . ($note ? " Note: {$note}" : ''),
                'is_read' => false,
                'created_at'       => now(),
            ]);

            try {
                $pushOrder = DB::table('orders')->where('id', $orderId)->first();
                if ($pushOrder) {
                    $mobile = app(MobileNotificationService::class);
                    $mobile->notifyOrderCustomer($pushOrder, 'Delivery Update', "There is a delivery update for Order #{$orderId}.", ['event' => 'delivery_issue'], $customerSms);
                    $mobile->notifyOrderSeller($pushOrder, 'Delivery Issue Reported', "Rider {$riderName} reported: {$issueLabel}.", ['event' => 'delivery_issue']);
                }
            } catch (\Throwable $e) {
                Log::warning('Rider issue push failed: ' . $e->getMessage());
            }

            return response()->json(['ok'=>true,'status'=>$newStatus]);

        } catch (\Throwable $e) {
            Log::error('Rider reportIssue: ' . $e->getMessage());
            return response()->json(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
        }
    }
}
