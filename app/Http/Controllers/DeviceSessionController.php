<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeviceSessionController extends Controller
{
    public function register(Request $request)
    {
        if (!Schema::hasTable('device_sessions')) {
            return response()->json(['ok' => false, 'error' => 'Device registration is not ready yet.'], 503);
        }

        $validated = $request->validate([
            'device_token'     => 'required|string|min:20|max:4096',
            'device_type'      => 'nullable|string|max:30',
            'platform'         => 'nullable|string|max:80',
            'device_name'      => 'nullable|string|max:120',
            'guest_track_code' => 'nullable|string|max:30',
            'rider_order_id'   => 'nullable|string|max:30',
            'rider_token'      => 'nullable|string|max:80',
        ]);

        [$role, $userId, $riderId, $guestTrackCode] = $this->resolveOwner($request, $validated);
        if (!$role) {
            return response()->json(['ok' => false, 'error' => 'No customer, seller, or rider context found.'], 422);
        }

        $token = trim($validated['device_token']);
        DB::table('device_sessions')->updateOrInsert(
            ['token_hash' => hash('sha256', $token)],
            [
                'role'             => $role,
                'user_id'          => $userId,
                'rider_id'         => $riderId,
                'guest_track_code' => $guestTrackCode,
                'device_token'     => $token,
                'device_type'      => $validated['device_type'] ?? 'android',
                'platform'         => $validated['platform'] ?? null,
                'device_name'      => $validated['device_name'] ?? null,
                'user_agent'       => substr((string) $request->userAgent(), 0, 1000),
                'is_push_enabled'  => true,
                'last_seen_at'     => now(),
                'revoked_at'       => null,
                'updated_at'       => now(),
                'created_at'       => now(),
            ]
        );

        return response()->json(['ok' => true, 'role' => $role]);
    }

    public function unregister(Request $request)
    {
        if (!Schema::hasTable('device_sessions')) {
            return response()->json(['ok' => true]);
        }

        $token = trim((string) $request->input('device_token', ''));
        if ($token !== '') {
            DB::table('device_sessions')
                ->where('token_hash', hash('sha256', $token))
                ->update(['is_push_enabled' => false, 'revoked_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    private function resolveOwner(Request $request, array $data): array
    {
        $sessionUser = $request->session()->get('user');
        if (is_array($sessionUser) && !empty($sessionUser['id']) && !empty($sessionUser['role'])) {
            return [(string) $sessionUser['role'], (string) $sessionUser['id'], null, null];
        }

        $riderOrderId = trim((string) ($data['rider_order_id'] ?? ''));
        $riderToken   = trim((string) ($data['rider_token'] ?? ''));
        if ($riderOrderId !== '' && $riderToken !== '') {
            $order = DB::table('orders')
                ->where('id', $riderOrderId)
                ->where('rider_token', $riderToken)
                ->whereNotNull('rider_id')
                ->first();
            if ($order) {
                return ['rider', null, (int) $order->rider_id, null];
            }
        }

        $trackCode = strtoupper(trim((string) ($data['guest_track_code'] ?? $request->session()->get('guest_track_code', ''))));
        if ($trackCode !== '') {
            $exists = DB::table('orders')->where('track_code', $trackCode)->exists();
            if ($exists) {
                return ['guest_customer', null, null, $trackCode];
            }
        }

        return [null, null, null, null];
    }
}
