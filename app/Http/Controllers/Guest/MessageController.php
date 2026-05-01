<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Helpers\SmsHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    use UploadsFiles;
    /** Get messages for polling (returns JSON) */
    public function poll(string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return response()->json(['messages' => []]);

        $messages = DB::table('messages')
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($m) use ($order) {
                return [
                    'id'          => $m->id,
                    'role'        => $m->sender_role,
                    'message'     => $m->message,
                    'image_path'  => $m->image_path,
                    'created_at'  => \Carbon\Carbon::parse($m->created_at)->format('M d, g:i A'),
                    'is_admin'    => $m->sender_role === 'admin',
                    'name'        => $m->sender_role === 'admin'
                                     ? (config('app.name','Cake Shop').' Baker')
                                     : ($order->guest_name ?? 'You'),
                ];
            });

        // Mark admin messages as read when customer views
        DB::table('messages')
            ->where('order_id', $order->id)
            ->where('sender_role', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['messages' => $messages]);
    }

    /** Send a message from guest */
    public function send(Request $request, string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);

        $message = trim($request->input('message', ''));
        $imgPath = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');
            $ext  = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['jpg','jpeg','png','webp','gif']) && $file->getSize() <= 5*1024*1024) {
                $imgPath = $this->uploadFile($file, 'uploads/messages');
            }
        }

        if (!$message && !$imgPath)
            return response()->json(['ok' => false, 'error' => 'Message cannot be empty.'], 422);

        DB::table('messages')->insert([
            'order_id'    => $order->id,
            'sender_role' => 'customer',
            'sender_id'   => null,
            'message'     => $message ?: null,
            'image_path'  => $imgPath,
            'is_read' => false,
            'created_at'  => now(),
        ]);

        // Notify admin
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'Message from ' . ($order->guest_name ?? 'Customer'),
            'message'          => ($order->guest_name ?? 'Customer') . ' sent a message on Order #' . $order->id,
            'is_read' => false,
            'created_at'       => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
