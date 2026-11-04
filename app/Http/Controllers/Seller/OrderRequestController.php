<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\FulfillmentScheduleService;
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
        if (!in_array($tab, ['pending', 'rush', 'accepted', 'suggested', 'converted', 'declined', 'all'], true)) $tab = 'pending';
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
            $query->whereIn('r.status', ['accepted', 'customer_accepted']);
        } elseif ($tab === 'converted') {
            $query->where('r.status', 'converted');
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
            ->orderByRaw("CASE WHEN r.status = 'pending' AND r.is_rush = true THEN 0 WHEN r.status = 'pending' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN r.preferred_datetime IS NULL THEN 1 ELSE 0 END')
            ->orderBy('r.preferred_datetime')
            ->orderByDesc('r.created_at')
            ->paginate(12)
            ->withQueryString();

        $counts = ['pending' => 0, 'rush' => 0, 'accepted' => 0, 'suggested' => 0, 'converted' => 0, 'declined' => 0, 'all' => 0];
        if (Schema::hasTable('order_requests')) {
            $base = DB::table('order_requests')->where('shop_id', $shop->id);
            $counts['pending'] = (clone $base)->where('status', 'pending')->count();
            $counts['rush'] = (clone $base)->where('status', 'pending')->where('is_rush', true)->count();
            $counts['accepted'] = (clone $base)->whereIn('status', ['accepted', 'customer_accepted'])->count();
            $counts['converted'] = (clone $base)->where('status', 'converted')->count();
            $counts['suggested'] = (clone $base)->whereIn('status', ['alternative_offered', 'schedule_suggested', 'needs_more_details'])->count();
            $counts['declined'] = (clone $base)->whereIn('status', ['declined', 'customer_declined', 'expired'])->count();
            $counts['all'] = (clone $base)->count();
        }

        $alternativeProducts = DB::table('products')
            ->where('shop_id', $shop->id)
            ->whereNull('archived_at')
            ->where('is_available', true)
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        $scheduleSettings = app(FulfillmentScheduleService::class)->settings($shop->id);
        $prepSettings = app(\App\Services\PreparationWindowService::class)->settings($shop->id);
        $suggestionRules = [
            'earliest_date' => app(\App\Services\PreparationWindowService::class)->earliestDate($shop->id, 'custom')->toDateString(),
            'prep_days' => (int) $prepSettings->custom_cake_prep_days,
            'open_time' => substr((string) $scheduleSettings->shop_open_time, 0, 5),
            'close_time' => substr((string) $scheduleSettings->shop_close_time, 0, 5),
            'required_minutes' => app(FulfillmentScheduleService::class)->requiredLeadMinutes($shop->id, 'custom', 'Pickup'),
        ];

        return view('seller.order_requests', compact('shop', 'requests', 'counts', 'tab', 'search', 'alternativeProducts', 'suggestionRules'));
    }

    public function update(Request $request, string $id, OrderRequestService $service)
    {
        $shop = $this->getShop();
        $service->markExpired();
        $orderRequest = DB::table('order_requests')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$orderRequest) return back()->with('err', 'Request not found.');
        if ($orderRequest->status !== 'pending') {
            return back()->with('err', 'This request has already been reviewed.');
        }

        $action = $request->input('action');
        $response = trim((string) $request->input('seller_response', ''));
        $data = ['seller_response' => $response ?: null, 'responded_at' => now(), 'updated_at' => now()];
        $message = 'Request updated.';

        if ($action === 'accept') {
            $acceptedPrice = $service->fixedPriceForRequest($orderRequest);
            if ($acceptedPrice <= 0) return back()->with('err', 'This cake needs a valid published catalog price before it can be offered.')->withInput();
            $acceptedDate = $orderRequest->preferred_date;
            $acceptedTime = substr((string) $orderRequest->preferred_time, 0, 5);
            $scheduleCheck = app(FulfillmentScheduleService::class)->validate(
                $shop->id,
                $acceptedDate,
                $acceptedTime,
                ($orderRequest->type ?? '') === 'custom' ? 'custom' : 'regular',
                'Pickup'
            );
            if (!$scheduleCheck['ok']) {
                return back()->with('err', $scheduleCheck['message'])->withInput();
            }
            $data['status'] = 'accepted';
            $data['accepted_price'] = $acceptedPrice;
            $data['accepted_date'] = $acceptedDate;
            $data['accepted_time'] = $acceptedTime;
            $data['accepted_datetime'] = $acceptedDate ? \Carbon\Carbon::parse($acceptedDate . ' ' . ($acceptedTime ?: '00:00'), config('app.timezone'))->toDateTimeString() : null;
            $data['action_expires_at'] = $service->actionDeadline();
            $message = 'Offer sent. Waiting for the customer to accept before checkout/payment.';
        } elseif ($action === 'decline') {
            if ($response === '') return back()->with('err', 'Please provide a reason for declining.')->withInput();
            $data['status'] = 'declined';
            $message = 'Request declined.';
        } elseif ($action === 'suggest_schedule') {
            if (!$request->input('suggested_date') || !$request->input('suggested_time')) {
                return back()->with('err', 'Please provide suggested date and time.')->withInput();
            }
            if (mb_strlen($response) < 5) {
                return back()->with('err', 'Explain why this alternative schedule is needed.')->withInput();
            }
            $data['status'] = 'schedule_suggested';
            $data['suggested_date'] = $request->input('suggested_date');
            $data['suggested_time'] = substr((string) $request->input('suggested_time'), 0, 5);
            $scheduleCheck = $this->checkSuggestedSchedule($shop, $orderRequest, $data['suggested_date'], $data['suggested_time']);
            if (!$scheduleCheck['ok']) return back()->with('err', $scheduleCheck['message'])->withInput();
            $data['action_expires_at'] = $service->actionDeadline();
            $message = 'Suggested schedule sent.';
        } elseif ($action === 'offer_alternative') {
            if (!$orderRequest->allow_similar_cake) {
                return back()->with('err', 'The customer did not allow a similar cake for this request.')->withInput();
            }
            if (!$request->input('alternative_product_id')) {
                return back()->with('err', 'Please choose an available alternative cake from your catalog.')->withInput();
            }
            $data['status'] = 'alternative_offered';
            $data['alternative_product_id'] = $request->input('alternative_product_id') ?: null;
            $data['action_expires_at'] = $service->actionDeadline();
            $message = 'Alternative offer sent.';
        } elseif ($action === 'needs_more_details') {
            if ($response === '') return back()->with('err', 'Please ask what details you need.')->withInput();
            $data['status'] = 'needs_more_details';
            $data['action_expires_at'] = $service->actionDeadline();
            $message = 'Details request sent.';
        } else {
            return back()->with('err', 'Unknown action.');
        }

        if ($action === 'decline') $data['action_expires_at'] = null;

        DB::table('order_requests')->where('id', $id)->update($data);
        $service->ensureCustomerToken($id);
        $this->notifyCustomer($id, $data['status']);

        return back()->with('msg', $message);
    }

    public function validateSuggestedSchedule(Request $request, string $id, OrderRequestService $service)
    {
        $shop = $this->getShop();
        $service->markExpired();
        $orderRequest = DB::table('order_requests')->where('id', $id)->where('shop_id', $shop->id)->first();
        if (!$orderRequest || $orderRequest->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'This request is no longer available for a schedule suggestion.'], 422);
        }

        $result = $this->checkSuggestedSchedule(
            $shop,
            $orderRequest,
            (string) $request->query('suggested_date', ''),
            substr((string) $request->query('suggested_time', ''), 0, 5)
        );
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    private function checkSuggestedSchedule(object $shop, object $orderRequest, string $date, string $time): array
    {
        if ($date === '' || $time === '') return ['ok' => false, 'message' => 'Choose a suggested date and time.'];
        try {
            $customerSchedule = \Carbon\Carbon::parse($orderRequest->preferred_date . ' ' . substr((string) $orderRequest->preferred_time, 0, 5), config('app.timezone'));
            $suggestedSchedule = \Carbon\Carbon::parse($date . ' ' . $time, config('app.timezone'));
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Choose a valid suggested date and time.'];
        }
        if ($suggestedSchedule->lt($customerSchedule)) {
            return ['ok' => false, 'message' => 'Suggested schedule cannot be earlier than the customer’s requested date and time.'];
        }
        if ($suggestedSchedule->equalTo($customerSchedule)) {
            return ['ok' => false, 'message' => 'Choose a schedule different from the customer’s original request.'];
        }

        $preparation = app(\App\Services\PreparationWindowService::class)->validateDate($shop->id, $date, 'custom');
        if (!$preparation['ok']) return $preparation;

        $schedule = app(FulfillmentScheduleService::class)->validate($shop->id, $date, $time, 'custom', 'Pickup');
        if (!$schedule['ok']) return $schedule;

        $capacity = app(\App\Services\DailyCapacityService::class)->validate($shop->id, $date, max(1, (int) $orderRequest->quantity));
        if (!$capacity['allowed']) return ['ok' => false, 'message' => $capacity['message']];

        return ['ok' => true, 'message' => ($capacity['message'] ?? 'Schedule is available.') . ' This suggestion uses the current shop settings.'];
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
            $url = null;
            if ($status === 'accepted') {
                $url = app(OrderRequestService::class)->offerUrl($row);
                $message = ($row->product_name ?: 'Your cake request') . ' was accepted. Review the offer to continue checkout.';
            }
            app(MobileNotificationService::class)->notifyUser(
                !empty($row->user_id) ? 'customer' : 'guest_customer',
                !empty($row->user_id) ? (string) $row->user_id : null,
                $row->guest_phone ?? null,
                $title,
                $message,
                ['event' => 'order_request', 'order_request_id' => $id],
                null,
                $url
            );
        } catch (\Throwable $e) {
            // Never break seller action because notification failed.
        }
    }
}
