<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    use UploadsFiles;
    public function index()
    {
        $uid = session('user')['id'];
        $threads = DB::select("
            SELECT o.id order_id, o.status, p.name product_name,
                (SELECT message FROM messages m WHERE m.order_id=o.id ORDER BY m.created_at DESC LIMIT 1) last_message,
                (SELECT created_at FROM messages m WHERE m.order_id=o.id ORDER BY m.created_at DESC LIMIT 1) last_time,
                (SELECT COUNT(*) FROM messages m WHERE m.order_id=o.id AND m.sender_role='admin' AND m.is_read=false) unread_count
            FROM orders o
            JOIN products p ON p.id=o.product_id
            WHERE o.user_id=?
            AND EXISTS (SELECT 1 FROM messages m WHERE m.order_id=o.id)
            ORDER BY last_time DESC
        ", [$uid]);
        return view('customer.messages', compact('threads'));
    }

    public function thread(string $orderId)
    {
        $uid = session('user')['id'];
        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $orderId)->where('o.user_id', $uid)
            ->select('o.*', 'p.name as product_name', 'p.image_path')
            ->first();
        if (!$order) return redirect()->route('customer.messages');

        DB::table('messages')
            ->where('order_id', $orderId)
            ->where('sender_role', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = DB::table('messages')->where('order_id', $orderId)->orderBy('created_at')->get();
        return view('customer.thread', compact('order','messages','orderId'));
    }

    public function markReadMsg(Request $request, string $id)
    {
        $uid = session('user')['id'];
        DB::table('messages')
            ->where('id', $id)
            ->where('sender_role', 'admin')
            ->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    // Mark all unread admin messages for a specific order as read (called when customer opens conversation in bubble)
    public function markOrderRead(Request $request, string $orderId)
    {
        $uid = session('user')['id'];
        // Verify this order belongs to this customer
        $order = DB::table('orders')->where('id', $orderId)->where('user_id', $uid)->first();
        if (!$order) return response()->json(['ok' => false], 403);

        DB::table('messages')
            ->where('order_id', $orderId)
            ->where('sender_role', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    public function popupData(Request $request)
    {
        $uid  = session('user')['id'];
        $limit = (int)$request->input('limit', 30);

        // Get all messages for this customer across all orders — newest first
        // Get messages with order context
        $withOrder = DB::table('messages as m')
            ->join('orders as o', 'o.id', '=', 'm.order_id')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.user_id', $uid)
            ->select(
                'm.id', 'm.order_id', 'm.sender_role', 'm.message',
                'm.image_path', 'm.is_read', 'm.created_at',
                'p.name as product_name'
            )
            ->orderByDesc('m.created_at')
            ->limit($limit)
            ->get();

        // Get general messages (no order) for this user — only if user_id column exists
        $general = collect();
        try {
            $general = DB::table('messages as m')
                ->whereNull('m.order_id')
                ->where('m.sender_id', $uid)
                ->select(
                    'm.id', 'm.order_id', 'm.sender_role', 'm.message',
                    'm.image_path', 'm.is_read', 'm.created_at',
                    DB::raw("'General Inquiry' as product_name")
                )
                ->orderByDesc('m.created_at')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {}

        // Also get general admin replies to this user
        $adminGeneral = collect();
        try {
            $adminGeneral = DB::table('messages as m')
                ->whereNull('m.order_id')
                ->where('m.sender_role', 'admin')
                ->where(function($q) use ($uid) {
                    $q->where('m.user_id', $uid);
                })
                ->select(
                    'm.id', 'm.order_id', 'm.sender_role', 'm.message',
                    'm.image_path', 'm.is_read', 'm.created_at',
                    DB::raw("'General Inquiry' as product_name")
                )
                ->orderByDesc('m.created_at')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {}

        $messages = $withOrder
            ->concat($general)
            ->concat($adminGeneral)
            ->sortBy('created_at')
            ->take($limit)
            ->values();

        // NOTE: Do NOT auto-mark as read here — only mark read when user explicitly opens a specific message

        $user = DB::table('users')->where('id', $uid)->select('fullname','profile_photo')->first();

        return response()->json([
            'messages'   => $messages,
            'user'       => $user,
            'role'       => 'customer',
        ]);
    }

public function popupSend(Request $request)
    {
        $uid     = session('user')['id'];
        $text    = trim($request->input('message', ''));
        $orderId = (int)$request->input('order_id', 0);
        $exts    = ['jpg','jpeg','png','webp','gif'];

        // Handle multiple images
        $imgPath = null;
        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                if ($file->isValid() && $file->getSize() <= 5*1024*1024
                    && in_array(strtolower($file->getClientOriginalExtension()), $exts)) {
                    $paths[] = $this->uploadFile($file, 'uploads/messages');
                }
            }
            if (count($paths) === 1) $imgPath = $paths[0];
            elseif (count($paths) > 1)  $imgPath = json_encode($paths);
        }

        if ($text === '' && !$imgPath) return response()->json(['error' => 'Empty message.'], 422);

        $order = null;
        if ($orderId) {
            $order   = DB::table('orders')->where('id', $orderId)->where('user_id', $uid)->first();
            $orderId = $order ? $order->id : null;
        } else {
            $order   = DB::table('orders')->where('user_id', $uid)->orderByDesc('id')->first();
            $orderId = $order ? $order->id : null;
        }

        $user = DB::table('users')->where('id', $uid)->first();

        $id = DB::table('messages')->insertGetId([
            'order_id'    => $orderId,
            'user_id'     => $uid,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => $text,
            'image_path'  => $imgPath,
            'is_read' => false,
            'created_at'  => now(),
        ]);

        $notifMsg = $orderId
            ? "New message from customer (Order #{$orderId})."
            : "New general inquiry from {$user->fullname}.";

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'New Message',
            'message'          => $notifMsg,
            'is_read' => false,
            'created_at'       => now(),
        ]);

        $product = $order ? DB::table('products')->where('id', $order->product_id)->value('name') : null;
        return response()->json([
            'id'           => $id,
                        'sender_role'  => 'customer',
            'message'      => $text,
            'image_path'   => $imgPath,
            'created_at'   => now(),
            'product_name' => $product ?? 'General Inquiry',
        ]);
    }

    public function send(Request $request, string $orderId)
    {
        $uid   = session('user')['id'];
        $text  = trim($request->input('message', ''));
        $exts  = ['jpg','jpeg','png','webp','gif'];
        $img   = '';

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                if ($file->isValid() && $file->getSize() <= 5*1024*1024
                    && in_array(strtolower($file->getClientOriginalExtension()), $exts)) {
                    $paths[] = $this->uploadFile($file, 'uploads/messages');
                }
            }
            if (count($paths) === 1) $img = $paths[0];
            elseif (count($paths) > 1)  $img = json_encode($paths);
        }

        if ($text === '' && $img === '') {
            return redirect()->route('customer.messages.thread', $orderId)->with('warn', 'Type a message or attach an image.');
        }

        DB::table('messages')->insert([
            'order_id'    => $orderId,
            'user_id'     => $uid,
            'sender_role' => 'customer',
            'sender_id'   => $uid,
            'message'     => $text,
            'image_path'  => $img,
            'is_read' => false,
            'created_at'  => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => 'New Message',
            'message'          => "New message from customer (Order #{$orderId}).",
            'is_read' => false,
            'created_at'       => now(),
        ]);

        CakeshopHelper::logActivity($uid, 'customer', 'Send Message', "Order #{$orderId}");
        return redirect()->route('customer.messages.thread', $orderId);
    }
}
