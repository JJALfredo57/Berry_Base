<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Services\CustomerVerificationService;
use App\Services\LoyaltyService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    private const CUSTOMER_HIDDEN_TRACKING_STATUSES = [
        'GCash QR Remittance Created',
        'GCash Remittance Verified',
        'Cash Handover Submitted',
        'Cash Remittance Confirmed',
        'Cash Remittance Rejected',
        'Rider Assignment Pending',
        'Rider Accepted Delivery',
        'Rider Declined Delivery',
        'Rider No Response',
    ];

    public function index(Request $request)
    {
        $uid    = session('user')['id'];
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'All');

        $orders = DB::table('orders as o')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('custom_orders as co_name', 'co_name.order_id', '=', 'o.id')
            ->where('o.user_id', $uid)
            ->select('o.*', DB::raw("COALESCE(p.name, co_name.cake_name, 'Custom Cake') as product_name"), 'p.image_path')
            ->when($search, fn($q) => $q->where(fn($sq) => $sq
                ->where('o.id', 'like', "%$search%")
                ->orWhere('p.name', 'like', "%$search%")
                ->orWhere('co_name.cake_name', 'like', "%$search%")
            ))
            ->when($status && $status !== 'All', fn($q) => $q->where('o.status', $status))
            ->orderByDesc('o.id')
            ->paginate(10)
            ->withQueryString();

        $orderIds = collect($orders->items())->pluck('id')->toArray();
        $tracking = [];
        $orderAddons = [];
        $orderItems = [];
        $orderReviews = [];
        $customOrderData = [];
        $customOrderVouchers = [];
        $customOrderLoyaltyQuotes = [];
        $checkoutGroupOrders = [];
        $loyaltySettings = app(LoyaltyService::class)->settings();
        $customOrderVerified = app(CustomerVerificationService::class)->isVerified($uid);

        if ($orderIds) {
            $rows = DB::table('order_tracking')
                ->whereIn('order_id', $orderIds)
                ->whereNotIn('status', self::CUSTOMER_HIDDEN_TRACKING_STATUSES)
                ->orderBy('created_at')
                ->get();
            foreach ($rows as $t) $tracking[$t->order_id][] = $t;
            try {
                $addonRows = DB::table('order_addons')->whereIn('order_id', $orderIds)->orderBy('id')->get();
                foreach ($addonRows as $a) $orderAddons[$a->order_id][] = $a;
            } catch (\Exception $e) {}
            try {
                if (Schema::hasTable('order_items')) {
                    $itemRows = DB::table('order_items')->whereIn('order_id', $orderIds)->orderBy('id')->get();
                    foreach ($itemRows as $item) $orderItems[$item->order_id][] = $item;
                }
            } catch (\Exception $e) {}
            try {
                $reviewRows = DB::table('order_reviews')->whereIn('order_id', $orderIds)->get();
                foreach ($reviewRows as $r) $orderReviews[$r->order_id] = $r;
            } catch (\Exception $e) {}
            try {
                $coRows = DB::table('custom_orders')->whereIn('order_id', $orderIds)->get();
                foreach ($coRows as $co) {
                    foreach ([
                        'review_status' => 'pending',
                        'cake_name' => 'Custom Cake',
                        'flavor' => null,
                        'size' => null,
                        'size_label' => null,
                        'layers' => null,
                        'design_complexity' => null,
                        'admin_price' => null,
                        'price_confirmed' => null,
                        'admin_comment' => null,
                        'progress_image' => null,
                        'progress_message' => null,
                        'time_slot' => null,
                        'dedication' => null,
                        'custom_note' => null,
                        'reference_images' => null,
                    ] as $key => $value) {
                        if (!property_exists($co, $key)) {
                            $co->{$key} = $value;
                        }
                    }
                    $co->size_label = $co->size_label ?? $co->size ?? null;
                    $customOrderData[$co->order_id] = $co;
                }
            } catch (\Exception $e) {}
        }
        if ($orderIds && Schema::hasColumn('orders', 'checkout_group_id')) {
            try {
                $groupIds = collect($orders->items())->pluck('checkout_group_id')->filter()->unique()->values();
                if ($groupIds->isNotEmpty()) {
                    $siblings = DB::table('orders as o')
                        ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
                        ->leftJoin('custom_orders as co', 'co.order_id', '=', 'o.id')
                        ->where('o.user_id', $uid)
                        ->whereIn('o.checkout_group_id', $groupIds)
                        ->select('o.id','o.checkout_group_id','o.status','o.track_code','o.order_type','o.total_price', DB::raw("COALESCE(p.name, co.cake_name, 'Custom Cake') as product_name"))
                        ->orderBy('o.id')
                        ->get();
                    foreach ($siblings as $sibling) $checkoutGroupOrders[$sibling->checkout_group_id][] = $sibling;
                }
            } catch (\Throwable $e) {}
        }
        foreach ($orders->items() as $orderForDiscounts) {
            $coForDiscounts = $customOrderData[$orderForDiscounts->id] ?? null;
            if (!$coForDiscounts || ($coForDiscounts->review_status ?? '') !== 'approved' || (float) ($coForDiscounts->admin_price ?? 0) <= 0) continue;
            $finalPrice = (float) $coForDiscounts->admin_price;
            $customOrderVouchers[$coForDiscounts->id] = app(VoucherService::class)->availableForCustomer($uid, $orderForDiscounts->shop_id ?? null, $finalPrice);
            $customOrderLoyaltyQuotes[$coForDiscounts->id] = app(LoyaltyService::class)->redemptionQuote($uid, $finalPrice, 0, $customOrderVerified);
        }

        return view('customer.orders', compact('orders','tracking','orderAddons','orderItems','orderReviews','checkoutGroupOrders','customOrderData','customOrderVouchers','customOrderLoyaltyQuotes','loyaltySettings','customOrderVerified','search','status'));
    }

    private function applyCustomFinalDiscounts(Request $request, object $order, object $co, float $finalPrice, string $uid): array
    {
        $voucherCode = strtoupper(trim((string) $request->input('voucher_code', '')));
        $voucherResult = app(VoucherService::class)->validate($voucherCode, $finalPrice, $order->shop_id ?? null, $uid);
        if (!$voucherResult['ok']) {
            return ['ok' => false, 'message' => $voucherResult['message'] ?? 'Voucher could not be applied.'];
        }

        $voucherDiscount = (float) ($voucherResult['discount'] ?? 0);
        $loyaltyQuote = app(LoyaltyService::class)->redemptionQuote(
            $uid,
            max(0, $finalPrice - $voucherDiscount),
            max(0, (int) $request->input('points_to_redeem', 0)),
            app(CustomerVerificationService::class)->isVerified($uid)
        );
        if (!$loyaltyQuote['ok']) {
            return ['ok' => false, 'message' => $loyaltyQuote['message'] ?? 'Rewards points could not be applied.'];
        }

        $pointsRedeemed = (int) ($loyaltyQuote['points'] ?? 0);
        $loyaltyDiscount = (float) ($loyaltyQuote['discount'] ?? 0);
        $payableTotal = round(max(0, $finalPrice - $voucherDiscount - $loyaltyDiscount), 2);
        if ($payableTotal < 100) {
            return ['ok' => false, 'message' => 'Custom order payable total must be at least PHP 100.00 after discounts.'];
        }

        return [
            'ok' => true,
            'voucher' => $voucherResult['voucher'] ?? null,
            'voucher_discount' => $voucherDiscount,
            'points_redeemed' => $pointsRedeemed,
            'loyalty_discount' => $loyaltyDiscount,
            'payable_total' => $payableTotal,
        ];
    }

    private function recordCustomFinalDiscounts(string $orderId, object $order, array $discounts, string $uid): void
    {
        if (!empty($discounts['voucher']) && (float) $discounts['voucher_discount'] > 0) {
            app(VoucherService::class)->recordRedemption($discounts['voucher'], $order, (float) $discounts['voucher_discount']);
            DB::table('order_discounts')->insert([
                'order_id' => $orderId,
                'source_type' => 'voucher',
                'source_id' => $discounts['voucher']->id,
                'label' => $discounts['voucher']->name,
                'code' => $discounts['voucher']->code,
                'amount' => (float) $discounts['voucher_discount'],
                'meta' => json_encode(['applied_to' => 'custom_final_price']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ((int) ($discounts['points_redeemed'] ?? 0) > 0 && (float) ($discounts['loyalty_discount'] ?? 0) > 0) {
            app(LoyaltyService::class)->redeemForOrder($uid, $orderId, (int) $discounts['points_redeemed'], (float) $discounts['loyalty_discount']);
            DB::table('order_discounts')->insert([
                'order_id' => $orderId,
                'source_type' => 'loyalty',
                'source_id' => null,
                'label' => 'Rewards Points',
                'code' => null,
                'amount' => (float) $discounts['loyalty_discount'],
                'meta' => json_encode([
                    'points' => (int) $discounts['points_redeemed'],
                    'point_value' => (float) (app(LoyaltyService::class)->settings()['point_value'] ?? 1),
                    'applied_to' => 'custom_final_price',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    public function status(string $id)
    {
        $uid = session('user')['id'];
        $order = DB::table('orders')
            ->where('id', $id)
            ->where('user_id', $uid)
            ->select('id', 'status', 'payment_status', 'deposit_status', 'total_price', 'deposit_amount', 'paid_at', 'deposit_paid_at', 'updated_at')
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'Order not found.'], 404);
        }

        $trackingQuery = DB::table('order_tracking')
            ->where('order_id', $id)
            ->whereNotIn('status', self::CUSTOMER_HIDDEN_TRACKING_STATUSES);

        $trackingCount = (clone $trackingQuery)->count();
        $latestTrackingAt = (clone $trackingQuery)->max('created_at');
        $final = in_array($order->status, ['Delivered', 'Picked Up', 'Cancelled'], true);
        $active = in_array($order->status, ['Preparing', 'Ready for Rider', 'Out for Delivery', 'Pickup'], true);

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
            'updated_at' => (string) ($order->updated_at ?? ''),
            'latest_tracking_at' => (string) ($latestTrackingAt ?? ''),
            'final' => $final,
            'interval_ms' => $final ? 0 : ($active ? 10000 : 25000),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function requestCancel(Request $request, string $id)
    {
        $uid    = session('user')['id'];
        $reason = trim($request->input('cancel_reason', ''));

        if (!$reason) return back()->with('error', 'Please provide a reason for cancellation.');

        $order = DB::table('orders')->where('id', $id)->where('user_id', $uid)->first();
        if (!$order) return back()->with('error', 'Order not found.');

        $hasPaidDeposit = ($order->deposit_status ?? null) === 'paid'
            || in_array(($order->payment_status ?? ''), ['Partial Payment', 'Paid'], true);
        if ($hasPaidDeposit) {
            return back()->with('error', 'Cannot cancel this order because your deposit has already been paid.');
        }

        $notAllowed = ['Preparing','Ready for Rider','Out for Delivery','Delivered','Cancelled'];
        if (in_array($order->status, $notAllowed)) {
            return back()->with('error', "Cannot cancel - status is already '{$order->status}'.");
        }

        if ($order->cancel_requested && $order->cancel_status === 'pending') {
            return back()->with('error', 'You already have a pending cancel request for this order.');
        }

        DB::table('orders')->where('id', $id)->update([
            'cancel_requested'    => 1,
            'cancel_reason'       => $reason,
            'cancel_status'       => 'pending',
            'cancel_admin_note'   => null,
            'cancel_requested_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $custName = session('user')['fullname'] ?? 'Customer';
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ Cancel Request ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Order #' . $id,
            'message'          => "{$custName} wants to cancel Order #{$id}. Reason: {$reason}",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        DB::table('messages')->insert([
                        'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ Cancel Request submitted.\n\nReason: {$reason}",
            'is_read' => false,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'Cancel Request', "Order #{$id} ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â {$reason}");
        return back()->with('msg', 'Cancel request submitted. Waiting for admin approval.');
    }

    /** Customer accepts the admin-set price -> apply final discounts, then prepare deposit */
    public function acceptPrice(string $coId)
    {
        $uid = session('user')['id'];
        $co  = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'pending') return back()->with('err', 'Price already responded to.');

        $order = DB::table('orders')->where('id', $co->order_id)->where('user_id', $uid)->first();
        if (!$order) return back()->with('err', 'Order not found.');
        if ($order->payment_status === 'Paid') return back()->with('err', 'This order is already fully paid.');

        $finalPrice = (float) $co->admin_price;
        $discounts = $this->applyCustomFinalDiscounts(request(), $order, $co, $finalPrice, (string) $uid);
        if (!$discounts['ok']) return back()->with('err', $discounts['message'])->withInput();

        $totalPrice = (float) $discounts['payable_total'];
        $minDeposit = round($totalPrice * 0.5, 2);
        $requested = (float) request()->input('deposit_amount', $minDeposit);
        if ($requested < $minDeposit) {
            return back()->with('err', 'Minimum deposit is 50%: PHP ' . number_format($minDeposit, 2) . '.')->withInput();
        }
        $depositAmount = round(min($requested, $totalPrice), 2);

        DB::table('custom_orders')->where('id', $coId)->update([
            'price_confirmed'       => 'accepted',
            'customer_confirmed_at' => now(),
        ]);

        DB::table('orders')->where('id', $co->order_id)->update([
            'deposit_required' => 1,
            'deposit_amount'   => $depositAmount,
            'deposit_status'   => 'pending',
            'deposit_paid_at'  => null,
            'payment_status'   => 'Unpaid',
            'paid_at'          => null,
            'status'           => $order->status,
            'total_price'      => $totalPrice,
            'voucher_code'      => $discounts['voucher']->code ?? null,
            'voucher_discount_amount' => (float) $discounts['voucher_discount'],
            'loyalty_discount_amount' => (float) $discounts['loyalty_discount'],
            'points_redeemed' => (int) $discounts['points_redeemed'],
        ]);

        $updatedOrder = DB::table('orders')->where('id', $co->order_id)->first();
        if ($updatedOrder) $this->recordCustomFinalDiscounts($co->order_id, $updatedOrder, $discounts, (string) $uid);

        $discountNote = '';
        if ((float) $discounts['voucher_discount'] > 0) $discountNote .= ' Voucher discount: PHP ' . number_format((float) $discounts['voucher_discount'], 2) . '.';
        if ((float) $discounts['loyalty_discount'] > 0) $discountNote .= ' Rewards discount: PHP ' . number_format((float) $discounts['loyalty_discount'], 2) . '.';

        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => $order->status,
            'notes'      => 'Customer accepted the final price of PHP ' . number_format($finalPrice, 2) . '. Payable after discounts: PHP ' . number_format($totalPrice, 2) . '.' . $discountNote,
            'created_at' => now(),
        ]);

        $custName = session('user')['fullname'] ?? 'Customer';
        DB::table('messages')->insert([
            'order_id'    => $co->order_id,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => 'I accept the final price of PHP ' . number_format($finalPrice, 2) . '. Payable after discounts: PHP ' . number_format($totalPrice, 2) . '. I will proceed with the deposit payment.',
            'is_read' => false,
            'created_at'  => now(),
        ]);
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Custom Order #' . $co->order_id . ' - Price Accepted',
            'message'          => $custName . ' accepted PHP ' . number_format($finalPrice, 2) . ' for Custom Order #' . $co->order_id . '. Payable after discounts: PHP ' . number_format($totalPrice, 2) . '. Waiting for deposit payment.',
            'is_read' => false,
            'created_at'       => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'Accept Custom Price', 'Custom Order #' . $coId);
        return redirect()->route('customer.custom_orders.pay_deposit', $coId);
    }
    /** Customer sets deposit amount for custom order (min 50%) */
    public function setCustomDeposit(Request $request, string $coId)
    {
        $uid = session('user')['id'];
        $co  = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'accepted') return back()->with('err', 'Please accept the price first.');

        $order = DB::table('orders')->where('id', $co->order_id)->first();
        if (!$order) return back()->with('err', 'Order not found.');
        if ($order->payment_status === 'Paid') return back()->with('err', 'This order is already fully paid.');

        $totalPrice    = (float) $order->total_price;
        $minDeposit    = round($totalPrice * 0.5, 2);
        $depositAmount = round((float) $request->input('deposit_amount', $minDeposit), 2);

        if ($depositAmount < $minDeposit)
            return back()->with('err', 'Minimum deposit is 50% of total (ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±' . number_format($minDeposit, 2) . ').');
        if ($depositAmount > $totalPrice)
            $depositAmount = $totalPrice;

        $isFullPayment = abs($depositAmount - $totalPrice) < 0.01;

        // Save deposit info on order
        DB::table('orders')->where('id', $co->order_id)->update([
            'deposit_required' => 1,
            'deposit_amount'   => $depositAmount,
            'deposit_status'   => 'pending',
            'total_price'      => $totalPrice,
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => $order->status,
            'notes'      => $isFullPayment
                ? "Customer chose to pay full amount PHP {$depositAmount}."
                : "Customer set deposit of PHP {$depositAmount} (min 50%).",
            'created_at' => now(),
        ]);

        // GCash ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â redirect to PayMongo
        if ($order->payment_method === 'GCash') {
            return redirect()->route('customer.custom_orders.pay_deposit', $coId);
        }

        // All custom deposit payments are collected through PayMongo before confirmation.
        return redirect()->route('customer.custom_orders.pay_deposit', $coId);
    }

    /** COD custom order ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â acknowledge deposit and auto-confirm */
    private function acknowledgeCustomCod(string $coId, object $co, object $order, float $depositAmount, bool $isFullPayment, int $uid)
    {
        DB::table('orders')->where('id', $co->order_id)->update([
            'deposit_status' => 'paid',
            'deposit_paid_at'=> now(),
            'payment_status' => $isFullPayment ? 'Paid' : 'Partial Payment',
            'status'         => 'Confirmed',
        ]);

        DB::table('order_tracking')->insert([
            'order_id'   => $co->order_id,
            'status'     => 'Confirmed',
            'notes'      => $isFullPayment
                ? "COD full payment ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±{$depositAmount} acknowledged. Order auto-confirmed."
                : "COD deposit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±{$depositAmount} acknowledged. Order auto-confirmed. Remaining: ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±" . ($order->total_price - $depositAmount),
            'created_at' => now(),
        ]);

        try {
            $this->sendCustomToKitchen($co, $order);
        } catch (\Exception $e) {
            DB::table('order_tracking')->insert([
                'order_id'   => $co->order_id,
                'status'     => 'Confirmed',
                'notes'      => 'Order confirmed, but kitchen ticket could not be generated automatically. Please notify the shop.',
                'created_at' => now(),
            ]);
        }

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Custom Order #' . $co->order_id . ' - COD Deposit Acknowledged',
            'message'          => "Customer acknowledged COD deposit of PHP {$depositAmount} for Custom Order #{$co->order_id}. Auto-confirmed.",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'COD Custom Deposit Acknowledged', "Custom Order #{$coId}");
        return back()->with('msg', 'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Order confirmed! Your custom cake is now being prepared. ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â°ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡');
    }

    /** Send custom order to kitchen (shared helper) */
    private function sendCustomToKitchen(object $co, object $order)
    {
        if ($order->kitchen_sent) return;
        $addons    = DB::table('order_addons')->where('order_id', $co->order_id)->get();
        $addonList = $addons->count() > 0
            ? "\nADD-ONS:\n" . $addons->map(fn($a) => "  ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ {$a->addon_name}" . ($a->addon_price > 0 ? " (+ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±{$a->addon_price})" : " (FREE)"))->implode("\n")
            : '';
        $productName = DB::table('products')->where('id', $order->product_id)->value('name') ?? 'Custom Cake';
        $fullname    = DB::table('users')->where('id', $order->user_id)->value('fullname') ?? 'Customer';
        $phone       = DB::table('users')->where('id', $order->user_id)->value('phone') ?? '';
        $sizeInfo    = $order->selected_size ? "\nSIZE: {$order->selected_size}" : '';
        $noteInfo    = $order->custom_note   ? "\nSPECIAL NOTE: {$order->custom_note}" : '';
        $schedInfo   = $order->schedule_date
            ? "\nSCHEDULE: " . date('M d, Y', strtotime($order->schedule_date)) : '';
        $payInfo     = $order->payment_method === 'COD'
            ? CakeshopHelper::shortPaymentCode($order->payment_method, $order->fulfillment_type ?? null) . " ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â Deposit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±{$order->deposit_amount} acknowledged"
            : "GCash Deposit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±{$order->deposit_amount} ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ Paid";

        DB::table('kitchen_tickets')->where('order_id', $co->order_id)->delete();
        DB::table('kitchen_tickets')->insert([
                        'shop_id'       => $order->shop_id ?? null,
            'order_id'     => $co->order_id,
            'product_name' => $productName . ' (Custom)',
            'quantity'     => $order->quantity ?? 1,
                        'instructions' => "=== KITCHEN ORDER TICKET ===\nOrder #: {$co->order_id}\nCustomer: {$fullname} ({$phone})\nProduct: {$productName} (Custom)\nQty: {$order->quantity}{$sizeInfo}{$noteInfo}{$addonList}{$schedInfo}\nFulfillment: {$order->fulfillment_type}\nPayment: {$payInfo}\n===========================",
            'status'       => 'pending',
            'sent_at'      => now()->format('Y-m-d H:i:s'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        DB::table('orders')->where('id', $co->order_id)->update(['kitchen_sent' => true]);
    }

    /** Customer cancels order after admin sets a different price */
    public function cancelAfterPrice(string $coId)
    {
        $uid = session('user')['id'];
        $co  = DB::table('custom_orders')->where('id', $coId)->where('user_id', $uid)->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->price_confirmed !== 'pending') return back()->with('err', 'Price already responded to.');

        DB::table('custom_orders')->where('id', $coId)->update([
            'price_confirmed'      => 'cancelled',
            'customer_confirmed_at'=> now(),
        ]);

        if ($co->order_id) {
            DB::table('orders')->where('id', $co->order_id)->update(['status' => 'Cancelled']);
            DB::table('order_tracking')->insert([
                'order_id'   => $co->order_id,
                'status'     => 'Cancelled',
                'notes'      => 'Customer declined the final price of ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±' . number_format($co->admin_price, 2),
                'created_at' => now(),
            ]);

            $custName = session('user')['fullname'] ?? 'Customer';
            DB::table('messages')->insert([
                'order_id'    => $co->order_id,
                'sender_role' => 'customer',
                'sender_id'   => $uid,
                'message'     => "I am cancelling my custom cake order. The final price of PHP " . number_format($co->admin_price, 2) . " does not work for me. Thank you.",
                'is_read' => false,
                'created_at'  => now(),
            ]);
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => 'Custom Order #' . $co->order_id . ' - Price Declined',
                'message'          => "{$custName} declined PHP " . number_format($co->admin_price, 2) . " and cancelled Custom Order #{$co->order_id}.",
                'is_read' => false,
                'created_at'       => now(),
            ]);
        }

        CakeshopHelper::logActivity($uid, 'customer', 'Decline Custom Price', "Custom Order #{$coId}");
        return back()->with('msg', 'Order cancelled. Feel free to place a new custom order anytime!');
    }

}
