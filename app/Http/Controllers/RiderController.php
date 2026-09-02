<?php
namespace App\Http\Controllers;

use App\Helpers\SmsHelper;
use App\Helpers\CakeshopHelper;
use App\Helpers\PaymentTransactionHelper;
use App\Services\MobileNotificationService;
use App\Services\RiderAssignmentService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            ->whereNotNull('rider_token')
            ->orderByDesc('id')
            ->get()
            ->first(fn ($candidate) => $this->isRiderAccessibleOrder($candidate));

        if (!$order) {
            return back()->with('rider_err', 'PIN not found or no rider action is needed for this order.');
        }

        return redirect()->route('rider.show', [$order->id, $order->rider_token]);
    }

    /** Show the rider portal login page */
    public function loginPage()
    {
        return redirect()->route('login');
    }

    /** Verify phone + PIN submitted from the rider portal login page */
    public function loginVerify(Request $request)
    {
        $phone = trim($request->input('phone', ''));
        $pin   = trim($request->input('pin', ''));

        if (!$phone || !$pin) {
            return back()->with('err', 'Please enter your phone number and rider PIN.')->withInput();
        }

        $formats = $this->phoneFormats($phone);

        $rider = DB::table('riders')
            ->where('is_active', true)
            ->where(function ($q) use ($formats) {
                foreach ($formats as $f) $q->orWhere('phone', $f);
            })->first();

        if (!$rider) {
            return back()->with('err', 'Phone number not found. Please check and try again.')->withInput();
        }

        if (!empty($rider->login_pin_hash) && Hash::check($pin, $rider->login_pin_hash)) {
            session([
                'rider' => [
                    'id' => (int) $rider->id,
                    'name' => $rider->name,
                    'phone' => $rider->phone,
                    'shop_id' => $rider->shop_id,
                ],
            ]);

            if (Schema::hasColumn('riders', 'password_must_change') && (bool) ($rider->password_must_change ?? false)) {
                return redirect()->route('rider.password.setup');
            }

            return redirect()->route('rider.dashboard');
        }

        $order = DB::table('orders')
            ->where('rider_id', $rider->id)
            ->where('rider_pin', $pin)
            ->whereNotNull('rider_token')
            ->orderByDesc('id')
            ->get()
            ->first(fn ($candidate) => $this->isRiderAccessibleOrder($candidate));

        if (!$order) {
            return back()->with('err', 'PIN is not valid for this rider account, or no action is needed anymore.')->withInput();
        }

        return redirect()->route('rider.show', [$order->id, $order->rider_token]);
    }

    public function dashboard(Request $request, RiderAssignmentService $assignments)
    {
        $rider = $this->sessionRider();
        if (!$rider) {
            return redirect()->route('login')->with('error', 'Please log in to view your rider dashboard.');
        }
        if (Schema::hasColumn('riders', 'password_must_change') && (bool) ($rider->password_must_change ?? false)) {
            return redirect()->route('rider.password.setup');
        }

        $assignments->expirePendingAssignments(null, (int) $rider->id);
        $lat = $request->filled('lat') ? (float) $request->query('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->query('lng') : null;
        $this->expireRiderBatches((int) $rider->id);
        if ($request->query('remittance_payment') === 'success') {
            $this->syncRiderActiveBatch((int) $rider->id);
        }
        $orders = $this->riderDashboardOrders((int) $rider->id, $lat, $lng);
        $unremitted = $this->riderUnremittedSummary((int) $rider->id);
        $bulkRemittance = $this->riderBulkRemittanceSummary((int) $rider->id);
        $settings = CakeshopHelper::getSettings();

        return view('rider.dashboard', compact('rider', 'orders', 'unremitted', 'bulkRemittance', 'settings', 'lat', 'lng'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget('rider');
        return redirect()->route('login')->with('msg', 'Rider logged out.');
    }

    public function passwordSetup()
    {
        $rider = $this->sessionRider();
        if (!$rider) {
            return redirect()->route('login')->with('error', 'Please log in with your rider phone and temporary PIN first.');
        }

        if (Schema::hasColumn('riders', 'password_must_change') && !(bool) ($rider->password_must_change ?? false)) {
            return redirect()->route('rider.dashboard');
        }

        return view('rider.password_setup', compact('rider'));
    }

    public function passwordSetupUpdate(Request $request)
    {
        $rider = $this->sessionRider();
        if (!$rider) {
            return redirect()->route('login')->with('error', 'Please log in with your rider phone and temporary PIN first.');
        }

        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('confirm_password', '');
        $error = $this->passwordValidationError($password, $confirm);
        if ($error) {
            return back()->with('err', $error);
        }

        $updates = $this->filterExistingColumns('riders', [
            'login_pin_hash' => Hash::make($password),
            'login_pin_set_at' => now(),
            'password_must_change' => false,
            'password_changed_at' => now(),
            'password_reset_otp' => null,
            'password_reset_expires_at' => null,
            'updated_at' => now(),
        ]);

        DB::table('riders')->where('id', $rider->id)->update($updates);
        CakeshopHelper::logActivity($rider->id, 'rider', 'Set Password', 'Rider created a personal password');

        return redirect()->route('rider.dashboard')->with('msg', 'Your rider password has been updated.');
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

        $formats = $this->phoneFormats($phone);

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
            ->whereNotNull('rider_token')
            ->orderByDesc('id')
            ->get()
            ->first(fn ($candidate) => $this->isRiderAccessibleOrder($candidate));

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

        app(RiderAssignmentService::class)->expirePendingAssignments(null, (int) ($order->rider_id ?? 0));
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
        $remittance = null;
        $shopPayout = !empty($order->shop_id)
            ? DB::table('shops')->where('id', $order->shop_id)->select('payout_account_name', 'payout_account_number')->first()
            : null;

        $assignmentPending = ($order->rider_assignment_status ?? '') === 'pending';
        $assignmentExpired = ($order->rider_assignment_status ?? '') === 'expired';
        $assignmentDeclined = ($order->rider_assignment_status ?? '') === 'declined';

        if ($assignmentPending || $assignmentExpired || $assignmentDeclined) {
            return view('rider.delivery', [
                'order' => $order,
                'addons' => $addons,
                'settings' => $settings,
                'remittance' => $remittance,
                'shopPayout' => $shopPayout,
                'done' => false,
                'assignmentPending' => $assignmentPending,
                'assignmentExpired' => $assignmentExpired,
                'assignmentDeclined' => $assignmentDeclined,
            ]);
        }

        if (!in_array($order->status, ['Out for Delivery'], true)) {
            return $this->closedDeliveryAttemptRedirect($order);
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
        if (($order->rider_assignment_status ?? '') === 'pending') {
            return response()->json(['ok' => false, 'error' => 'Please accept this delivery before marking it delivered.'], 422);
        }

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
                'rider_pin'        => null,
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
                'redirect_url' => route('rider.dashboard'),
            ]);

        } catch (\Throwable $e) {
            Log::error('Rider markDelivered: ' . $e->getMessage());
            return response()->json(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
        }
    }

    public function submitRemittance(Request $request, string $orderId, string $token)
    {
        return redirect()->route('rider.dashboard')->with('err', 'Per-order remittance has moved to the rider dashboard. Please remit your COD total there.');
    }

    public function generateBulkRemittanceQr(Request $request)
    {
        $rider = $this->sessionRider();
        if (!$rider) {
            return redirect()->route('login')->with('error', 'Please log in to remit COD cash.');
        }

        if (!Schema::hasTable('rider_remittances') || !Schema::hasTable('rider_remittance_batches')) {
            return back()->with('err', 'Bulk remittance is not ready yet. Please run the latest database migration.');
        }

        if (!Schema::hasColumn('rider_remittances', 'batch_id')) {
            return back()->with('err', 'Bulk remittance needs the latest database migration.');
        }

        $openPayment = $request->boolean('open_payment', true);
        $activeBatch = $this->activeRiderBatch((int) $rider->id);
        if ($activeBatch) {
            if ($openPayment && !empty($activeBatch->paymongo_action_url)) {
                return redirect()->away($activeBatch->paymongo_action_url);
            }

            return back()->with('msg', 'Your GCash bulk remittance payment is still active. Please check its payment status before creating another one.');
        }

        $rows = $this->eligibleBulkRemittanceRows((int) $rider->id);
        if ($rows->isEmpty()) {
            return back()->with('err', 'No COD cash is ready for bulk remittance.');
        }

        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        $publicKey = CakeshopHelper::getPaymongoPublicKey();
        if (!$secretKey || str_contains($secretKey, 'YOUR_SECRET_KEY')) {
            return back()->with('err', 'GCash QR remittance is not configured yet. Please ask the admin to set PayMongo secret key.');
        }

        $clientApiKey = ($publicKey && !str_contains($publicKey, 'YOUR_PUBLIC_KEY')) ? $publicKey : $secretKey;
        $total = round((float) $rows->sum('amount'), 2);
        $amountCentavos = (int) round($total * 100);
        if ($amountCentavos < 100) {
            return back()->with('err', 'Bulk remittance amount must be at least PHP 1.00.');
        }

        $shopId = (string) ($rider->shop_id ?? $rows->first()->shop_id);
        $batchId = null;

        try {
            $batchId = DB::transaction(function () use ($rows, $rider, $shopId, $total) {
                $id = DB::table('rider_remittance_batches')->insertGetId([
                    'shop_id' => $shopId,
                    'rider_id' => $rider->id,
                    'total_amount' => $total,
                    'order_count' => $rows->count(),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('rider_remittances')
                    ->whereIn('id', $rows->pluck('id')->all())
                    ->whereIn('status', ['pending', 'rejected', 'qr_expired'])
                    ->update($this->filterExistingColumns('rider_remittances', [
                        'batch_id' => $id,
                        'updated_at' => now(),
                    ]));

                return $id;
            });

            $reference = 'REMIT-BATCH-' . $batchId;
            $description = 'Rider COD bulk remittance for ' . $rows->count() . ' order(s)';
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

            $checkoutSessionId = null;
            $checkoutUrl = null;
            try {
                $checkout = $this->createBulkRemittanceCheckoutSession($batchId, $rows, $amountCentavos, $reference, $description, $secretKey);
                $checkoutSessionId = data_get($checkout, 'data.id');
                $checkoutUrl = data_get($checkout, 'data.attributes.checkout_url');
            } catch (\Throwable $checkoutError) {
                Log::warning('PayMongo bulk remittance checkout fallback failed', [
                    'batch_id' => $batchId,
                    'error' => $checkoutError->getMessage(),
                ]);
            }

            DB::table('rider_remittance_batches')->where('id', $batchId)->update([
                'status' => 'awaiting_payment',
                'remittance_method' => 'gcash_paymongo',
                'reference_number' => $reference,
                'paymongo_payment_intent_id' => $intentId,
                'paymongo_payment_method_id' => $methodId,
                'paymongo_checkout_session_id' => $checkoutSessionId,
                'paymongo_client_key' => $clientKey,
                'paymongo_qr_image' => $qrImage,
                'paymongo_action_url' => $actionUrl ?: $checkoutUrl,
                'paymongo_status' => $paymongoStatus,
                'paymongo_reference' => $reference,
                'paymongo_expires_at' => now()->addMinutes(30),
                'updated_at' => now(),
            ]);

            DB::table('rider_remittances')->whereIn('id', $rows->pluck('id')->all())->update($this->filterExistingColumns('rider_remittances', [
                'status' => 'awaiting_payment',
                'remittance_method' => 'gcash_paymongo',
                'reference_number' => $reference,
                'paymongo_payment_intent_id' => $intentId,
                'paymongo_payment_method_id' => $methodId,
                'paymongo_checkout_session_id' => $checkoutSessionId,
                'paymongo_client_key' => $clientKey,
                'paymongo_qr_image' => $qrImage,
                'paymongo_action_url' => $actionUrl ?: $checkoutUrl,
                'paymongo_status' => $paymongoStatus,
                'paymongo_reference' => $reference,
                'paymongo_expires_at' => now()->addMinutes(30),
                'updated_at' => now(),
            ]));

            if ($openPayment && ($actionUrl || $checkoutUrl)) {
                return redirect()->away($actionUrl ?: $checkoutUrl);
            }

            return back()->with('msg', 'Bulk GCash QR generated. Pay the total amount, then tap Check Payment Status.');
        } catch (\Throwable $e) {
            if ($batchId) {
                DB::table('rider_remittances')->where('batch_id', $batchId)->update($this->filterExistingColumns('rider_remittances', [
                    'batch_id' => null,
                    'status' => 'pending',
                    'updated_at' => now(),
                ]));
                DB::table('rider_remittance_batches')->where('id', $batchId)->update([
                    'status' => 'failed',
                    'seller_note' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
            }

            Log::error('PayMongo bulk rider remittance QR failed', [
                'rider_id' => $rider->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('err', 'Could not generate bulk GCash QR. PayMongo: ' . $e->getMessage());
        }
    }

    public function checkBulkRemittanceQr(Request $request)
    {
        $rider = $this->sessionRider();
        if (!$rider) {
            return redirect()->route('login')->with('error', 'Please log in to check remittance payment.');
        }

        $batch = $this->activeRiderBatch((int) $rider->id);
        if (!$batch) {
            return back()->with('err', 'No active bulk GCash remittance payment found.');
        }

        try {
            $result = $this->syncPaymongoBatchRemittance($batch);
            return back()->with($result['ok'] ? 'msg' : 'err', $result['message']);
        } catch (\Throwable $e) {
            Log::error('PayMongo bulk rider remittance status check failed', [
                'rider_id' => $rider->id,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('err', 'Could not check PayMongo payment status right now.');
        }
    }

    public function acceptAssignment(string $orderId, string $token, RiderAssignmentService $assignments)
    {
        $order = DB::table('orders')->where('id', $orderId)->where('rider_token', $token)->first();
        if (!$order || ($order->rider_assignment_status ?? '') !== 'pending') {
            return back()->with('err', 'This assignment is no longer pending.');
        }

        if (!empty($order->rider_assignment_expires_at) && now()->gte(\Carbon\Carbon::parse($order->rider_assignment_expires_at))) {
            $assignments->expirePendingAssignments(null, (int) ($order->rider_id ?? 0));
            return back()->with('err', 'This assignment already expired. Please wait for the seller to assign again.');
        }

        $assignments->accept($order);
        return redirect()->route('rider.show', [$orderId, $token])->with('msg', 'Delivery accepted. You can now proceed.');
    }

    public function declineAssignment(Request $request, string $orderId, string $token, RiderAssignmentService $assignments)
    {
        $order = DB::table('orders')->where('id', $orderId)->where('rider_token', $token)->first();
        if (!$order || ($order->rider_assignment_status ?? '') !== 'pending') {
            return back()->with('err', 'This assignment is no longer pending.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ], [
            'reason.required' => 'Please tell the seller why you cannot accept this delivery.',
        ]);

        $assignments->decline($order, trim($validated['reason']));
        return redirect()->route('rider.dashboard')->with('msg', 'Delivery declined. Seller has been notified.');
    }

    public function generateRemittanceQr(Request $request, string $orderId, string $token)
    {
        return redirect()->route('rider.dashboard')->with('err', 'Per-order GCash remittance has moved to the rider dashboard. Please pay your COD total there.');
    }

    public function checkRemittanceQr(Request $request, string $orderId, string $token)
    {
        return redirect()->route('rider.dashboard')->with('err', 'Per-order GCash remittance checks have moved to the rider dashboard.');
    }

    public function paymongoRemittanceWebhook(Request $request)
    {
        $payload = $request->all();
        $eventType = data_get($payload, 'data.attributes.type') ?: data_get($payload, 'data.type') ?: data_get($payload, 'type');
        $paymentIntentId = data_get($payload, 'data.attributes.data.attributes.payment_intent_id')
            ?: data_get($payload, 'data.attributes.data.relationships.payment_intent.data.id')
            ?: data_get($payload, 'data.attributes.data.id');
        $batchId = data_get($payload, 'data.attributes.data.attributes.metadata.batch_id')
            ?: data_get($payload, 'data.attributes.data.attributes.metadata.batchId');
        $remittanceId = data_get($payload, 'data.attributes.data.attributes.metadata.remittance_id')
            ?: data_get($payload, 'data.attributes.data.attributes.metadata.remittanceId');

        if (Schema::hasTable('rider_remittance_batches')) {
            $batch = null;
            $batchQuery = DB::table('rider_remittance_batches');
            if ($paymentIntentId) {
                $batchQuery->where('paymongo_payment_intent_id', $paymentIntentId);
            } elseif ($batchId) {
                $batchQuery->where('id', $batchId);
            }
            if ($paymentIntentId || $batchId) {
                $batch = $batchQuery->first();
            }
            if ($batch) {
                $this->syncPaymongoBatchRemittance($batch);
                return response()->json(['ok' => true]);
            }
        }

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

    private function createBulkRemittanceCheckoutSession(int $batchId, $rows, int $amountCentavos, string $reference, string $description, string $secretKey): array
    {
        $returnUrl = route('rider.dashboard');

        return $this->paymongoRequest('POST', 'https://api.paymongo.com/v2/checkout_sessions', $secretKey, [
            'data' => [
                'attributes' => [
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount' => $amountCentavos,
                        'name' => 'COD Bulk Remittance',
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => ['gcash'],
                    'success_url' => $returnUrl . '?remittance_payment=success',
                    'cancel_url' => $returnUrl . '?remittance_payment=cancelled',
                    'description' => $description,
                    'reference_number' => $reference,
                    'send_email_receipt' => false,
                    'show_description' => true,
                    'show_line_items' => true,
                    'metadata' => [
                        'batch_id' => (string) $batchId,
                        'rider_id' => (string) $rows->first()->rider_id,
                        'shop_id' => (string) $rows->first()->shop_id,
                        'order_ids' => $rows->pluck('order_id')->implode(','),
                    ],
                ],
            ],
        ]);
    }

    private function syncRiderActiveBatch(int $riderId): void
    {
        $batch = $this->activeRiderBatch($riderId);
        if (!$batch) return;

        try {
            $this->syncPaymongoBatchRemittance($batch);
        } catch (\Throwable $e) {
            Log::warning('Rider bulk remittance return sync failed: ' . $e->getMessage());
        }
    }

    private function expireRiderBatches(int $riderId): void
    {
        if (!Schema::hasTable('rider_remittance_batches')) return;

        $expired = DB::table('rider_remittance_batches')
            ->where('rider_id', $riderId)
            ->where('status', 'awaiting_payment')
            ->whereNotNull('paymongo_expires_at')
            ->where('paymongo_expires_at', '<=', now())
            ->pluck('id');

        if ($expired->isEmpty()) return;

        DB::table('rider_remittance_batches')->whereIn('id', $expired)->update([
            'status' => 'qr_expired',
            'updated_at' => now(),
        ]);

        DB::table('rider_remittances')->whereIn('batch_id', $expired)->where('status', 'awaiting_payment')->update($this->filterExistingColumns('rider_remittances', [
            'status' => 'qr_expired',
            'updated_at' => now(),
        ]));
    }

    private function syncPaymongoBatchRemittance(object $batch): array
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        if (!$secretKey) {
            return ['ok' => false, 'message' => 'PayMongo keys are not configured.'];
        }

        $intent = !empty($batch->paymongo_payment_intent_id)
            ? $this->paymongoRequest('GET', 'https://api.paymongo.com/v1/payment_intents/' . $batch->paymongo_payment_intent_id, $secretKey)
            : [];
        $status = data_get($intent, 'data.attributes.status', '');
        $reference = data_get($intent, 'data.attributes.payments.0.attributes.reference_number')
            ?: ($batch->paymongo_reference ?? $batch->reference_number ?? null);
        $paid = $status === 'succeeded';

        if (!$paid && !empty($batch->paymongo_checkout_session_id)) {
            $checkout = $this->paymongoRequest('GET', 'https://api.paymongo.com/v1/checkout_sessions/' . $batch->paymongo_checkout_session_id, $secretKey);
            $checkoutStatus = data_get($checkout, 'data.attributes.status', '');
            $checkoutPaymentStatus = data_get($checkout, 'data.attributes.payment_intent.attributes.status', '');
            $paid = $checkoutStatus === 'completed' || $checkoutPaymentStatus === 'succeeded';
            $status = $paid ? 'succeeded' : ($status ?: $checkoutPaymentStatus ?: $checkoutStatus);
            $reference = data_get($checkout, 'data.attributes.payments.0.attributes.reference_number')
                ?: data_get($checkout, 'data.attributes.reference_number')
                ?: $reference;
        }

        $batchUpdates = [
            'paymongo_status' => $status ?: null,
            'updated_at' => now(),
        ];
        $rowUpdates = [
            'paymongo_status' => $status ?: null,
            'updated_at' => now(),
        ];

        if ($paid) {
            $batchUpdates = array_merge($batchUpdates, [
                'status' => 'confirmed',
                'remittance_method' => 'gcash_paymongo',
                'reference_number' => $reference,
                'paymongo_reference' => $reference,
                'paymongo_paid_at' => now(),
                'confirmed_at' => now(),
                'submitted_at' => now(),
                'rejected_at' => null,
                'seller_note' => 'Auto-verified via PayMongo bulk GCash remittance.',
            ]);
            $rowUpdates = array_merge($rowUpdates, [
                'status' => 'confirmed',
                'remittance_method' => 'gcash_paymongo',
                'reference_number' => $reference,
                'paymongo_reference' => $reference,
                'paymongo_paid_at' => now(),
                'confirmed_at' => now(),
                'submitted_at' => now(),
                'rejected_at' => null,
                'seller_note' => 'Auto-verified via PayMongo bulk remittance #' . $batch->id . '.',
            ]);
        } elseif (!empty($batch->paymongo_expires_at) && now()->gte($batch->paymongo_expires_at)) {
            $batchUpdates['status'] = 'qr_expired';
            $rowUpdates['status'] = 'qr_expired';
        }

        DB::transaction(function () use ($batch, $batchUpdates, $rowUpdates, $paid) {
            DB::table('rider_remittance_batches')->where('id', $batch->id)->update($batchUpdates);
            DB::table('rider_remittances')->where('batch_id', $batch->id)->update($this->filterExistingColumns('rider_remittances', $rowUpdates));

            if ($paid) {
                $orderIds = DB::table('rider_remittances')->where('batch_id', $batch->id)->pluck('order_id')->all();
                $orderUpdates = $this->filterExistingColumns('orders', [
                    'settled_at' => now(),
                    'updated_at' => now(),
                ]);
                if (!empty($orderIds) && !empty($orderUpdates)) {
                    DB::table('orders')->whereIn('id', $orderIds)->update($orderUpdates);
                }

                foreach ($orderIds as $orderId) {
                    $this->addOrderTrackingSafe((string) $orderId, 'Bulk GCash Remittance Verified', 'COD remittance was auto-verified by PayMongo bulk payment #' . $batch->id . '.', 'seller');
                }
            }
        });

        if ($paid) {
            return ['ok' => true, 'message' => 'Bulk GCash remittance verified by PayMongo.'];
        }

        if (($batchUpdates['status'] ?? null) === 'qr_expired') {
            return ['ok' => false, 'message' => 'This bulk GCash QR expired. Generate a new payment and try again.'];
        }

        return ['ok' => false, 'message' => 'Payment is not completed yet. Open GCash payment, then check again.'];
    }

    private function createRemittanceCheckoutSession(object $order, object $remittance, int $amountCentavos, string $reference, string $description, string $secretKey): array
    {
        $returnUrl = route('rider.show', [$order->id, $order->rider_token]);

        return $this->paymongoRequest('POST', 'https://api.paymongo.com/v2/checkout_sessions', $secretKey, [
            'data' => [
                'attributes' => [
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount' => $amountCentavos,
                        'name' => 'COD Remittance - Order #' . $order->id,
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => ['gcash'],
                    'success_url' => $returnUrl . '?remittance_payment=success',
                    'cancel_url' => $returnUrl . '?remittance_payment=cancelled',
                    'description' => $description,
                    'reference_number' => $reference,
                    'send_email_receipt' => false,
                    'show_description' => true,
                    'show_line_items' => true,
                ],
            ],
        ]);
    }

    private function syncPaymongoRemittance(object $remittance, object $order): array
    {
        $secretKey = CakeshopHelper::getPaymongoSecretKey();
        if (!$secretKey) {
            return ['ok' => false, 'message' => 'PayMongo keys are not configured.'];
        }

        $intent = !empty($remittance->paymongo_payment_intent_id)
            ? $this->paymongoRequest('GET', 'https://api.paymongo.com/v1/payment_intents/' . $remittance->paymongo_payment_intent_id, $secretKey)
            : [];
        $status = data_get($intent, 'data.attributes.status', '');
        $reference = data_get($intent, 'data.attributes.payments.0.attributes.reference_number')
            ?: ($remittance->paymongo_reference ?? $remittance->reference_number ?? null);
        $paid = $status === 'succeeded';

        if (!$paid && !empty($remittance->paymongo_checkout_session_id)) {
            $checkout = $this->paymongoRequest('GET', 'https://api.paymongo.com/v1/checkout_sessions/' . $remittance->paymongo_checkout_session_id, $secretKey);
            $checkoutStatus = data_get($checkout, 'data.attributes.status', '');
            $checkoutPaymentStatus = data_get($checkout, 'data.attributes.payment_intent.attributes.status', '');
            $paid = $checkoutStatus === 'completed' || $checkoutPaymentStatus === 'succeeded';
            $status = $paid ? 'succeeded' : ($status ?: $checkoutPaymentStatus ?: $checkoutStatus);
            $reference = data_get($checkout, 'data.attributes.payments.0.attributes.reference_number')
                ?: data_get($checkout, 'data.attributes.reference_number')
                ?: $reference;
        }

        $updates = [
            'paymongo_status' => $status ?: null,
            'updated_at' => now(),
        ];

        if ($paid) {
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
            $this->addOrderTrackingSafe($order->id, 'GCash Remittance Verified', 'COD remittance was auto-verified by PayMongo QR payment.', 'seller');
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

    private function phoneFormats(string $phone): array
    {
        $clean = preg_replace('/\D/', '', $phone);
        if (str_starts_with($clean, '0')) {
            $clean = '63' . substr($clean, 1);
        }
        if ($clean !== '' && !str_starts_with($clean, '63')) {
            $clean = '63' . $clean;
        }

        return array_values(array_unique(array_filter([
            $phone,
            $clean ? '+' . $clean : null,
            $clean ?: null,
            strlen($clean) > 2 ? '0' . substr($clean, 2) : null,
        ])));
    }

    private function sessionRider(): ?object
    {
        $session = session('rider');
        if (!is_array($session) || empty($session['id'])) {
            return null;
        }

        return DB::table('riders')->where('id', $session['id'])->where('is_active', true)->first();
    }

    private function closedDeliveryAttemptRedirect(object $order): \Illuminate\Http\RedirectResponse
    {
        $rider = $this->sessionRider();
        if ($rider && (int) $rider->id === (int) ($order->rider_id ?? 0)) {
            return redirect()->route('rider.dashboard')->with('msg', 'This delivery attempt is already closed. Continue from your rider dashboard.');
        }

        return redirect()->route('login')->with('error', 'This delivery link has expired. Please log in to your rider account.');
    }

    private function isRiderAccessibleOrder(object $order): bool
    {
        return ($order->status ?? '') === 'Out for Delivery';
    }

    private function orderNeedsRemittance(object $order): bool
    {
        if (!Schema::hasTable('rider_remittances')) {
            return false;
        }

        return DB::table('rider_remittances')
            ->where('order_id', $order->id)
            ->whereIn('status', ['pending', 'submitted', 'rejected', 'awaiting_payment', 'qr_expired'])
            ->exists();
    }

    private function riderDashboardOrders(int $riderId, ?float $lat, ?float $lng)
    {
        $query = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('rider_remittances as rr', 'rr.order_id', '=', 'o.id')
            ->where('o.rider_id', $riderId)
            ->whereNotIn('o.status', ['Cancelled'])
            ->select(
                'o.*',
                DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"),
                'p.image_path',
                'rr.amount as remittance_amount',
                'rr.status as remittance_status',
                'rr.remittance_method',
                'rr.batch_id'
            );

        $orders = $query->get()->filter(fn ($order) => $this->isDashboardVisibleOrder($order));
        $orders->each(function ($order) use ($lat, $lng) {
            $order->distance_km = null;
            if ($lat !== null && $lng !== null && isset($order->latitude, $order->longitude) && $order->latitude && $order->longitude) {
                $order->distance_km = $this->haversineKm($lat, $lng, (float) $order->latitude, (float) $order->longitude);
            }
            $order->rider_bucket = $this->riderOrderBucket($order);
        });

        return $orders->sortBy([
            fn ($a, $b) => $this->bucketRank($a->rider_bucket) <=> $this->bucketRank($b->rider_bucket),
            fn ($a, $b) => ($a->distance_km ?? 999999) <=> ($b->distance_km ?? 999999),
            fn ($a, $b) => strcmp((string) ($a->schedule_date ?? ''), (string) ($b->schedule_date ?? '')),
            fn ($a, $b) => strcmp((string) ($a->schedule_time ?? ''), (string) ($b->schedule_time ?? '')),
        ])->values();
    }

    private function riderUnremittedSummary(int $riderId): array
    {
        if (!Schema::hasTable('rider_remittances')) {
            return ['total' => 0, 'count' => 0, 'needs_action' => 0, 'waiting_seller' => 0, 'waiting_paymongo' => 0, 'rejected' => 0];
        }

        $rows = DB::table('rider_remittances')
            ->where('rider_id', $riderId)
            ->whereIn('status', ['pending', 'submitted', 'rejected', 'awaiting_payment', 'qr_expired'])
            ->get();

        return [
            'total' => round((float) $rows->sum('amount'), 2),
            'count' => $rows->count(),
            'needs_action' => $rows->whereIn('status', ['pending', 'qr_expired'])->count(),
            'waiting_seller' => $rows->where('status', 'submitted')->count(),
            'waiting_paymongo' => $rows->where('status', 'awaiting_payment')->count(),
            'rejected' => $rows->where('status', 'rejected')->count(),
        ];
    }

    private function riderBulkRemittanceSummary(int $riderId): array
    {
        $empty = [
            'ready_total' => 0,
            'ready_count' => 0,
            'waiting_total' => 0,
            'waiting_count' => 0,
            'confirmed_total' => 0,
            'confirmed_count' => 0,
            'orders' => collect(),
            'active_batch' => null,
        ];

        if (!Schema::hasTable('rider_remittances')) {
            return $empty;
        }

        $readyRows = $this->eligibleBulkRemittanceRows($riderId);
        $waitingRows = DB::table('rider_remittances')
            ->where('rider_id', $riderId)
            ->whereIn('status', ['awaiting_payment', 'submitted'])
            ->get();
        $confirmedRows = DB::table('rider_remittances')
            ->where('rider_id', $riderId)
            ->where('status', 'confirmed')
            ->where('updated_at', '>=', now()->subDays(7))
            ->get();

        return [
            'ready_total' => round((float) $readyRows->sum('amount'), 2),
            'ready_count' => $readyRows->count(),
            'waiting_total' => round((float) $waitingRows->sum('amount'), 2),
            'waiting_count' => $waitingRows->count(),
            'confirmed_total' => round((float) $confirmedRows->sum('amount'), 2),
            'confirmed_count' => $confirmedRows->count(),
            'orders' => $readyRows,
            'active_batch' => $this->activeRiderBatch($riderId),
        ];
    }

    private function eligibleBulkRemittanceRows(int $riderId)
    {
        if (!Schema::hasTable('rider_remittances')) {
            return collect();
        }

        $query = DB::table('rider_remittances as rr')
            ->join('orders as o', 'o.id', '=', 'rr.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('rr.rider_id', $riderId)
            ->whereIn('rr.status', ['pending', 'rejected', 'qr_expired'])
            ->whereNotIn('o.status', ['Cancelled', 'Issue Reported', 'Attempted Delivery'])
            ->select(
                'rr.*',
                DB::raw("COALESCE(p.name, 'Custom Cake') as product_name"),
                'o.guest_name',
                'o.schedule_date',
                'o.schedule_time'
            );

        if (Schema::hasColumn('rider_remittances', 'batch_id') && Schema::hasTable('rider_remittance_batches')) {
            $query->where(function ($q) {
                $q->whereNull('rr.batch_id')
                    ->orWhereExists(function ($batchQuery) {
                        $batchQuery->select(DB::raw(1))
                            ->from('rider_remittance_batches as rb')
                            ->whereColumn('rb.id', 'rr.batch_id')
                            ->whereIn('rb.status', ['qr_expired', 'failed', 'rejected']);
                    });
            });
        }

        return $query->orderBy('rr.collected_at')->orderBy('rr.id')->get();
    }

    private function activeRiderBatch(int $riderId): ?object
    {
        if (!Schema::hasTable('rider_remittance_batches')) {
            return null;
        }

        return DB::table('rider_remittance_batches')
            ->where('rider_id', $riderId)
            ->where('status', 'awaiting_payment')
            ->where(function ($q) {
                $q->whereNull('paymongo_expires_at')
                    ->orWhere('paymongo_expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }

    private function isDashboardVisibleOrder(object $order): bool
    {
        if (($order->rider_assignment_status ?? '') === 'pending') {
            return true;
        }

        if (in_array(($order->status ?? ''), ['Out for Delivery', 'Attempted Delivery'], true)) {
            return true;
        }

        return in_array(($order->remittance_status ?? ''), ['pending', 'submitted', 'rejected', 'awaiting_payment', 'qr_expired'], true);
    }

    private function riderOrderBucket(object $order): string
    {
        if (($order->rider_assignment_status ?? '') === 'pending') {
            return 'Pending Acceptance';
        }
        if (($order->remittance_status ?? '') === 'rejected') {
            return 'Remittance Rejected';
        }
        if (in_array(($order->remittance_status ?? ''), ['pending', 'qr_expired'], true)) {
            return 'Delivered - Remit Needed';
        }
        if (($order->remittance_status ?? '') === 'awaiting_payment') {
            return 'GCash Waiting Payment';
        }
        if (($order->remittance_status ?? '') === 'submitted') {
            return 'Cash Waiting Seller';
        }

        return 'Active Delivery';
    }

    private function bucketRank(string $bucket): int
    {
        return match ($bucket) {
            'Pending Acceptance' => 0,
            'Remittance Rejected' => 1,
            'Delivered - Remit Needed' => 2,
            'GCash Waiting Payment' => 3,
            'Cash Waiting Seller' => 4,
            default => 5,
        };
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function addOrderTrackingSafe(string $orderId, string $status, ?string $notes = null, ?string $receiverRole = null): void
    {
        try {
            if (!Schema::hasTable('order_tracking')) return;

            $tracking = $this->filterExistingColumns('order_tracking', [
                'order_id' => $orderId,
                'status' => $status,
                'notes' => $notes,
                'sender_role' => $receiverRole ? 'rider' : null,
                'receiver_role' => $receiverRole,
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
                'rider_pin'         => null,
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

            return response()->json([
                'ok' => true,
                'status' => $newStatus,
                'redirect_url' => route('rider.dashboard'),
            ]);

        } catch (\Throwable $e) {
            Log::error('Rider reportIssue: ' . $e->getMessage());
            return response()->json(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
        }
    }

    private function passwordValidationError(string $password, string $confirm): ?string
    {
        if (strlen($password) < 8) return 'Password must be at least 8 characters.';
        if ($password !== $confirm) return 'Passwords do not match.';
        if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least 1 uppercase letter.';
        if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least 1 number.';
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?`~]/', $password)) return 'Password must contain at least 1 special character.';

        return null;
    }
}
