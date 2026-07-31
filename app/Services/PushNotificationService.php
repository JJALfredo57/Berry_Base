<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PushNotificationService
{
    public function sendToUser(string $role, ?string $userId, string $title, string $body, array $data = []): void
    {
        if (!$userId) {
            return;
        }

        $query = $this->baseTokenQuery();
        if (!$query) {
            return;
        }

        $this->sendToTokens($query->where('role', $role)->where('user_id', $userId)->pluck('device_token')->all(), $title, $body, $data + ['role' => $role]);
    }

    public function sendToGuestTrackCode(?string $trackCode, string $title, string $body, array $data = []): void
    {
        $trackCode = strtoupper(trim((string) $trackCode));
        if ($trackCode === '') {
            return;
        }

        $query = $this->baseTokenQuery();
        if (!$query) {
            return;
        }

        $this->sendToTokens($query->where('role', 'guest_customer')->where('guest_track_code', $trackCode)->pluck('device_token')->all(), $title, $body, $data + ['role' => 'guest_customer', 'track_code' => $trackCode]);
    }

    public function sendToRider(?int $riderId, string $title, string $body, array $data = []): void
    {
        if (!$riderId) {
            return;
        }

        $query = $this->baseTokenQuery();
        if (!$query) {
            return;
        }

        $this->sendToTokens($query->where('role', 'rider')->where('rider_id', $riderId)->pluck('device_token')->all(), $title, $body, $data + ['role' => 'rider', 'rider_id' => (string) $riderId]);
    }

    public function sendToOrderCustomer(object $order, string $title, string $body, array $data = []): void
    {
        $payload = $this->orderData($order, $data);
        if (!empty($order->user_id)) {
            $this->sendToUser('customer', (string) $order->user_id, $title, $body, $payload + [
                'url' => route('customer.orders', [], false),
            ]);
            return;
        }

        $this->sendToGuestTrackCode($order->track_code ?? null, $title, $body, $payload + [
            'url' => route('track.order', $order->track_code ?? '', false),
        ]);
    }

    public function sendToOrderSeller(object $order, string $title, string $body, array $data = []): void
    {
        $sellerId = null;
        if (!empty($order->shop_id)) {
            $sellerId = DB::table('shops')->where('id', $order->shop_id)->value('seller_id');
        }

        $this->sendToUser('seller', $sellerId ? (string) $sellerId : null, $title, $body, $this->orderData($order, $data) + [
            'url' => route('seller.orders', [], false),
        ]);
    }

    public function sendToOrderRider(object $order, string $title, string $body, array $data = []): void
    {
        $url = '';
        if (!empty($order->id) && !empty($order->rider_token)) {
            $url = route('rider.show', [$order->id, $order->rider_token], false);
        }

        $this->sendToRider((int) ($order->rider_id ?? 0), $title, $body, $this->orderData($order, $data) + ['url' => $url]);
    }

    public function notifyPaymentComplete(object $order): void
    {
        $isFull = ($order->payment_status ?? '') === 'Paid';
        $amount = number_format((float) ($order->total_price ?? 0), 2);
        $sellerTitle = $isFull ? 'GCash Payment Complete' : 'GCash Deposit Paid';
        $sellerBody = $isFull
            ? "Order #{$order->id} is now paid. Amount: PHP {$amount}."
            : "Order #{$order->id} has a paid deposit and is ready for confirmation.";
        $this->sendToOrderSeller($order, $sellerTitle, $sellerBody, [
            'event' => 'payment_complete',
        ]);
        $this->sendToOrderRider($order, $isFull ? 'Payment Complete' : 'Deposit Paid', $isFull ? "Order #{$order->id} is paid. You may continue delivery when assigned." : "Order #{$order->id} has a paid deposit.", [
            'event' => 'payment_complete',
        ]);
        $this->sendToOrderCustomer($order, 'Payment Received', "Your payment for Order #{$order->id} was received.", [
            'event' => 'payment_complete',
        ]);
    }

    private function baseTokenQuery(): ?\Illuminate\Database\Query\Builder
    {
        if (!Schema::hasTable('device_sessions')) {
            return null;
        }

        return DB::table('device_sessions')
            ->where('is_push_enabled', true)
            ->whereNull('revoked_at');
    }

    private function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (!$tokens) {
            return;
        }

        $projectId = config('services.fcm.project_id');
        $accessToken = $this->accessToken();
        if (!$projectId || !$accessToken) {
            Log::info('Push skipped: Firebase is not configured.');
            return;
        }

        foreach ($tokens as $token) {
            $this->sendOne($projectId, $accessToken, $token, $title, $body, $data);
        }
    }

    private function sendOne(string $projectId, string $accessToken, string $token, string $title, string $body, array $data): void
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $this->stringData($data),
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => ['channel_id' => 'berry_orders'],
                        ],
                    ],
                ]);

            if ($response->status() === 404 || $response->status() === 400) {
                DB::table('device_sessions')
                    ->where('token_hash', hash('sha256', $token))
                    ->update(['is_push_enabled' => false, 'revoked_at' => now(), 'updated_at' => now()]);
            }

            if (!$response->successful()) {
                Log::warning('Push send failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Push send exception: ' . $e->getMessage());
        }
    }

    private function accessToken(): ?string
    {
        $credentials = $this->credentials();
        if (!$credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            return null;
        }

        return Cache::remember('fcm_access_token_' . sha1($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $jwt = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
                . '.'
                . $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

            $signature = '';
            openssl_sign($jwt, $signature, str_replace('\n', "\n", $credentials['private_key']), OPENSSL_ALGO_SHA256);
            $assertion = $jwt . '.' . $this->base64Url($signature);

            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (!$response->successful()) {
                Log::warning('FCM access token failed', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    private function credentials(): ?array
    {
        $json = config('services.fcm.credentials_json');
        if ($json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $path = config('services.fcm.credentials_path');
        if ($path && is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function stringData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $out[(string) $key] = (string) $value;
            }
        }
        return $out;
    }

    private function orderData(object $order, array $data): array
    {
        return $data + [
            'order_id' => (string) ($order->id ?? ''),
            'track_code' => (string) ($order->track_code ?? ''),
        ];
    }
}
