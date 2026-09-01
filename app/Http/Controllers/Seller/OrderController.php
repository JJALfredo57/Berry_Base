<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\PaymentTransactionHelper;
use App\Helpers\SmsHelper;
use App\Services\CustomerRiskService;
use App\Services\MobileNotificationService;
use App\Services\OrderRefundService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    use UploadsFiles;

    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403);
        return $shop;
    }

    public function index(Request $request)
    {
        $shop   = $this->getShop();
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'All');
        $hasRemittanceTable = Schema::hasTable('rider_remittances');

        try {
            $orders = DB::table('orders as o')
                ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
                ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
                ->where('o.shop_id', $shop->id)
                ->select(
                    'o.*',
                    DB::raw("COALESCE(o.guest_name, o.fullname, u.fullname, 'Customer') as order_customer_name"),
                    DB::raw('COALESCE(o.guest_phone, u.phone) as order_customer_phone'),
                    'p.name as product_name',
                    'p.image_path as product_image_path'
                )
                ->when($search, fn($q) => $q->where(function ($sq) use ($search, $hasRemittanceTable) {
                    $sq->whereRaw("o.track_code ilike ?", ["%$search%"])
                        ->orWhereRaw("COALESCE(o.guest_name, o.fullname, u.fullname) ilike ?", ["%$search%"])
                        ->orWhereRaw("p.name ilike ?", ["%$search%"])
                        ->orWhereRaw("o.payment_status ilike ?", ["%$search%"])
                        ->orWhereRaw("o.payment_method ilike ?", ["%$search%"])
                        ->orWhereRaw("o.status ilike ?", ["%$search%"]);

                    if ($hasRemittanceTable) {
                        $sq->orWhereExists(function ($rq) use ($search) {
                            $rq->select(DB::raw(1))
                                ->from('rider_remittances as rr')
                                ->whereColumn('rr.order_id', 'o.id')
                                ->whereRaw("rr.status ilike ?", ["%$search%"]);
                        });
                    }
                }))
                ->when($status && $status !== 'All', fn($q) =>
                    $status === 'Cancel Requests'
                        ? $q->where('o.cancel_status', 'pending')
                        : $q->where('o.status', $status)
                )
                ->orderByRaw("
                    CASE
                        WHEN o.status = 'Pickup' THEN 0
                        WHEN o.status IN ('Pending','Pending Review') THEN 1
                        WHEN o.status IN ('Confirmed','Preparing','Out for Delivery') THEN 2
                        WHEN o.status IN ('Delivered','Picked Up','Cancelled') THEN 4
                        ELSE 3
                    END
                ")
                ->orderByRaw("CASE WHEN o.status = 'Pickup' THEN o.created_at ELSE NULL END ASC")
                ->orderByDesc('o.id')
                ->paginate(10)
                ->withQueryString();
        } catch (\Throwable $e) {
            Log::error('Seller orders index failed: ' . $e->getMessage(), [
                'shop_id' => $shop->id,
                'status'  => $status,
                'search'  => $search,
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->with('err', 'Could not load orders. Error: ' . $e->getMessage());
        }

        $orderIds    = collect($orders->items())->pluck('id')->toArray();
        $orderAddons = [];
        $customData  = [];
        $orderRefunds = [];
        $paymentReceipts = [];
        $riderRemittances = [];
        if ($orderIds) {
            try {
                $addons = DB::table('order_addons')->whereIn('order_id', $orderIds)->get();
                foreach ($addons as $a) $orderAddons[$a->order_id][] = $a;
                $customs = DB::table('custom_orders')->whereIn('order_id', $orderIds)->get();
                foreach ($customs as $c) $customData[$c->order_id] = $c;
                if (\Illuminate\Support\Facades\Schema::hasTable('order_refunds')) {
                    $refundRows = DB::table('order_refunds')->whereIn('order_id', $orderIds)->orderByDesc('id')->get();
                    foreach ($refundRows as $r) {
                        if (!isset($orderRefunds[$r->order_id])) $orderRefunds[$r->order_id] = $r;
                    }
                }
                if (Schema::hasTable('payment_transactions')) {
                    $receiptRows = DB::table('payment_transactions')
                        ->whereIn('order_id', $orderIds)
                        ->orderByDesc('paid_at')
                        ->orderByDesc('id')
                        ->get();
                    foreach ($receiptRows as $receipt) $paymentReceipts[$receipt->order_id][] = $receipt;
                }
                if ($hasRemittanceTable) {
                    $remittanceRows = DB::table('rider_remittances')
                        ->whereIn('order_id', $orderIds)
                        ->orderByDesc('id')
                        ->get();
                    foreach ($remittanceRows as $remittance) $riderRemittances[$remittance->order_id] = $remittance;
                }
            } catch (\Throwable $e) {
                Log::error('Seller orders addons/customs failed: ' . $e->getMessage());
            }
        }

        $customerRiskMap = [];
        $riskService = app(CustomerRiskService::class);
        foreach ($orders->items() as $order) {
            $customerRiskMap[$order->id] = $riskService->badge($order->order_customer_phone ?? null, $shop->id);
        }
        $pendingCancelCount = (int) DB::table('orders')
            ->where('shop_id', $shop->id)
            ->where('cancel_status', 'pending')
            ->count();

        try {
            return response(
                view('seller.orders', compact('shop', 'orders', 'orderAddons', 'customData', 'orderRefunds', 'paymentReceipts', 'riderRemittances', 'customerRiskMap', 'pendingCancelCount', 'search', 'status'))->render()
            );
        } catch (\Throwable $e) {
            Log::error('Seller orders VIEW render failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('err', 'Page render error: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $order = DB::table('orders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$order) return back()->with('err', 'Order not found.');

        $newStatus = $request->input('status');
        $allowed   = ['Confirmed','Preparing','Pickup','Out for Delivery','Delivered','Picked Up','Cancelled'];
        if (!in_array($newStatus, $allowed)) return back()->with('err', 'Invalid status.');

        // Cancellation requires reason
        if ($newStatus === 'Cancelled') {
            $request->validate(['cancel_reason' => 'required|string|min:5'],[
                'cancel_reason.required' => 'Please provide a reason for cancellation.',
            ]);
        }

        // COP orders store payment_method='COP'; older orders may have stored 'COD' for pickup.
        // Both cases mean cash is collected at the counter when the customer picks up.
        $isCashPickup = ($order->fulfillment_type ?? 'Pickup') === 'Pickup'
            && in_array(strtoupper((string) $order->payment_method), ['COP', 'COD']);

        // Picked Up requires settled payment; Cash on Pickup is settled during pickup confirmation.
        if ($newStatus === 'Picked Up' && $order->payment_status !== 'Paid' && !$isCashPickup) {
            return back()->with('err', 'Cannot mark as Picked Up — customer still has an unpaid balance. Payment must be completed first.');
        }

        $upd = [
            'status'        => $newStatus,
            'cancel_reason' => $newStatus === 'Cancelled' ? $request->input('cancel_reason') : $order->cancel_reason,
            'updated_at'    => now(),
        ];
        if ($newStatus === 'Picked Up') {
            $upd['delivered_at']     = now()->format('Y-m-d H:i:s');
            $upd['review_requested'] = 1;
            if ($isCashPickup && $order->payment_status !== 'Paid') {
                $upd['payment_status'] = 'Paid';
                $upd['paid_at']        = now()->format('Y-m-d H:i:s');
            }
        }
        DB::table('orders')->where('id', $id)->update($upd);
        if ($newStatus === 'Picked Up') {
            $freshOrder = DB::table('orders')->where('id', $id)->first();
            PaymentTransactionHelper::recordFinalCashIfNeeded($freshOrder ?? $order, $newStatus);
        }

        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => $newStatus,
            'notes'      => $request->input('notes', ''),
            'created_at' => now(),
        ]);

        $mobileStatusNotice = ['push_sent' => 0];
        try {
            $pushOrder = DB::table('orders')->where('id', $id)->first();
            if ($pushOrder) {
                $mobileStatusNotice = app(MobileNotificationService::class)->notifyOrderCustomer(
                    $pushOrder,
                    'Order Update: ' . ($newStatus === 'Pickup' ? 'Ready for Pickup' : $newStatus),
                    "Order #{$id} is now " . ($newStatus === 'Pickup' ? 'ready for pickup' : $newStatus) . '.',
                    ['event' => 'order_status']
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Seller order status push failed: ' . $e->getMessage());
        }

        // SMS + in-app notification — send only for actionable statuses
        try {
            $siteName  = config('app.name', 'Cake Shop');
            $shopName  = SmsHelper::getShopName($shop->id ?? null);
            $header    = SmsHelper::header($siteName, $shopName);
            $shopLine  = $shopName ? "\nShop: {$shopName}" : '';
            $custName  = $order->guest_name
                ?? DB::table('users')->where('id', $order->user_id)->value('fullname')
                ?? 'Customer';
            $smsMsgs = [
                'Pickup'           => "{$header}\nHi {$custName}! Your order is ready!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Ready for Pickup\n\nYour cake is now ready for pickup. Please visit our shop at your earliest convenience.\n\nYour Tracking Code: {$order->track_code}",
                'Out for Delivery' => "{$header}\nHi {$custName}! Your order is on its way!\n\nOrder No.: #{$id}{$shopLine}\nStatus: Out for Delivery\n\nOur rider is now heading to your location. Please make sure someone is available to receive your order.\n\nYour Tracking Code: {$order->track_code}",
                'Cancelled'        => "{$header}\nHi {$custName}, your order has been cancelled.\n\nOrder No.: #{$id}{$shopLine}\nStatus: Cancelled\n\nIf you have questions or concerns, please contact us through our shop page. We hope to serve you again soon.",
            ];
            $phone = $order->guest_phone ?? DB::table('users')->where('id', $order->user_id)->value('phone');
            if ($phone && isset($smsMsgs[$newStatus]) && (int) ($mobileStatusNotice['push_sent'] ?? 0) <= 0) {
                SmsHelper::send($phone, $smsMsgs[$newStatus]);
            }

            // In-app notification for registered customers
            if ($order->user_id) {
                $notifMsgs = [
                    'Confirmed'        => "Your order #{$id} has been confirmed!",
                    'Preparing'        => "Your order #{$id} is now being prepared.",
                    'Pickup'           => "Your order #{$id} is ready for pickup!",
                    'Out for Delivery' => "Your order #{$id} is on its way!",
                    'Picked Up'        => "Your order #{$id} has been picked up. Enjoy!",
                    'Cancelled'        => "Your order #{$id} has been cancelled.",
                ];
                if (isset($notifMsgs[$newStatus])) {
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'customer',
                        'receiver_user_id' => $order->user_id,
                        'title'            => 'Order Update: ' . $newStatus,
                        'message'          => $notifMsgs[$newStatus],
                        'is_read' => false,
                        'created_at'       => now(),
                    ]);
                }
                if (in_array($newStatus, ['Picked Up', 'Delivered'])) {
                    DB::table('notifications')->insert([
                        'receiver_role'    => 'customer',
                        'receiver_user_id' => $order->user_id,
                        'title'            => 'Rate Your Order #' . $id,
                        'message'          => "How was your cake? Please leave a rating for Order #{$id}!",
                        'is_read' => false,
                        'created_at'       => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {}

        return back()->with('msg', "Order status updated to {$newStatus}.");
    }

    public function realtimeStatus(string $id)
    {
        $shop  = $this->getShop();
        $order = DB::table('orders')
            ->where('id', $id)
            ->where('shop_id', $shop->id)
            ->select(
                'id',
                'status',
                'payment_method',
                'payment_status',
                'deposit_status',
                'deposit_amount',
                'total_price',
                'paid_at',
                'updated_at'
            )
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);
        }

        $isCashPickup = ($order->status ?? '') === 'Pickup'
            && in_array(strtoupper((string) $order->payment_method), ['COP', 'COD'], true);

        return response()->json([
            'ok'                 => true,
            'id'                 => $order->id,
            'status'             => $order->status,
            'display_status'     => $order->status === 'Pickup' ? 'Ready for Pickup' : $order->status,
            'payment_method'     => $order->payment_method,
            'payment_status'     => $order->payment_status,
            'deposit_status'     => $order->deposit_status,
            'can_confirm_pickup' => $order->status === 'Pickup' && ($order->payment_status === 'Paid' || $isCashPickup),
            'remaining_balance'  => max(0, (float)($order->total_price ?? 0) - (float)($order->deposit_amount ?? 0)),
            'paid_at'            => (string) ($order->paid_at ?? ''),
            'updated_at'         => (string) ($order->updated_at ?? ''),
        ]);
    }

    public function receipt(string $id, int $transactionId)
    {
        $shop = $this->getShop();
        $transaction = DB::table('payment_transactions as pt')
            ->join('orders as o', 'o.id', '=', 'pt.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('pt.id', $transactionId)
            ->where('pt.order_id', $id)
            ->where('o.shop_id', $shop->id)
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
            ->first();

        if (!$transaction) abort(404, 'Receipt not found.');

        $transaction->type_label = PaymentTransactionHelper::typeLabel($transaction->type);
        $receiptAddons = DB::table('order_addons')->where('order_id', $transaction->order_id)->get();
        $vatSettings = DB::table('site_settings')->where('shop_id', $shop->id)->select('vat_enabled','vat_rate','tin_number','site_title')->first()
            ?: DB::table('site_settings')->select('vat_enabled','vat_rate','tin_number','site_title')->first();

        return view('guest.payment_transaction_receipt', [
            'trackCode' => $transaction->track_code,
            'transaction' => $transaction,
            'receiptAddons' => $receiptAddons,
            'vatSettings' => $vatSettings,
            'backUrl' => route('seller.orders'),
            'backLabel' => 'Back to Seller Orders',
        ]);
    }

    public function confirmRemittance(Request $request, string $id, int $remittanceId)
    {
        $shop = $this->getShop();
        $remittance = $this->sellerRemittance($shop->id, $id, $remittanceId);
        if (!$remittance) return back()->with('err', 'Remittance record not found.');
        if (($remittance->status ?? '') === 'confirmed') return back()->with('msg', 'This remittance is already confirmed.');
        if (!in_array(($remittance->status ?? ''), ['pending', 'submitted', 'rejected'], true)) {
            return back()->with('err', 'This remittance cannot be confirmed from its current status.');
        }

        $validated = $request->validate([
            'seller_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($remittance, $id, $validated) {
                $updates = $this->filterExistingColumns('rider_remittances', [
                    'status' => 'confirmed',
                    'remittance_method' => $remittance->remittance_method ?: 'cash_handover',
                    'seller_note' => trim((string) ($validated['seller_note'] ?? '')) ?: null,
                    'confirmed_at' => now(),
                    'rejected_at' => null,
                    'updated_at' => now(),
                ]);
                if (empty($updates)) {
                    throw new \RuntimeException('No matching rider_remittances columns available for confirmation.');
                }

                DB::table('rider_remittances')->where('id', $remittance->id)->update($updates);

                $orderUpdates = $this->filterExistingColumns('orders', [
                    'settled_at' => now(),
                    'updated_at' => now(),
                ]);
                if (!empty($orderUpdates)) {
                    DB::table('orders')->where('id', $id)->update($orderUpdates);
                }

                $this->addOrderTrackingSafe(
                    $id,
                    'Cash Remittance Confirmed',
                    ($remittance->status ?? '') === 'pending'
                        ? 'Seller confirmed COD cash received directly.'
                        : 'Seller confirmed rider cash remittance.',
                    'seller'
                );
            });
        } catch (\Throwable $e) {
            Log::error('Seller confirm remittance failed', [
                'order_id' => $id,
                'remittance_id' => $remittanceId,
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('err', 'Unable to confirm remittance right now. Please try again or contact admin.');
        }

        $this->logSellerActivitySafe('Confirm Rider Remittance', "Order #{$id}");
        return back()->with('msg', "Cash remittance for Order #{$id} confirmed.");
    }

    public function remittanceActionRedirect(string $id, int $remittanceId)
    {
        $shop = $this->getShop();
        $remittance = $this->sellerRemittance($shop->id, $id, $remittanceId);
        if (!$remittance) {
            return redirect()->route('seller.orders')->with('err', 'Remittance record not found.');
        }

        return redirect()
            ->route('seller.orders', ['search' => $id])
            ->with('err', 'For safety, confirm or reject COD remittance using the buttons on the Orders page.');
    }

    public function rejectRemittance(Request $request, string $id, int $remittanceId)
    {
        $shop = $this->getShop();
        $remittance = $this->sellerRemittance($shop->id, $id, $remittanceId);
        if (!$remittance) return back()->with('err', 'Remittance record not found.');
        if (($remittance->status ?? '') === 'confirmed') return back()->with('err', 'Confirmed remittance cannot be rejected.');

        $validated = $request->validate([
            'seller_note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'seller_note.required' => 'Please explain what the rider needs to correct.',
        ]);

        try {
            DB::transaction(function () use ($remittance, $id, $validated) {
                $updates = $this->filterExistingColumns('rider_remittances', [
                    'status' => 'rejected',
                    'seller_note' => trim($validated['seller_note']),
                    'rejected_at' => now(),
                    'updated_at' => now(),
                ]);
                if (empty($updates)) {
                    throw new \RuntimeException('No matching rider_remittances columns available for rejection.');
                }

                DB::table('rider_remittances')->where('id', $remittance->id)->update($updates);
                $this->addOrderTrackingSafe($id, 'Cash Remittance Rejected', trim($validated['seller_note']), 'seller');
            });
        } catch (\Throwable $e) {
            Log::error('Seller reject remittance failed', [
                'order_id' => $id,
                'remittance_id' => $remittanceId,
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('err', 'Unable to reject remittance right now. Please try again or contact admin.');
        }

        $this->logSellerActivitySafe('Reject Rider Remittance', "Order #{$id}");
        return back()->with('msg', "Remittance for Order #{$id} rejected. Rider can resubmit corrected details.");
    }

    private function logSellerActivitySafe(string $action, string $description = ''): void
    {
        $userId = data_get(session('user'), 'id');
        if (!$userId) return;

        try {
            CakeshopHelper::logActivity($userId, 'seller', $action, $description);
        } catch (\Throwable $e) {
            Log::warning('Seller activity log failed', [
                'action' => $action,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function filterExistingColumns(string $table, array $values): array
    {
        if (!Schema::hasTable($table)) return [];

        return collect($values)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
            ->all();
    }

    private function addOrderTrackingSafe(string $orderId, string $status, ?string $notes = null, ?string $receiverRole = null): void
    {
        if (!Schema::hasTable('order_tracking')) return;

        $tracking = $this->filterExistingColumns('order_tracking', [
            'order_id' => $orderId,
            'status' => $status,
            'notes' => $notes,
            'sender_role' => 'seller',
            'receiver_role' => $receiverRole,
            'created_at' => now(),
        ]);

        if (!empty($tracking)) {
            DB::table('order_tracking')->insert($tracking);
        }
    }

    private function sellerRemittance(string $shopId, string $orderId, int $remittanceId): ?object
    {
        if (!Schema::hasTable('rider_remittances')) return null;

        return DB::table('rider_remittances as rr')
            ->join('orders as o', 'o.id', '=', 'rr.order_id')
            ->where('rr.id', $remittanceId)
            ->where('rr.order_id', $orderId)
            ->where('rr.shop_id', $shopId)
            ->where('o.shop_id', $shopId)
            ->select('rr.*')
            ->first();
    }

    public function approveCancel(Request $request, string $id, OrderRefundService $refunds)
    {
        $shop = $this->getShop();
        $order = DB::table('orders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$order) return back()->with('err', 'Order not found.');

        $refund = $refunds->pendingForOrder($id);
        if (!$refund) return back()->with('err', 'No pending refund request found for this order.');

        $validated = $request->validate([
            'refund_receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'reference_number' => 'nullable|string|max:120',
            'admin_note' => 'nullable|string|max:500',
        ], [
            'refund_receipt.required' => 'Upload the GCash refund receipt before approving.',
            'refund_receipt.image' => 'The refund receipt must be an image.',
        ]);

        $receiptPath = $this->uploadFile($request->file('refund_receipt'), 'uploads/refund_receipts');
        if (!$receiptPath) return back()->with('err', 'Receipt upload failed. Please try again.');

        $user = session('user');
        $refunds->markRefunded(
            $order,
            $refund,
            $receiptPath,
            $validated['reference_number'] ?? null,
            trim((string) ($validated['admin_note'] ?? 'Cancellation approved and refund sent.')),
            'seller',
            (string) ($user['id'] ?? '')
        );

        return back()->with('msg', "Order #{$id} cancelled and refund receipt sent to customer tracking page.");
    }

    public function rejectCancel(Request $request, string $id, OrderRefundService $refunds)
    {
        $shop = $this->getShop();
        $order = DB::table('orders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$order) return back()->with('err', 'Order not found.');

        $refund = $refunds->pendingForOrder($id);
        if (!$refund) return back()->with('err', 'No pending refund request found for this order.');

        $validated = $request->validate([
            'admin_note' => 'required|string|min:5|max:500',
        ], [
            'admin_note.required' => 'Please provide a reason for rejecting this cancellation.',
        ]);

        $user = session('user');
        $refunds->reject($order, $refund, trim($validated['admin_note']), 'seller', (string) ($user['id'] ?? ''));

        return back()->with('msg', "Order #{$id} cancellation/refund request rejected.");
    }

    public function assignRider(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $order = DB::table('orders')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$order) return back()->with('err', 'Order not found.');

        $validated = $request->validate([
            'rider_id' => 'required|exists:riders,id',
        ],[
            'rider_id.required' => 'Please select a rider.',
        ]);

        $rider = DB::table('riders')->where('id', $validated['rider_id'])
            ->where('shop_id', $shop->id)->first();
        if (!$rider) return back()->with('err', 'Rider not found.');

        DB::table('orders')->where('id', $id)->update([
            'rider_id'   => $rider->id,
            'status'     => 'Out for Delivery',
            'updated_at' => now(),
        ]);
        DB::table('order_tracking')->insert([
            'order_id'   => $id,
            'status'     => 'Out for Delivery',
            'notes'      => "Assigned to rider: {$rider->name}",
            'created_at' => now(),
        ]);

        // SMS rider
        $riderSmsSent = null;
        if ($rider->phone) {
            try {
                $riderToken = $order->rider_token;
                if (!$riderToken) {
                    $riderToken = bin2hex(random_bytes(16));
                    DB::table('orders')->where('id', $id)->update(['rider_token' => $riderToken]);
                }
                $siteName  = config('app.name', 'Cake Shop');
                $shopName  = SmsHelper::getShopName($shop->id ?? null);
                $header    = SmsHelper::header($siteName, $shopName);
                $custName  = $order->guest_name
                    ?? DB::table('users')->where('id', $order->user_id)->value('fullname')
                    ?? 'Customer';
                $custPhone = $order->guest_phone
                    ?? DB::table('users')->where('id', $order->user_id)->value('phone')
                    ?? '';
                $addr      = $order->delivery_address ?? 'N/A';

                $riderPin = SmsHelper::generateRiderPin();
                DB::table('orders')->where('id', $id)->update(['rider_pin' => $riderPin]);

                $riderSms = SmsHelper::buildRiderSms(
                    $header, $id, $custName, $custPhone, $addr,
                    SmsHelper::paymentLine($order), $riderPin, $rider->phone, $riderToken
                );
                $riderUrl = route('rider.show', [$id, $riderToken], false);
                $riderNotice = app(MobileNotificationService::class)->notifyRider(
                    (int) $rider->id,
                    $rider->phone,
                    'New Delivery Assigned',
                    "Order #{$id} is assigned to you. Open the delivery page for details.",
                    ['event' => 'rider_assigned', 'order_id' => (string) $id, 'url' => $riderUrl],
                    $riderSms,
                    $riderUrl
                );
                $riderSmsSent = (int) ($riderNotice['push_sent'] ?? 0) > 0 || (bool) ($riderNotice['sms_sent'] ?? false);
                DB::table('orders')->where('id', $id)
                    ->update(['rider_sms_sent' => (bool) $riderSmsSent]);
            } catch (\Exception $e) {
                $riderSmsSent = false;
                DB::table('orders')->where('id', $id)->update(['rider_sms_sent' => false]);
            }
        }

        $smsNote = $rider->phone === null
            ? ' Warning: This rider has no phone number on record.'
            : ($riderSmsSent === false ? ' Warning: SMS to rider was not delivered. The message may have been flagged or the number is unreachable.' : ' SMS sent to rider.');

        try {
            $pushOrder = DB::table('orders')->where('id', $id)->first();
            if ($pushOrder) {
                app(MobileNotificationService::class)->notifyOrderCustomer(
                    $pushOrder,
                    'Order Out for Delivery',
                    "Order #{$id} is now out for delivery.",
                    ['event' => 'order_status']
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Seller rider assignment push failed: ' . $e->getMessage());
        }

        return back()->with('msg', "Rider assigned. Order is now Out for Delivery.{$smsNote}");
    }
}
