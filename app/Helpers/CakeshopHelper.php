<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CakeshopHelper
{
    /**
     * Generate a unique random string ID (e.g. i74f5ycrfnxd)
     * Checks the given table/column for uniqueness before returning.
     */
    public static function generateId(string $table = 'users', string $column = 'id', int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        do {
            $id = '';
            for ($i = 0; $i < $length; $i++) {
                $id .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (\Illuminate\Support\Facades\DB::table($table)->where($column, $id)->exists());
        return $id;
    }
    public static function getSettings(): array
    {
        try {
            $row = DB::table('site_settings')->where('id', 1)->first();
            if ($row) return (array) $row;
        } catch (\Exception $e) {}
        return [
            'site_title'           => 'Simple Cake Shop',
            'tagline'              => 'Online Catalog & Order Request',
            'logo_path'            => '',
            'bg_type'              => 'gradient',
            'bg_color'             => '#fff7fb',
            'gradient_start'       => '#fff7fb',
            'gradient_end'         => '#ffe3f1',
            'bg_image_path'        => '',
            'bg_image_opacity'     => 1.0,
            'primary_color'        => '#e91e63',
            'paymongo_mode'        => 'test',
            'paymongo_test_secret' => '',
            'paymongo_test_public' => '',
            'paymongo_live_secret' => '',
            'paymongo_live_public' => '',
        ];
    }

    /**
     * Get the active PayMongo secret key based on current mode (test/live)
     */
    public static function getPaymongoSecretKey(): string
    {
        $settings = self::getSettings();
        $mode = $settings['paymongo_mode'] ?? 'test';

        $key = $mode === 'live'
            ? ($settings['paymongo_live_secret'] ?? '')
            : ($settings['paymongo_test_secret'] ?? '');

        // Fallback to platform_settings (Super Admin), then .env
        if (empty($key)) {
            try {
                $p    = DB::table('platform_settings')->first();
                $pMode = $p->paymongo_mode ?? 'test';
                $key  = $pMode === 'live'
                    ? ($p->paymongo_live_secret ?? $p->paymongo_secret_key ?? '')
                    : ($p->paymongo_test_secret ?? $p->paymongo_secret_key ?? '');
            } catch (\Exception $e) {}
        }
        if (empty($key)) $key = config('paymongo.secret_key', '');

        return $key;
    }

    public static function getPaymongoPublicKey(): string
    {
        $settings = self::getSettings();
        $mode = $settings['paymongo_mode'] ?? 'test';

        $key = $mode === 'live'
            ? ($settings['paymongo_live_public'] ?? '')
            : ($settings['paymongo_test_public'] ?? '');

        // Fallback to platform_settings (Super Admin), then .env
        if (empty($key)) {
            try {
                $p    = DB::table('platform_settings')->first();
                $pMode = $p->paymongo_mode ?? 'test';
                $key  = $pMode === 'live'
                    ? ($p->paymongo_live_public ?? $p->paymongo_public_key ?? '')
                    : ($p->paymongo_test_public ?? $p->paymongo_public_key ?? '');
            } catch (\Exception $e) {}
        }
        if (empty($key)) $key = config('paymongo.public_key', '');

        return $key;
    }

    /**
     * Get current PayMongo mode
     */
    public static function getPaymongoMode(): string
    {
        $settings = self::getSettings();
        return $settings['paymongo_mode'] ?? 'test';
    }

    public static function displayPaymentMethod(?string $paymentMethod, ?string $fulfillmentType = null): string
    {
        $method = strtoupper(trim((string) $paymentMethod));

        if ($method === 'COD') {
            return strtoupper((string) $fulfillmentType) === 'PICKUP'
                ? 'Cash on Pickup (COP)'
                : 'Cash on Delivery (COD)';
        }

        if ($method === 'GCASH') {
            return 'GCash';
        }

        return $paymentMethod ?: 'Unspecified';
    }

    public static function shortPaymentCode(?string $paymentMethod, ?string $fulfillmentType = null): string
    {
        $method = strtoupper(trim((string) $paymentMethod));

        if ($method === 'COD') {
            return strtoupper((string) $fulfillmentType) === 'PICKUP' ? 'COP' : 'COD';
        }

        return $paymentMethod ?: 'N/A';
    }

    public static function getActiveDiscountMap(array $productIds): array
    {
        $productIds = array_values(array_filter(array_unique($productIds)));
        if (!$productIds) {
            return [];
        }

        try {
            $now = now()->format('Y-m-d H:i:s');
            $rows = DB::table('product_discounts')
                ->whereIn('product_id', $productIds)
                ->where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->orderByDesc('id')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                if (!isset($map[$row->product_id])) {
                    $map[$row->product_id] = $row;
                }
            }

            return $map;
        } catch (\Exception $e) {
            Log::warning('getActiveDiscountMap failed: ' . $e->getMessage());
            return [];
        }
    }

    public static function getDiscountConfigMap(array $productIds): array
    {
        $productIds = array_values(array_filter(array_unique($productIds)));
        if (!$productIds) {
            return [];
        }

        try {
            $rows = DB::table('product_discounts')
                ->whereIn('product_id', $productIds)
                ->orderByDesc('id')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                if (!isset($map[$row->product_id])) {
                    $map[$row->product_id] = $row;
                }
            }

            return $map;
        } catch (\Exception $e) {
            Log::warning('getDiscountConfigMap failed: ' . $e->getMessage());
            return [];
        }
    }

    public static function getActiveProductDiscount(string $productId): ?object
    {
        return self::getActiveDiscountMap([$productId])[$productId] ?? null;
    }

    public static function resolveProductUnitPrice(string $productId, float $fallbackPrice, ?string $selectedSize = null): float
    {
        $selectedSize = trim((string) $selectedSize);
        if ($selectedSize === '') {
            return $fallbackPrice;
        }

        try {
            $size = DB::table('product_sizes')
                ->where('product_id', $productId)
                ->where('label', $selectedSize)
                ->where('is_active', true)
                ->first();

            if ($size) {
                return (float) $size->price;
            }
        } catch (\Throwable $e) {}

        return $fallbackPrice;
    }

    public static function calculateDiscountSnapshot(float $originalUnitPrice, ?object $discount = null): array
    {
        $originalUnitPrice = max(0, round($originalUnitPrice, 2));
        $discountAmount = 0.0;
        $discountType = null;
        $discountValue = null;
        $discountLabel = null;

        if ($discount && !empty($discount->discount_type)) {
            $discountType = strtolower(trim((string) $discount->discount_type));
            $discountValue = (float) ($discount->discount_value ?? 0);
            $discountLabel = trim((string) ($discount->label ?? '')) ?: null;

            if ($discountType === 'percent') {
                $discountValue = min(max($discountValue, 0), 100);
                $discountAmount = round($originalUnitPrice * ($discountValue / 100), 2);
            } elseif ($discountType === 'fixed') {
                $discountAmount = round(max(0, $discountValue), 2);
            }
        }

        $discountAmount = min($discountAmount, $originalUnitPrice);
        $finalUnitPrice = max(0, round($originalUnitPrice - $discountAmount, 2));

        return [
            'has_discount'        => $discountAmount > 0,
            'original_unit_price' => $originalUnitPrice,
            'discount_label'      => $discountLabel,
            'discount_type'       => $discountType,
            'discount_value'      => $discountValue,
            'discount_amount'     => round($discountAmount, 2),
            'final_unit_price'    => $finalUnitPrice,
            'badge_text'          => self::discountBadgeText($discountType, $discountValue),
        ];
    }

    public static function discountBadgeText(?string $discountType, float|int|null $discountValue): ?string
    {
        $value = (float) ($discountValue ?? 0);
        if ($value <= 0) {
            return null;
        }

        return match (strtolower((string) $discountType)) {
            'percent' => rtrim(rtrim(number_format($value, 2), '0'), '.') . '% OFF',
            'fixed'   => 'PHP ' . number_format($value, 2) . ' OFF',
            default   => null,
        };
    }

    public static function backgroundCss(array $s): string
    {
        $type = $s['bg_type'] ?? 'gradient';
        if ($type === 'image' && !empty($s['bg_image_path'])) {
            // Opacity-controlled via a fixed overlay div in app.blade.php
            return "background: transparent;";
        }
        if ($type === 'color') {
            return "background: " . ($s['bg_color'] ?? '#ffffff') . ";";
        }
        $a = $s['gradient_start'] ?? '#fff7fb';
        $b = $s['gradient_end']   ?? '#ffe3f1';
        return "background: linear-gradient(135deg, {$a}, {$b});";
    }

    public static function logActivity(string|int $userId, string $role, string $action, string $desc = ''): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id'    => $userId,
                'role'       => $role,
                'action'     => $action,
                'details'    => $desc,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {}
    }

    public static function sendOtpEmail(string $toEmail, string $otp, string $purpose = 'OTP Verification'): bool
    {
        try {
            $settings = self::getSettings();
            $siteName = $settings['site_title'] ?? 'Cake Shop';
            $fromAddr = config('mail.from.address', 'no-reply@cakeshop.com');
            $fromName = config('mail.from.name', $siteName);

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #f0c0d0;border-radius:12px;overflow:hidden'>
              <div style='background:#e91e63;padding:24px;text-align:center'>
                <h2 style='color:#fff;margin:0;font-size:22px'>{$siteName}</h2>
              </div>
              <div style='padding:32px;background:#fff'>
                <h3 style='color:#333;margin-top:0'>{$purpose}</h3>
                <p style='color:#555;font-size:15px'>Your one-time verification code is:</p>
                <div style='font-size:40px;font-weight:bold;letter-spacing:10px;text-align:center;color:#e91e63;background:#fff0f5;padding:20px;border-radius:8px;margin:16px 0'>{$otp}</div>
                <p style='color:#888;font-size:13px'>This code expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0'>
                <p style='color:#aaa;font-size:12px;text-align:center'>If you did not request this, you can safely ignore this email.</p>
              </div>
            </div>";

            Mail::send([], [], function ($msg) use ($toEmail, $otp, $siteName, $fromAddr, $fromName, $html, $purpose) {
                $msg->to($toEmail)
                    ->from($fromAddr, $fromName)
                    ->subject("{$siteName} — Your OTP Code: {$otp}")
                    ->html($html);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('OTP Email failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function unreadMessagesCount(string $role, string|int|null $userId = null): int
    {
        try {
            if ($role === 'admin') {
                return (int) DB::table('messages')
                    ->where('sender_role', 'customer')
                    ->where('is_read', false)
                    ->count();
            }
            // Count unread admin messages on order threads
            $orderUnread = (int) DB::table('messages as m')
                ->join('orders as o', 'o.id', '=', 'm.order_id')
                ->where('o.user_id', $userId)
                ->where('m.sender_role', 'admin')
                ->where('m.is_read', false)
                ->count();

            // Count unread general messages (no order) sent to this customer
            $generalUnread = 0;
            try {
                $generalUnread = (int) DB::table('messages')
                    ->whereNull('order_id')
                    ->where('sender_role', 'admin')
                    ->where('user_id', $userId)
                    ->where('is_read', false)
                    ->count();
            } catch (\Exception $e) {}

            return $orderUnread + $generalUnread;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function exportSql(string $dbName): string
    {
        $pdo = DB::getPdo();
        $sql = "-- Backup of {$dbName}\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $tables = [];
        $res = $pdo->query('SHOW TABLES');
        while ($row = $res->fetch(\PDO::FETCH_NUM)) $tables[] = $row[0];
        foreach ($tables as $t) {
            $sql .= "DROP TABLE IF EXISTS `{$t}`;\n";
            $create = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(\PDO::FETCH_ASSOC);
            $sql .= $create['Create Table'] . ";\n\n";
            $rows = $pdo->query("SELECT * FROM `{$t}`");
            while ($rr = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $cols = array_map(fn($c) => "`{$c}`", array_keys($rr));
                $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", array_values($rr));
                $sql .= "INSERT INTO `{$t}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }
        return $sql;
    }
}
