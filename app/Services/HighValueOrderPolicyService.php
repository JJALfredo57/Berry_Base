<?php

namespace App\Services;

class HighValueOrderPolicyService
{
    public const THRESHOLD = 10000.0;

    public function assess(?string $userId, float $payableTotal): array
    {
        $total = max(0, round($payableTotal, 2));
        $verified = app(CustomerVerificationService::class)->isVerified($userId);
        $requiresFullGcash = !$verified && $total >= self::THRESHOLD;

        return [
            'verified' => $verified,
            'threshold' => self::THRESHOLD,
            'payable_total' => $total,
            'requires_full_gcash' => $requiresFullGcash,
            'message' => $requiresFullGcash
                ? 'This order is PHP 10,000.00 or higher. Because your account is not yet verified, full GCash payment is required. Verify your account to use the standard 50% deposit option for high-value orders.'
                : null,
        ];
    }

    public function validatePayment(?string $userId, float $payableTotal, string $paymentMethod): array
    {
        $assessment = $this->assess($userId, $payableTotal);
        if ($assessment['requires_full_gcash'] && $paymentMethod !== 'GCash') {
            return $assessment + ['ok' => false];
        }

        return $assessment + ['ok' => true];
    }
}
