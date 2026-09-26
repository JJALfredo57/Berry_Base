<?php

namespace App\Services;

use App\Helpers\CakeshopHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderRequestService
{
    public const ACTIVE_STATUSES = ['pending', 'accepted', 'alternative_offered', 'schedule_suggested', 'needs_more_details'];

    public function rushSnapshot(?string $shopId, ?string $date, ?string $time, string $type = 'ready_made'): array
    {
        $prep = app(PreparationWindowService::class);
        $settings = $prep->settings($shopId);
        $prepDays = $type === 'custom'
            ? (int) $settings->custom_cake_prep_days
            : (int) $settings->ready_made_prep_days;

        $noticeMinutes = null;
        $isRush = false;
        $preferredAt = null;

        try {
            if ($date) {
                $preferredAt = Carbon::parse($date . ' ' . (substr((string) $time, 0, 5) ?: '00:00'), config('app.timezone'));
                $noticeMinutes = (int) round(now(config('app.timezone'))->diffInMinutes($preferredAt, false));
                $minimumAt = now(config('app.timezone'))->addDays(max(0, $prepDays));
                $isRush = $preferredAt->lt($minimumAt);
            }
        } catch (\Throwable $e) {
            $preferredAt = null;
        }

        return [
            'is_rush' => $isRush,
            'rush_reason' => $isRush ? 'prep_window_short' : null,
            'seller_prep_days_at_request' => max(0, $prepDays),
            'requested_notice_minutes' => $noticeMinutes,
            'preferred_datetime' => $preferredAt?->toDateTimeString(),
        ];
    }


    public function ensureCustomerToken(object|string $request): ?string
    {
        if (!Schema::hasTable('order_requests') || !Schema::hasColumn('order_requests', 'customer_token')) return null;
        $row = is_object($request) ? $request : DB::table('order_requests')->where('id', $request)->first();
        if (!$row) return null;
        if (!empty($row->customer_token)) return (string) $row->customer_token;

        do {
            $token = Str::random(48);
        } while (DB::table('order_requests')->where('customer_token', $token)->exists());

        DB::table('order_requests')->where('id', $row->id)->update([
            'customer_token' => $token,
            'updated_at' => now(),
        ]);
        return $token;
    }

    public function offerUrl(object $row): string
    {
        $token = $this->ensureCustomerToken($row);
        if (!empty($row->user_id)) {
            return route('customer.order_requests.show', $row->id, false);
        }
        return route('order_requests.show_token', ['id' => $row->id, 'token' => $token], false);
    }

    public function findForCustomer(string $id, ?string $token = null, ?string $userId = null): ?object
    {
        if (!Schema::hasTable('order_requests')) return null;
        $query = DB::table('order_requests as r')
            ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
            ->leftJoin('shops as s', 's.id', '=', 'r.shop_id')
            ->leftJoin('products as ap', 'ap.id', '=', 'r.alternative_product_id')
            ->where('r.id', $id)
            ->select('r.*', 'p.name as product_name', 'p.image_path', 'p.price as product_price', 'p.flavor', 'p.classification', 's.shop_name', 's.shop_slug', 'ap.name as alternative_product_name');
        if ($userId) $query->where('r.user_id', $userId);
        else $query->where('r.customer_token', $token);
        return $query->first();
    }

    public function finalOfferPrice(object $row): float
    {
        $price = (float) ($row->accepted_price ?? 0);
        if ($price <= 0) $price = (float) ($row->product_price ?? 0);
        return max(0, round($price, 2));
    }

    public function prepareCheckoutFromOffer(Request $request, object $row, bool $customer): array
    {
        if (!in_array(($row->status ?? ''), ['accepted', 'customer_accepted'], true)) {
            return ['ok' => false, 'message' => 'This offer is not ready for checkout.'];
        }
        if (!empty($row->converted_order_id)) {
            return ['ok' => false, 'message' => 'This request was already converted to an order.'];
        }
        if (!empty($row->expires_at) && Carbon::parse($row->expires_at, config('app.timezone'))->lte(now(config('app.timezone')))) {
            DB::table('order_requests')->where('id', $row->id)->update(['status' => 'expired', 'updated_at' => now()]);
            return ['ok' => false, 'message' => 'This seller offer already expired. Please request again or message the seller.'];
        }
        $price = $this->finalOfferPrice($row);
        if ($price <= 0) return ['ok' => false, 'message' => 'The seller must set a valid final price before checkout.'];

        $acceptedDate = $row->accepted_date ?: $row->preferred_date;
        $acceptedTime = substr((string) ($row->accepted_time ?: $row->preferred_time), 0, 5);
        $noteParts = [];
        if (!empty($row->customer_note)) $noteParts[] = (string) $row->customer_note;
        if (!empty($row->seller_response)) $noteParts[] = 'Seller note: ' . $row->seller_response;
        if (!empty($row->is_rush)) $noteParts[] = 'Rush request accepted by seller.';

        $checkout = [
            'product_id' => $row->alternative_product_id ?: $row->product_id,
            'quantity' => max(1, (int) $row->quantity),
            'custom_note' => implode(' | ', $noteParts),
            'selected_size' => trim((string) ($row->selected_size ?? '')),
            'order_request_id' => $row->id,
            'request_offer_checkout' => true,
            'accepted_unit_price' => $price,
            'accepted_total_price' => $price * max(1, (int) $row->quantity),
            'schedule_date' => $acceptedDate,
            'schedule_time' => $acceptedTime,
            'is_rush' => (bool) $row->is_rush,
            'rush_reason' => $row->rush_reason,
            'seller_prep_days_at_request' => $row->seller_prep_days_at_request,
            'requested_notice_minutes' => $row->requested_notice_minutes,
        ];

        if (($row->status ?? '') === 'accepted') {
            DB::table('order_requests')->where('id', $row->id)->where('status', 'accepted')->update([
                'status' => 'customer_accepted',
                'customer_decision_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $checkout['request_offer_status'] = 'customer_accepted';

        $request->session()->put($customer ? 'checkout' : 'guest_checkout', $checkout);
        return ['ok' => true, 'checkout' => $checkout];
    }

    public function customerDecline(object $row, ?string $note = null): array
    {
        if (!in_array(($row->status ?? ''), ['accepted', 'customer_accepted'], true)) return ['ok' => false, 'message' => 'This offer can no longer be declined.'];
        DB::table('order_requests')->where('id', $row->id)->whereIn('status', ['accepted', 'customer_accepted'])->update([
            'status' => 'customer_declined',
            'customer_decision_note' => trim((string) $note) ?: null,
            'customer_decision_at' => now(),
            'updated_at' => now(),
        ]);
        $this->notifySellerDecision((string) $row->id, 'declined');
        return ['ok' => true, 'message' => 'Offer declined.'];
    }

    public function notifySellerDecision(string $requestId, string $decision, ?string $orderId = null): void
    {
        try {
            $row = DB::table('order_requests as r')
                ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
                ->leftJoin('shops as s', 's.id', '=', 'r.shop_id')
                ->leftJoin('users as u', 'u.id', '=', 's.seller_id')
                ->where('r.id', $requestId)
                ->select('r.*', 'p.name as product_name', 's.seller_id', 'u.phone as seller_phone')
                ->first();
            if (!$row || !$row->seller_id) return;
            $accepted = $decision === 'accepted';
            $title = $accepted ? 'Customer accepted your offer' : 'Customer declined your offer';
            if ($accepted && !empty($row->is_rush)) $title = '[Rush] ' . $title;
            $message = ($row->product_name ?: 'Cake request') . ($accepted ? ' is moving to checkout.' : ' was declined by the customer.');
            if ($orderId) $message .= ' Order #' . $orderId . '.';
            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'receiver_role' => 'seller',
                    'receiver_user_id' => $row->seller_id,
                    'title' => $title,
                    'message' => $message,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            }
            app(MobileNotificationService::class)->notifyUser('seller', (string) $row->seller_id, $row->seller_phone ?? null, $title, $message, ['event' => 'order_request_decision', 'order_request_id' => $requestId, 'order_id' => $orderId], null, route('seller.order_requests', [], false));
        } catch (\Throwable $e) {
            Log::warning('Order request seller decision notification failed: ' . $e->getMessage());
        }
    }

    public function markConverted(string $requestId, string $orderId): void
    {
        if (!Schema::hasTable('order_requests')) return;
        DB::table('order_requests')->where('id', $requestId)->whereIn('status', ['customer_accepted', 'accepted'])->update([
            'status' => 'converted',
            'converted_order_id' => $orderId,
            'converted_at' => now(),
            'updated_at' => now(),
        ]);
        $this->notifySellerDecision($requestId, 'accepted', $orderId);
    }

    public function validateRequestPayload(array $data, ?object $product = null): array
    {
        $errors = [];
        $name = trim((string) ($data['guest_name'] ?? ''));
        $phone = trim((string) ($data['guest_phone'] ?? ''));
        $date = trim((string) ($data['preferred_date'] ?? ''));
        $time = substr(trim((string) ($data['preferred_time'] ?? '')), 0, 5);
        $qty = max(1, min(99, (int) ($data['quantity'] ?? 1)));

        if (($data['user_id'] ?? null) === null && $name === '') $errors[] = 'Please enter your full name.';
        if (($data['user_id'] ?? null) === null && $phone === '') $errors[] = 'Please enter your phone number.';
        if ($phone !== '' && !preg_match('/^(?:\+?63|0)?9\d{9}$/', preg_replace('/\s+/', '', $phone))) {
            $errors[] = 'Please enter a valid Philippine mobile number.';
        }
        if ($qty < 1) $errors[] = 'Please enter a valid quantity.';
        if ($date === '') $errors[] = 'Please choose your preferred date.';
        if ($time === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) $errors[] = 'Please choose a valid preferred time.';

        try {
            if ($date !== '') {
                $selectedDate = Carbon::parse($date, config('app.timezone'))->startOfDay();
                if ($selectedDate->lt(now(config('app.timezone'))->startOfDay())) {
                    $errors[] = 'Preferred date cannot be in the past.';
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Please choose a valid preferred date.';
        }

        if ($product && (int) ($product->is_available ?? 1) !== 1) {
            $errors[] = 'This product is currently unavailable.';
        }

        $selectedSize = trim((string) ($data['selected_size'] ?? ''));
        if ($selectedSize !== '' && $product && Schema::hasTable('product_sizes')) {
            $sizeQuery = DB::table('product_sizes')
                ->where('product_id', $product->id)
                ->whereRaw('LOWER(label) = ?', [strtolower($selectedSize)]);
            if (Schema::hasColumn('product_sizes', 'archived_at')) {
                $sizeQuery->whereNull('archived_at');
            }
            if (!$sizeQuery->exists()) {
                $errors[] = 'Selected size is no longer available for this cake.';
            }
        }
        if (($data['request_reason'] ?? '') === 'size_out_of_stock' && $selectedSize === '') {
            $errors[] = 'Please choose the out-of-stock size you want to request.';
        }

        return ['ok' => empty($errors), 'message' => implode(' ', $errors), 'quantity' => $qty, 'time' => $time, 'selected_size' => $selectedSize];
    }

    public function createReadyMade(array $data): array
    {
        if (!Schema::hasTable('order_requests')) {
            return ['ok' => false, 'message' => 'Order requests are not ready yet. Please try again later.'];
        }

        $product = DB::table('products')->where('id', $data['product_id'] ?? '')->whereNull('archived_at')->first();
        if (!$product) return ['ok' => false, 'message' => 'Product not found.'];

        $valid = $this->validateRequestPayload($data, $product);
        if (!$valid['ok']) return $valid;

        $schedule = app(FulfillmentScheduleService::class)->validate(
            $product->shop_id ?? null,
            $data['preferred_date'] ?? null,
            $valid['time'],
            'regular',
            'Pickup',
            null,
            null,
            false
        );
        if (!$schedule['ok']) return ['ok' => false, 'message' => $schedule['message']];

        $rush = $this->rushSnapshot($product->shop_id ?? null, $data['preferred_date'], $valid['time'], 'ready_made');
        $id = CakeshopHelper::generateId('order_requests');
        $note = trim((string) ($data['customer_note'] ?? ''));
        $expiresAt = $rush['preferred_datetime']
            ? Carbon::parse($rush['preferred_datetime'], config('app.timezone'))->subHour()
            : now(config('app.timezone'))->addDay();
        if ($expiresAt->lt(now(config('app.timezone'))->addMinutes(15))) {
            $expiresAt = now(config('app.timezone'))->addMinutes(15);
        }
        $expiresAt = $expiresAt->toDateTimeString();

        DB::transaction(function () use ($id, $data, $product, $valid, $rush, $note, $expiresAt) {
            $insert = [
                'id' => $id,
                'shop_id' => $product->shop_id,
                'user_id' => $data['user_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'product_id' => $product->id,
                'type' => $data['type'] ?? 'ready_made',
                'source' => $data['source'] ?? 'shop',
                'quantity' => $valid['quantity'],
                'preferred_date' => $data['preferred_date'],
                'preferred_time' => $valid['time'],
                'preferred_datetime' => $rush['preferred_datetime'],
                'is_rush' => $rush['is_rush'],
                'rush_reason' => $rush['rush_reason'],
                'seller_prep_days_at_request' => $rush['seller_prep_days_at_request'],
                'requested_notice_minutes' => $rush['requested_notice_minutes'],
                'allow_similar_cake' => !empty($data['allow_similar_cake']),
                'customer_note' => $note ?: null,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('order_requests', 'selected_size')) {
                $insert['selected_size'] = $valid['selected_size'] ?: null;
            }
            if (Schema::hasColumn('order_requests', 'request_reason')) {
                $insert['request_reason'] = $data['request_reason'] ?? 'out_of_stock';
            }
            DB::table('order_requests')->insert($insert);
        });

        $this->ensureCustomerToken($id);
        $this->notifySeller($id);

        return [
            'ok' => true,
            'id' => $id,
            'is_rush' => $rush['is_rush'],
            'message' => $rush['is_rush']
                ? 'Rush request to bake sent. The seller will confirm if the kitchen can prepare it in time.'
                : 'Request to bake sent. The seller will review it before it becomes an order.',
        ];
    }

    public function notifySeller(string $requestId): void
    {
        try {
            $request = DB::table('order_requests as r')
                ->leftJoin('products as p', 'p.id', '=', 'r.product_id')
                ->leftJoin('shops as s', 's.id', '=', 'r.shop_id')
                ->leftJoin('users as u', 'u.id', '=', 's.seller_id')
                ->where('r.id', $requestId)
                ->select('r.*', 'p.name as product_name', 's.seller_id', 'u.phone as seller_phone')
                ->first();
            if (!$request || !$request->seller_id) return;

            $title = $request->is_rush ? 'Rush kitchen request received' : 'Kitchen request received';
            $when = trim(($request->preferred_date ?? '') . ' ' . ($request->preferred_time ?? ''));
            $sizeText = !empty($request->selected_size) ? ' (' . $request->selected_size . ')' : '';
            $message = ($request->product_name ?: 'Cake request') . $sizeText . ' requested to bake for ' . $when . '.';

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                'receiver_role' => 'seller',
                'receiver_user_id' => $request->seller_id,
                'title' => $request->is_rush ? '[Rush] ' . $title : $title,
                'message' => $message,
                'is_read' => false,
                'created_at' => now(),
                ]);
            }

            app(MobileNotificationService::class)->notifyUser(
                'seller',
                (string) $request->seller_id,
                $request->seller_phone ?? null,
                $title,
                $message,
                ['event' => 'order_request', 'order_request_id' => $requestId],
                null,
                route('seller.order_requests', [], false)
            );
        } catch (\Throwable $e) {
            Log::warning('Order request seller notification failed: ' . $e->getMessage());
        }
    }

    public function markExpired(): int
    {
        if (!Schema::hasTable('order_requests')) return 0;
        return DB::table('order_requests')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }
}