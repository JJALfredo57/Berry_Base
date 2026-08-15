<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\SmsHelper;
use App\Services\MessageInteractionService;
use App\Services\MobileNotificationService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessageController extends Controller
{
    use UploadsFiles;
    /** Get messages for polling (returns JSON) */
    public function poll(string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return response()->json(['messages' => []]);

        $messages = app(MessageInteractionService::class)->decorate(DB::table('messages')
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get(), 'guest_customer', null, strtoupper($trackCode))
            ->map(function ($m) use ($order) {
                return [
                    'id'          => $m->id,
                    'role'        => $m->sender_role,
                    'message'     => $m->message,
                    'image_path'  => $m->image_path,
                    'is_read'     => (bool) $m->is_read,
                    'reply_to'    => $m->reply_to,
                    'reactions'   => $m->reaction_summary,
                    'my_reaction' => $m->my_reaction,
                    'created_at'  => \Carbon\Carbon::parse($m->created_at)->format('M d, g:i A'),
                    'is_admin'    => in_array($m->sender_role, ['admin', 'seller']),
                    'name'        => in_array($m->sender_role, ['admin', 'seller'])
                                     ? (config('app.name','Cake Shop').' Baker')
                                     : ($order->guest_name ?? 'You'),
                ];
            });

        // Mark admin messages as read when customer views
        DB::table('messages')
            ->where('order_id', $order->id)
            ->whereIn('sender_role', ['admin', 'seller'])
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['messages' => $messages]);
    }

    public function readStatuses(Request $request, string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return response()->json(['statuses' => []]);

        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(50)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['statuses' => []]);
        }

        $statuses = DB::table('messages')
            ->where('order_id', $order->id)
            ->where('sender_role', 'customer')
            ->whereIn('id', $ids)
            ->pluck('is_read', 'id')
            ->map(fn ($read) => (bool) $read);

        return response()->json(['statuses' => $statuses]);
    }

    /** Send a message from guest */
    public function send(Request $request, string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);

        $message = trim($request->input('message', ''));
        $replyToId = app(MessageInteractionService::class)->validateReply((int) $request->input('reply_to_id'), (string) $order->id);
        $imgPath = null;

        $files = $request->file('images') ?? [];
        if (!is_array($files)) $files = [$files];
        $paths = [];
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (in_array($ext, ['jpg','jpeg','png','webp','gif']) && $file->getSize() <= 10*1024*1024) {
                    $url = $this->uploadFile($file, 'uploads/messages');
                    if ($url) $paths[] = $url;
                }
            }
        }
        $imgPath = count($paths) === 1 ? $paths[0] : (count($paths) > 1 ? json_encode($paths) : null);

        if (!$message && !$imgPath)
            return response()->json(['ok' => false, 'error' => 'Message cannot be empty.'], 422);

        $row = [
            'order_id'    => $order->id,
            'sender_role' => 'customer',
            'sender_id'   => null,
            'message'     => $message ?: null,
            'image_path'  => $imgPath,
            'is_read'     => false,
            'created_at'  => now(),
        ];
        if (Schema::hasColumn('messages', 'reply_to_id')) {
            $row['reply_to_id'] = $replyToId;
        }
        $messageId = DB::table('messages')->insertGetId($row);

        // Notify admin
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Message from ' . ($order->guest_name ?? 'Customer'),
            'message'          => ($order->guest_name ?? 'Customer') . ' sent a message on Order #' . $order->id,
            'is_read' => false,
            'created_at'       => now(),
        ]);

        try {
            app(MobileNotificationService::class)->notifyOrderSeller(
                $order,
                'New Customer Message',
                $message !== '' ? mb_strimwidth($message, 0, 90, '...') : 'Customer sent image attachments.',
                ['event' => 'message']
            );
        } catch (\Throwable $e) {}

        $saved = DB::table('messages')->where('id', $messageId)->first();

        return response()->json([
            'ok' => true,
            'message' => [
                'id'          => $saved->id,
                'role'        => $saved->sender_role,
                'message'     => $saved->message,
                'image_path'  => $saved->image_path,
                'is_read'     => (bool) $saved->is_read,
                'reply_to'    => $replyToId ? app(MessageInteractionService::class)->summary(DB::table('messages')->where('id', $replyToId)->first()) : null,
                'reactions'   => [],
                'my_reaction' => null,
                'created_at'  => \Carbon\Carbon::parse($saved->created_at)->format('M d, g:i A'),
                'is_admin'    => false,
                'name'        => $order->guest_name ?? 'You',
            ],
        ]);
    }

    public function react(Request $request, string $trackCode, string $messageId)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);

        $result = app(MessageInteractionService::class)->react(
            (string) $order->id,
            (int) $messageId,
            'guest_customer',
            null,
            strtoupper($trackCode),
            (string) $request->input('reaction')
        );

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
