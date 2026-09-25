<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\MobileNotificationService;
use App\Services\OrderRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderRequestController extends Controller
{
    private function getShop(): object
    {
        $uid = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403);
        return $shop;
    }

    public function index(Request $request, OrderRequestService $service)
    {
        $shop = $this->getShop();
        $service->markExpired();
        $tab = $request->input('tab', 'pending');
        if (!in_array($tab, ['pending', 'rush', 'accepted', 'suggested', 'declined', 'all'], true)) $tab = 'pending';
        $search = trim((string) $request->input('search', ''));

        $query = DB::table('order_requests as r')
            ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.shop_id', $shop->id)
            ->select('r.*', 'p.name as product_name', 'p.image_path', 'p.flavor', 'p.classification', 'u.fullname as user_fullname', 'u.phone as user_phone');

        if ($tab === 'rush') {
            $query->where('r.is_rush', true)->where('r.status', 'pending');
        } elseif ($tab === 'pending') {
            $query->where('r.status', 'pending');
        } elseif ($tab === 'accepted') {
            $query->where('r.status', 'accepted');
        } elseif ($tab === 'suggested') {
            $query->whereIn('r.status', ['alternative_offered', 'schedule_suggested', 'needs_more_details']);
        } elseif ($tab === 'declined') {
            $query->whereIn('r.status', ['declined', 'expired']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                    ->orWhere('r.id', 'like', "%{$search}%")
                    ->orWhere('r.guest_name', 'like', "%{$search}%")
                    ->orWhere('r.guest_phone', 'like', "%{$search}%")
                    ->orWhere('u.fullname', 'like', "%{$search}%");
            });
        }

        $requests = $query
            ->orderByRaw("CASE WHEN r.status = 'pending' AND r.is_rush = 1 THEN 0 WHEN r.status = 'pending' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN r.preferred_datetime IS NULL THEN 1 ELSE 0 END')
            ->orderBy('r.preferred_datetime')
            ->orderByDesc('r.created_at')
            ->paginate(12)
            ->withQueryString();

        $counts = ['pending' => 0, 'rush' => 0, 'accepted' => 0, 'suggested' => 0, 'declined' => 0, 'all' => 0];
        if (Schema::hasTable('order_requests')) {
            $base = DB::table('order_requests')->where('shop_id', $shop->id);
            $counts['pending'] = (clone $base)->where('status', 'pending')->count();
            $counts['rush'] = (clone $base)->where('status', 'pending')->where('is_rush', true)->count();
            $counts['accepted'] = (clone $base)->where('status', 'accepted')->count();
            $counts['suggested'] = (clone $base)->whereIn('status', ['alternative_offered', 'schedule_suggested', 'needs_more_details'])->count();
            $counts['declined'] = (clone $base)->whereIn('status', ['declined', 'expired'])->count();
            $counts['all'] = (clone $base)->count();
        }

        $alternativeProducts = DB::table('products')
            ->where('shop_id', $shop->id)
            ->whereNull('archived_at')
            ->where('is_available', true)
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        return view('seller.order_requests', compact('shop', 'requests', 'counts', 'tab', 'search', 'alternativeProducts'));
    }

    public function update(Request $request, string $id)
    {
        $shop = $this->getShop();
        $orderRequest = DB::table('order_requests')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$orderRequest) return back()->with('err', 'Request not found.');
        if (!in_array($orderRequest->status, ['pending', 'alternative_offered', 'schedule_suggested', 'needs_more_details'], true)) {
            return back()->with('err', 'This request has already been reviewed.');
        }

        $action = $request->input('action');
        $response = trim((string) $request->input('seller_response', ''));
        $data = ['seller_response' => $response ?: null, 'responded_at' => now(), 'updated_at' => now()];
        $message = 'Request updated.';

        if ($action === 'accept') {
            $price = $request->input('accepted_price');
            $data['status'] = 'accepted';
            $data['accepted_price'] = $price !== null && $price !== '' ? max(0, (float) $price) : null;
            $message = 'Request accepted. Customer can now be guided to checkout/payment manually.';
        } elseif ($action === 'decline') {
            if ($response === '') return back()->with('err', 'Please provide a reason for declining.')->withInput();
            $data['status'] = 'declined';
            $message = 'Request declined.';
        } elseif ($action === 'suggest_schedule') {
            if (!$request->input('suggested_date') || !$request->input('suggested_time')) {
                return back()->with('err', 'Please provide suggested date and time.')->withInput();
            }
            $data['status'] = 'schedule_suggested';
            $data['suggested_date'] = $request->input('suggested_date');
            $data['suggested_time'] = substr((string) $request->input('suggested_time'), 0, 5);
            $message = 'Suggested schedule sent.';
        } elseif ($action === 'offer_alternative') {
            if (!$request->input('alternative_product_id') && $response === '') {
                return back()->with('err', 'Please choose an alternative cake or write your offer.')->withInput();
            }
            $data['status'] = 'alternative_offered';
            $data['alternative_product_id'] = $request->input('alternative_product_id') ?: null;
            $message = 'Alternative offer sent.';
        } elseif ($action === 'needs_more_details') {
            if ($response === '') return back()->with('err', 'Please ask what details you need.')->withInput();
            $data['status'] = 'needs_more_details';
            $message = 'Details request sent.';
        } else {
            return back()->with('err', 'Unknown action.');
        }

        DB::table('order_requests')->where('id', $id)->update($data);
        $this->notifyCustomer($id, $data['status']);

        return back()->with('msg', $message);
    }

    private function notifyCustomer(string $id, string $status): void
    {
        try {
            $row = DB::table('order_requests as r')
                ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
                ->where('r.id', $id)
                ->select('r.*', 'p.name as product_name')
                ->first();
            if (!$row) return;

            $title = match ($status) {
                'accepted' => 'Order request accepted',
                'declined' => 'Order request declined',
                'schedule_suggested' => 'Seller suggested a schedule',
                'alternative_offered' => 'Seller offered an alternative',
                'needs_more_details' => 'Seller needs more details',
                default => 'Order request updated',
            };
            $message = ($row->product_name ?: 'Your cake request') . ' was updated by the seller.';
            app(MobileNotificationService::class)->notifyUser(
                !empty($row->user_id) ? 'customer' : 'guest_customer',
                !empty($row->user_id) ? (string) $row->user_id : null,
                $row->guest_phone ?? null,
                $title,
                $message,
                ['event' => 'order_request', 'order_request_id' => $id],
                null,
                null
            );
        } catch (\Throwable $e) {
            // Never break seller action because notification failed.
        }
    }
}