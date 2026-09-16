<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerIdentityService
{
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '63' . $digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '63' . substr($digits, 1);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            // Already normalized.
        } else {
            return null;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '639')
            ? '+' . $digits
            : null;
    }

    public function phoneVariants(?string $phone): array
    {
        $normalized = $this->normalizePhone($phone);
        if (!$normalized) {
            return [];
        }

        $digits = substr($normalized, 1);
        return array_values(array_unique(array_filter([
            $phone,
            $normalized,
            $digits,
            '0' . substr($digits, 2),
        ])));
    }

    public function customerByPhone(?string $phone): ?object
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $variants = $this->phoneVariants($phone);
        if (!$variants) {
            return null;
        }

        return DB::table('users')
            ->where('role', 'customer')
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', $variant);
                }
            })
            ->orderByDesc('created_at')
            ->first();
    }

    public function userConflict(?string $username, ?string $email, ?string $phone): ?object
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $email = strtolower(trim((string) $email));
        $username = trim((string) $username);
        $phoneVariants = $this->phoneVariants($phone);

        return DB::table('users')
            ->where(function ($query) use ($username, $email, $phoneVariants) {
                if ($username !== '') {
                    $query->orWhere('username', $username);
                }
                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
                foreach ($phoneVariants as $variant) {
                    $query->orWhere('phone', $variant);
                }
            })
            ->first();
    }

    public function conflictMessage(object $user, ?string $username, ?string $email, ?string $phone): string
    {
        $email = strtolower(trim((string) $email));
        $username = trim((string) $username);
        $phoneVariants = $this->phoneVariants($phone);

        if ($phoneVariants && in_array((string) $user->phone, $phoneVariants, true)) {
            return 'This phone number is already linked to an account. Please log in or use another number.';
        }

        if ($email !== '' && strtolower((string) $user->email) === $email) {
            return 'This email is already linked to an account. Please log in or use another email.';
        }

        if ($username !== '' && (string) $user->username === $username) {
            return 'This username is already taken. Please choose another username.';
        }

        return 'An account already exists with these details. Please log in or use different details.';
    }
}
