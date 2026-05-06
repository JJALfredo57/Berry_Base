<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SmsHelper
{
    /**
     * Send an SMS via UniSMS.
     * Returns ['ok' => bool, 'error' => string|null].
     */
    public static function sendWithResult(string $phone, string $message, bool $isOtpCall = false): array
    {
        $apiKey  = config('unisms.api_key', '');
        $devMode = false;

        try {
            $p = DB::table('platform_settings')->first();
            if (!empty($p->philsms_token)) $apiKey  = $p->philsms_token;
            if (!empty($p->dev_mode))      $devMode = true;
        } catch (\Throwable $e) {}

        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($cleanPhone, '0'))   $cleanPhone = '63' . substr($cleanPhone, 1);
        if (!str_starts_with($cleanPhone, '63')) $cleanPhone = '63' . $cleanPhone;

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
            Log::warning('UniSMS: API key not configured.', ['to' => $cleanPhone]);
            return ['ok' => false, 'error' => 'SMS service is not configured. Please contact the platform administrator.'];
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
                CURLOPT_HTTPHEADER     => [
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
                Log::error('UniSMS cURL error.', ['to' => $cleanPhone, 'curl_error' => $curlErr]);
                return ['ok' => false, 'error' => 'Network error while contacting SMS gateway. Please try again.'];
            }

            Log::info('UniSMS response.', [
                'http_code' => $httpCode,
                'to'        => $cleanPhone,
                'body'      => $data ?? $response,
            ]);

            if ($httpCode < 200 || $httpCode >= 300) {
                $apiMsg = self::extractApiError($data);
                Log::warning('UniSMS rejected request.', ['http_code' => $httpCode, 'to' => $cleanPhone, 'api_error' => $apiMsg]);
                return ['ok' => false, 'error' => 'SMS gateway rejected the request' . ($apiMsg ? ': ' . $apiMsg : '.') . ' Please check the SMS settings.'];
            }

            if (is_array($data)) {
                if (array_key_exists('success', $data) && !$data['success']) {
                    $apiMsg = self::extractApiError($data);
                    return ['ok' => false, 'error' => 'SMS gateway reported a failure' . ($apiMsg ? ': ' . $apiMsg : '.') ];
                }
                if (isset($data['status']) && in_array(strtolower((string) $data['status']), ['failed', 'error'], true)) {
                    return ['ok' => false, 'error' => 'SMS gateway reported delivery status: ' . $data['status'] . '.'];
                }
            }

            return ['ok' => true, 'error' => null];

        } catch (\Throwable $e) {
            Log::error('UniSMS unexpected error.', ['message' => $e->getMessage(), 'to' => $cleanPhone]);
            return ['ok' => false, 'error' => 'An unexpected error occurred while sending SMS. Please try again.'];
        }
    }

    /** Backward-compatible boolean wrapper. */
    public static function send(string $phone, string $message, bool $isOtpCall = false): bool
    {
        return self::sendWithResult($phone, $message, $isOtpCall)['ok'];
    }

    public static function sendOtp(string $phone, string $otp, string $siteName = 'Cake Shop', string $recipientName = '', string $shopName = '', string $trackCode = '', string $trackUrl = ''): bool
    {
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

        $header          = self::header($siteName, $shopName);
        $trackingSection = $trackCode
            ? "\n\nYour Order Tracking Code: {$trackCode}\nUse this code to track your order on our website."
            : '';

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

    public static function generateRiderPin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

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
            $siteName = config('app.name', 'Cake Shop');
            $pinLine  = "\n\nDelivery PIN: {$pin}"
                . "\n\nOpen {$siteName}, tap the menu, enter your PIN to access your delivery.";
        }

        return "{$header}\n"
            . "Order #{$orderId} - New Delivery\n\n"
            . "Customer: {$custName}{$phoneLine}\n"
            . "Address: {$addr}\n\n"
            . "Payment: {$paymentInfo}"
            . $pinLine;
    }

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

    /** Extract a readable error string from a UniSMS error payload. */
    private static function extractApiError(?array $data): string
    {
        if (!$data) return '';
        if (isset($data['message']) && is_string($data['message'])) return $data['message'];
        if (isset($data['error']))   return is_string($data['error'])   ? $data['error']   : json_encode($data['error']);
        if (isset($data['errors']))  return is_string($data['errors'])  ? $data['errors']  : json_encode($data['errors']);
        return '';
    }

    private static function clean(string $text): string
    {
        $text = str_replace(
            ['₱', '—', '–', '✓', '✔', "\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"],
            ['PHP ', '-', '-', '(Paid)', '(Paid)', "'", "'", '"', '"'],
            $text
        );
        $text = preg_replace('/[^\x00-\x7F]/u', '', $text);
        $text = preg_replace('/ {2,}/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
