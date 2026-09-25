<?php

namespace App\Services;

use App\Helpers\CakeshopHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OrderRequestService
{
    public const ACTIVE_STATUSES = ['pending', 'alternative_offered', 'schedule_suggested', 'needs_more_details'];

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

        return ['ok' => empty($errors), 'message' => implode(' ', $errors), 'quantity' => $qty, 'time' => $time];
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
            DB::table('order_requests')->insert([
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
            ]);
        });

        $this->notifySeller($id);

        return [
            'ok' => true,
            'id' => $id,
            'is_rush' => $rush['is_rush'],
            'message' => $rush['is_rush']
                ? 'Rush request sent. The seller will confirm if they can prepare it in time.'
                : 'Request sent. The seller will review your preferred schedule.',
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

            $title = $request->is_rush ? 'Rush order request received' : 'Order request received';
            $when = trim(($request->preferred_date ?? '') . ' ' . ($request->preferred_time ?? ''));
            $message = ($request->product_name ?: 'Cake request') . ' requested for ' . $when . '.';

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