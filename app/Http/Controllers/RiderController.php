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
                && in_array(($remittance->status ?? ''), ['pending', 'submitted', 'rejected'], true);

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
            'remittance_method' => ['required', 'in:gcash,cash_handover'],
            'reference_number' => ['nullable', 'string', 'min:3', 'max:120'],
            'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'rider_note' => ['nullable', 'string', 'max:500'],
        ], [
            'remittance_method.required' => 'Choose how you remitted the cash to the seller.',
            'remittance_method.in' => 'Choose either GCash transfer or cash handover.',
            'receipt.image' => 'Upload a valid receipt screenshot image.',
        ]);

        $expected = round((float) $remittance->amount, 2);
        $submitted = round((float) $validated['amount'], 2);
        if (abs($expected - $submitted) > 0.009) {
            return back()->withErrors(['amount' => 'Amount must match the collected cash: PHP ' . number_format($expected, 2) . '.'])->withInput();
        }

        if ($validated['remittance_method'] === 'gcash' && !$request->hasFile('receipt') && empty($remittance->receipt_path)) {
            return back()->withErrors(['receipt' => 'Upload the GCash transfer receipt screenshot.'])->withInput();
        }

        $receiptPath = $remittance->receipt_path;
        if ($request->hasFile('receipt')) {
            $receiptPath = $this->uploadFile($request->file('receipt'), 'uploads/rider_remittances');
            if (!$receiptPath) {
                return back()->with('err', 'Receipt upload failed. Please try a smaller JPG/PNG/WebP image.')->withInput();
            }
        }

        DB::table('rider_remittances')->where('id', $remittance->id)->update([
            'remittance_method' => $validated['remittance_method'],
            'status' => 'submitted',
            'reference_number' => trim((string) ($validated['reference_number'] ?? '')) ?: null,
            'receipt_path' => $receiptPath,
            'rider_note' => trim((string) ($validated['rider_note'] ?? '')) ?: null,
            'submitted_at' => now(),
            'rejected_at' => null,
            'seller_note' => null,
            'updated_at' => now(),
        ]);

        DB::table('order_tracking')->insert([
            'order_id' => $orderId,
            'status' => 'Cash Remittance Submitted',
            'notes' => $validated['remittance_method'] === 'gcash'
                ? 'Rider submitted GCash remittance proof.'
                : 'Rider marked cash handover to seller.',
            'created_at' => now(),
        ]);

        try {
            $freshOrder = DB::table('orders')->where('id', $orderId)->first();
            if ($freshOrder) {
                app(MobileNotificationService::class)->notifyOrderSeller(
                    $freshOrder,
                    'COD Remittance Submitted',
                    "Rider submitted cash remittance for Order #{$orderId}. Please review and confirm.",
                    ['event' => 'rider_remittance_submitted']
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Rider remittance seller notification failed: ' . $e->getMessage());
        }

        return back()->with('msg', 'Remittance submitted. The seller will confirm once received.');
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
