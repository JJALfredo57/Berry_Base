<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VoucherService
{
    public function availableForCustomer(?string $userId, ?string $shopId, float $subtotal): array
    {
        if (!Schema::hasTable('vouchers')) return [];

        $rows = DB::table('vouchers')
            ->where('is_active', true)
            ->where(function ($q) use ($shopId) {
                $q->whereNull('shop_id');
                if ($shopId) $q->orWhere('shop_id', $shopId);
            })
            ->orderByRaw('CASE WHEN shop_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('ends_at')
            ->orderBy('code')
            ->get();

        return $rows->filter(function ($voucher) use ($userId) {
            if (($voucher->audience ?? 'public') !== 'assigned') return true;
            if (!Schema::hasTable('voucher_assignments')) return true;
            if (!$userId) return false;
            return DB::table('voucher_assignments')
                ->where('voucher_id', $voucher->id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->exists();
        })->map(function ($voucher) use ($subtotal, $shopId, $userId) {
            $result = $this->validate($voucher->code, $subtotal, $shopId, $userId);
            $voucher->validation_ok = $result['ok'];
            $voucher->validation_message = $result['message'] ?? '';
            $voucher->computed_discount = (float) ($result['discount'] ?? 0);
            return $voucher;
        })->values()->all();
    }

    public function validate(?string $code, float $subtotal, ?string $shopId, ?string $userId = null, ?string $guestPhone = null): array
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') return ['ok' => true, 'voucher' => null, 'discount' => 0, 'message' => ''];
        if (!Schema::hasTable('vouchers')) return ['ok' => false, 'message' => 'Voucher system is not ready yet.'];

        $voucher = DB::table('vouchers')
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('is_active', true)
            ->where(function ($q) use ($shopId) {
                $q->whereNull('shop_id');
                if ($shopId) $q->orWhere('shop_id', $shopId);
            })
            ->first();

        if (!$voucher) return ['ok' => false, 'message' => 'Promo code was not found or is inactive.'];
        if (($voucher->audience ?? 'public') === 'assigned') {
            if (Schema::hasTable('voucher_assignments')) {
                if (!$userId) return ['ok' => false, 'message' => 'This promo is assigned to a specific customer account.'];
                $assigned = DB::table('voucher_assignments')
                    ->where('voucher_id', $voucher->id)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                    ->exists();
                if (!$assigned) return ['ok' => false, 'message' => 'This promo is not assigned to your account.'];
            } else {
                return ['ok' => false, 'message' => 'Customer-specific vouchers are not ready yet.'];
            }
        }
        if ($voucher->starts_at && now()->lt($voucher->starts_at)) return ['ok' => false, 'message' => 'This promo has not started yet.'];
        if ($voucher->ends_at && now()->gt($voucher->ends_at)) return ['ok' => false, 'message' => 'This promo has already expired.'];
        if ($subtotal < (float) $voucher->minimum_order_amount) {
            return ['ok' => false, 'message' => 'Minimum order amount is PHP ' . number_format((float) $voucher->minimum_order_amount, 2) . '.'];
        }

        if ((int) ($voucher->usage_limit ?? 0) > 0) {
            $used = DB::table('voucher_redemptions')->where('voucher_id', $voucher->id)->count();
            if ($used >= (int) $voucher->usage_limit) return ['ok' => false, 'message' => 'This promo code has reached its usage limit.'];
        }

        if ((int) ($voucher->per_customer_limit ?? 0) > 0 && ($userId || $guestPhone)) {
            $query = DB::table('voucher_redemptions')->where('voucher_id', $voucher->id);
            if ($userId) $query->where('user_id', $userId);
            else $query->where('guest_phone', $guestPhone);
            if ($query->count() >= (int) $voucher->per_customer_limit) {
                return ['ok' => false, 'message' => 'You already used this promo code.'];
            }
        }

        if ((bool) $voucher->requires_verified_customer) {
            if (!$userId || !app(CustomerVerificationService::class)->isVerified($userId)) {
                return ['ok' => false, 'message' => 'This promo is for verified customers only.'];
            }
        }

        if ((bool) $voucher->first_order_only && $userId) {
            $hasOrder = DB::table('orders')->where('user_id', $userId)->exists();
            if ($hasOrder) return ['ok' => false, 'message' => 'This promo is for first orders only.'];
        }

        $discount = $this->discountAmount($voucher, $subtotal);

        return ['ok' => true, 'voucher' => $voucher, 'discount' => $discount, 'message' => 'Promo applied.'];
    }

    public function discountAmount(object $voucher, float $subtotal): float
    {
        $type = strtolower((string) $voucher->discount_type);
        $value = (float) $voucher->discount_value;

        $discount = match ($type) {
            'percent' => $subtotal * ($value / 100),
            'fixed' => $value,
            default => 0,
        };

        if ($voucher->max_discount !== null) {
            $discount = min($discount, (float) $voucher->max_discount);
        }

        return round(min(max(0, $discount), max(0, $subtotal)), 2);
    }

    public function recordRedemption(object $voucher, object $order, float $discount): void
    {
        DB::table('voucher_redemptions')->insert([
            'voucher_id' => $voucher->id,
            'order_id' => $order->id,
            'user_id' => $order->user_id ?? null,
            'guest_phone' => $order->guest_phone ?? null,
            'discount_amount' => $discount,
            'redeemed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
