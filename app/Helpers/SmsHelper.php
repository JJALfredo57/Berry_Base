<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SmsHelper
{
    public static function send(string $phone, string $message, bool $isOtpCall = false): bool
    {
        $apiKey   = config('unisms.api_key', '');
        $senderId = config('unisms.sender_id', '');
        $devMode  = false;

        // Platform settings always takes priority over .env placeholder
        try {
            $p = DB::table('platform_settings')->first();
            if (!empty($p->philsms_token))  $apiKey   = $p->philsms_token;
            if (!empty($p->philsms_sender)) $senderId = $p->philsms_sender;
            if (!empty($p->dev_mode))       $devMode  = true;
        } catch (\Throwable $e) {}

        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($cleanPhone, '0'))   $cleanPhone = '63' . substr($cleanPhone, 1);
        if (!str_starts_with($cleanPhone, '63')) $cleanPhone = '63' . $cleanPhone;

        // Dev mode: queue non-OTP SMS as a toast notification
        if ($devMode && !$isOtpCall) {
            try {
                $queue = session('dev_sms_queue', []);
                array_unshift($queue, [
                    'to'      => $cleanPhone,
                    'message' => $message,
                    'time'    => now()->format('h:i A'),
                ]);
                session(['dev_sms_queue' => array_slice($queue, 0, 10)]);
            } catch (\Throwable $e) {}
        }

        if (empty($apiKey)) {
            Log::warning('UniSMS not configured.', ['to' => $cleanPhone]);
            return false;
        }

        try {
            $ch = curl_init();
            $payload = [
                'recipient' => '+' . $cleanPhone,
                'content'   => self::clean($message),
            ];

            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://unismsapi.com/api/sms',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Basic ' . base64_encode($apiKey . ':'),
                ],
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            $data     = json_decode($response, true);
            curl_close($ch);

            if ($curlErr) {
                Log::error('UniSMS cURL error: ' . $curlErr, ['to' => $cleanPhone]);
                return false;
            }

            Log::info('UniSMS Response', [
                'http_code' => $httpCode,
                'to'        => $cleanPhone,
                'sender_id' => $senderId ?: null,
                'body'      => $data ?? $response,
            ]);

            if ($httpCode < 200 || $httpCode >= 300) {
                return false;
            }

            if (is_array($data)) {
                if (array_key_exists('success', $data)) {
                    return (bool) $data['success'];
                }
                if (isset($data['status'])) {
                    return !in_array(strtolower((string) $data['status']), ['failed', 'error'], true);
                }
                if (isset($data['error'])) {
                    return false;
                }
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('UniSMS error: ' . $e->getMessage());
            return false;
        }
    }

    public static function sendOtp(string $phone, string $otp, string $siteName = 'Cake Shop', string $recipientName = '', string $shopName = '', string $trackCode = '', string $trackUrl = ''): bool
    {
        // Dev mode: store OTP for display below the input field
        try {
            $p = DB::table('platform_settings')->first();
            if (!empty($p->dev_mode)) {
                $cleanPhone = preg_replace('/\D/', '', $phone);
                if (str_starts_with($cleanPhone, '0'))   $cleanPhone = '63' . substr($cleanPhone, 1);
                if (!str_starts_with($cleanPhone, '63')) $cleanPhone = '63' . $cleanPhone;

                session(['dev_otp' => [
                    'otp'   => $otp,
                    'phone' => $cleanPhone,
                    'name'  => $recipientName,
                    'time'  => now()->format('h:i A'),
                ]]);
            }
        } catch (\Throwable $e) {}

        $header  = self::header($siteName, $shopName);

        $trackingSection = '';
        if ($trackCode) {
            $trackingSection = "\n\nYour Order Tracking Code: {$trackCode}\n"
                . "Use this code to track your order on our website.";
        }

        $message = "{$header}\n"
            . "Your one-time verification code is: {$otp}\n\n"
            . "Valid for 10 minutes only.\n\n"
            . "WARNING: NEVER share this code with anyone - "
            . "not even our staff. We will NEVER ask for your OTP. "
            . "If someone is asking for this code, it is a scam. "
            . "Do not give it."
            . $trackingSection;

        return self::send($phone, $message, true);
    }

    public static function header(string $siteName, string $shopName = ''): string
    {
        return $shopName ? "[{$siteName} - {$shopName}]" : "[{$siteName}]";
    }

    /** Generate a fresh 6-digit rider access PIN. */
    public static function generateRiderPin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Build a rider delivery assignment SMS.
     * Kept as a single source of truth so the wording never triggers
     * carrier spam filters again (avoid: "link", "portal", "dispatcher").
     */
    public static function buildRiderSms(
        string $header,
        string $orderId,
        string $custName,
        string $custPhone,
        string $addr,
        string $paymentInfo,
        string $pin = '',
        string $riderPhone = '',
        string $token = ''
    ): string {
        $phoneLine = $custPhone ? "\nPhone: {$custPhone}" : '';

        $pinLine = '';
        if ($pin) {
            $base = rtrim(config('app.url', ''), '/');
            if ($token) {
                $pinLine = "\n\nTap to open your delivery page:\nhttps://" . parse_url($base, PHP_URL_HOST) . "/rider/{$orderId}/{$token}";
            } else {
                $rp = preg_replace('/\D/', '', $riderPhone);
                if (str_starts_with($rp, '63')) $rp = '0' . substr($rp, 2);
                $code = ($rp ?: '?') . '|' . $pin;
                $host = parse_url($base, PHP_URL_HOST) ?: request()->getHost();
                $pinLine = "\n\nYour delivery code:\n{$code}\nGo to https://{$host} then tap the menu and select Rider.";
            }
        }

        return "{$header}\n"
            . "Order #{$orderId} - New Delivery\n\n"
            . "Customer: {$custName}{$phoneLine}\n"
            . "Address: {$addr}\n\n"
            . "Payment: {$paymentInfo}"
            . $pinLine;
    }

    /** Build readable payment line for a given order object. */
    public static function paymentLine(object $order): string
    {
        if ($order->payment_method === 'COD') {
            return CakeshopHelper::shortPaymentCode($order->payment_method, $order->fulfillment_type ?? null)
                . ' - PHP ' . number_format($order->total_price, 2);
        }
        if ($order->payment_status === 'Paid') {
            return 'GCash - Paid';
        }
        if ($order->payment_status === 'Partial Payment') {
            $rem = $order->total_price - ($order->deposit_amount ?? 0);
            return 'Balance PHP ' . number_format($rem, 2) . ' (deposit paid)';
        }
        return 'GCash - PHP ' . number_format($order->total_price, 2);
    }

    public static function getShopName(int|string|null $shopId): string
    {
        if (!$shopId) return '';
        try {
            return DB::table('shops')->where('id', $shopId)->value('shop_name') ?? '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function clean(string $text): string
    {
        // Replace special characters with ASCII equivalents
        $text = str_replace(
            ['₱', '—', '–', '✓', '✔', "\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"],
            ['PHP ', '-', '-', '(Paid)', '(Paid)', "'", "'", '"', '"'],
            $text
        );

        // Strip emojis and all remaining non-ASCII characters
        $text = preg_replace('/[^\x00-\x7F]/u', '', $text);

        // Normalize multiple spaces and blank lines
        $text = preg_replace('/ {2,}/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
