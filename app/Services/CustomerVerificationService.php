<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CustomerVerificationService
{
    public function latest(?string $userId): ?object
    {
        if (!$userId) return null;

        return DB::table('customer_verifications')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();
    }

    public function status(?string $userId): string
    {
        $latest = $this->latest($userId);
        return $latest->status ?? 'not_submitted';
    }

    public function isVerified(?string $userId): bool
    {
        return $this->status($userId) === 'approved';
    }

    public function benefits(string $status): array
    {
        return [
            [
                'icon' => 'bi-patch-check-fill',
                'title' => 'Verified customer badge',
                'copy' => 'Sellers can quickly see that your account is trusted.',
                'unlocked' => $status === 'approved',
            ],
            [
                'icon' => 'bi-stars',
                'title' => 'Redeem loyalty rewards',
                'copy' => 'Use earned points as checkout discounts once verified.',
                'unlocked' => $status === 'approved',
            ],
            [
                'icon' => 'bi-ticket-perforated',
                'title' => 'Verified-only vouchers',
                'copy' => 'Unlock exclusive promo codes for verified accounts.',
                'unlocked' => $status === 'approved',
            ],
            [
                'icon' => 'bi-shield-check',
                'title' => 'Higher order trust',
                'copy' => 'Large COD/COP orders are easier to approve with verified identity.',
                'unlocked' => $status === 'approved',
            ],
        ];
    }

    public function limitations(string $status): array
    {
        if ($status === 'approved') return [];

        return [
            'You can still order, but rewards redemption stays locked.',
            'Verified-only vouchers will not apply yet.',
            'High-value COD/COP orders may require stricter review or deposit.',
            'Your account will not show a verified badge to sellers.',
        ];
    }
}
