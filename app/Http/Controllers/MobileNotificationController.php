<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileNotificationController extends Controller
{
    public function trackIndex(Request $request, string $trackCode)
    {
        if (!Schema::hasTable('mobile_notifications') || !$this->trackExists($trackCode)) {
            return response()->json(['ok' => true, 'notifications' => [], 'unread' => 0, 'has_more' => false]);
        }

        $limit = min(30, max(5, (int) $request->query('limit', 10)));
        $offset = max(0, (int) $request->query('offset', 0));
        $trackCode = strtoupper($trackCode);

        $base = DB::table('mobile_notifications')
            ->where('role', 'guest_customer')
            ->where('guest_track_code', $trackCode);

        $unread = (clone $base)->where('is_read', false)->count();
        $rows = (clone $base)
            ->orderBy('is_read')
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        return response()->json([
            'ok' => true,
            'unread' => $unread,
            'has_more' => $rows->count() > $limit,
            'notifications' => $rows->take($limit)->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'message' => $row->message,
                'event_type' => $row->event_type,
                'url' => $row->url,
                'is_read' => (bool) $row->is_read,
                'created_at' => (string) $row->created_at,
                'created_label' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->diffForHumans() : '',
            ])->values(),
        ]);
    }

    public function trackRead(string $trackCode, string $id)
    {
        if (Schema::hasTable('mobile_notifications') && $this->trackExists($trackCode)) {
            DB::table('mobile_notifications')
                ->where('id', $id)
                ->where('role', 'guest_customer')
                ->where('guest_track_code', strtoupper($trackCode))
                ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function trackReadAll(string $trackCode)
    {
        if (Schema::hasTable('mobile_notifications') && $this->trackExists($trackCode)) {
            DB::table('mobile_notifications')
                ->where('role', 'guest_customer')
                ->where('guest_track_code', strtoupper($trackCode))
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    private function trackExists(string $trackCode): bool
    {
        return DB::table('orders')->where('track_code', strtoupper($trackCode))->exists();
    }
}
