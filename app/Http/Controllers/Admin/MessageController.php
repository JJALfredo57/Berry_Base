<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index()
    {
        $threads = DB::select("
            SELECT o.id order_id, o.status,
                COALESCE(o.guest_name, u.fullname) as fullname,
                IFNULL(u.username, 'Guest') as username,
                (SELECT message FROM messages m WHERE m.order_id=o.id ORDER BY m.created_at DESC LIMIT 1) last_message,
                (SELECT created_at FROM messages m WHERE m.order_id=o.id ORDER BY m.created_at DESC LIMIT 1) last_time,
                (SELECT COUNT(*) FROM messages m WHERE m.order_id=o.id AND m.sender_role='customer' AND m.is_read=0) unread_count
            FROM orders o
            LEFT JOIN users u ON u.id=o.user_id
            WHERE EXISTS (SELECT 1 FROM messages m WHERE m.order_id=o.id)
            ORDER BY last_time DESC
        ");
        return view('admin.messages', compact('threads'));
    }

    public function thread(string $orderId)
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $orderId)
            ->select('o.*', 'p.name as product_name', 'p.image_path',
                DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                DB::raw('IFNULL(u.username, "Guest") as username'),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'))
            ->first();

        if (!$order) return redirect()->route('admin.messages.index');

        DB::table('messages')
            ->where('order_id', $orderId)
            ->where('sender_role', 'customer')
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        $messages = DB::table('messages')->where('order_id', $orderId)->orderBy('created_at')->get();
        return view('admin.thread', compact('order','messages','orderId'));
    }

    public function markReadMsg(Request $request, string $id)
    {
        DB::table('messages')
            ->where('id', $id)
            ->where('sender_role', 'customer')
            ->update(['is_read' => 1]);
        return response()->json(['ok' => true]);
    }

    // Mark ALL unread messages of a specific order/customer as read (called when admin opens a conversation in bubble)
    public function markOrderRead(Request $request, string $orderId)
    {
        DB::table('messages')
            ->where('order_id', $orderId)
            ->where('sender_role', 'customer')
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
        return response()->json(['ok' => true]);
    }

    public function popupData(Request $request)
    {
        $limit = (int)$request->input('limit', 40);

        // Get recent messages with order + customer info
        $messages = DB::table('messages as m')
            ->join('orders as o', 'o.id', '=', 'm.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->select(
                'm.id',
                'm.order_id',
                'm.sender_role',
                'm.message',
                'm.image_path',
                'm.is_read',
                'm.created_at',
                'p.name as product_name',
                DB::raw('COALESCE(o.guest_name, u.fullname, "Customer") as customer_name'),
                DB::raw('COALESCE(u.profile_photo, NULL) as customer_photo'),
                DB::raw('COALESCE(u.id, NULL) as customer_user_id'),
                'o.guest_name',
                'o.guest_phone'
            )
            ->orderByDesc('m.created_at')
            ->limit($limit)
            ->get();

        $admin = DB::table('users')
            ->where('id', session('user')['id'])
            ->select('fullname', 'profile_photo')
            ->first();

        return response()->json([
            'messages' => $messages->values(),
            'user'     => $admin,
            'role'     => 'admin',
        ]);
    }

public function popupSend(Request $request)
    {
        $user    = session('user');
        $text    = trim($request->input('message', ''));
        $orderId = (int)$request->input('order_id', 0);

        if (!$orderId) {
            $order = DB::table('orders')->orderByDesc('id')->first();
            if (!$order) return response()->json(['error' => 'No orders found.'], 422);
            $orderId = $order->id;
        }

        // Handle multiple images
        $imgPath = null;
        $exts    = ['jpg','jpeg','png','webp','gif'];
        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                if ($file->isValid() && $file->getSize() <= 5*1024*1024
                    && in_array(strtolower($file->getClientOriginalExtension()), $exts)) {
                    $filename = date('YmdHis').'_'.bin2hex(random_bytes(6)).'.'.$file->getClientOriginalExtension();
                    $file->storeAs('uploads/messages', $filename, 'public');
                    $paths[] = '/storage/uploads/messages/'.$filename;
                }
            }
            if (count($paths) === 1) $imgPath = $paths[0];
            elseif (count($paths) > 1)  $imgPath = json_encode($paths);
        }

        if ($text === '' && !$imgPath) return response()->json(['error' => 'Empty message.'], 422);

        $order  = $orderId ? DB::table('orders')->where('id', $orderId)->first() : null;
        $custId = $request->input('user_id', $order?->user_id ?? null);

        $id = DB::table('messages')->insertGetId([
            'order_id'    => $orderId ?: null,
            'user_id'     => $custId,
            'sender_role' => 'admin',
            'sender_id'   => $user['id'],
            'message'     => $text,
            'image_path'  => $imgPath,
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        if ($custId) {
            DB::table('notifications')->insert([
                'receiver_role'    => 'customer',
                'receiver_user_id' => $custId,
                'title'            => 'New Message',
                'message'          => $orderId ? "New message from Admin for Order #{$orderId}" : "New message from Admin.",
                'is_read'          => 0,
                'created_at'       => now(),
            ]);
        }

        $product = $order ? DB::table('products')->where('id', $order->product_id)->value('name') : null;
        return response()->json([
            'id'           => $id,
                        'sender_role'  => 'admin',
            'message'      => $text,
            'image_path'   => $imgPath,
            'created_at'   => now(),
            'product_name' => $product ?? 'General Inquiry',
        ]);
    }

    public function send(Request $request, string $orderId)
    {
        $user  = session('user');
        $text  = trim($request->input('message', ''));
        $exts  = ['jpg','jpeg','png','webp','gif'];
        $img   = '';

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                if ($file->isValid() && $file->getSize() <= 5*1024*1024
                    && in_array(strtolower($file->getClientOriginalExtension()), $exts)) {
                    $filename = date('YmdHis').'_'.bin2hex(random_bytes(6)).'.'.$file->getClientOriginalExtension();
                    $file->storeAs('uploads/messages', $filename, 'public');
                    $paths[] = '/storage/uploads/messages/'.$filename;
                }
            }
            if (count($paths) === 1) $img = $paths[0];
            elseif (count($paths) > 1)  $img = json_encode($paths);
        }

        if ($text === '' && $img === '') {
            return redirect()->route('admin.messages.thread', $orderId)->with('warn', 'Type a message or attach an image.');
        }

        DB::table('messages')->insert([
            'order_id'    => $orderId,
            'sender_role' => 'admin',
            'sender_id'   => $user['id'],
            'message'     => $text,
            'image_path'  => $img,
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        $order = DB::table('orders')->where('id', $orderId)->first();

        // For guest orders - no SMS, they check tracking page
        // For user orders - send app notification
        if ($order && $order->user_id) {
            DB::table('notifications')->insert([
                'receiver_role'    => 'customer',
                'receiver_user_id' => $order->user_id,
                'title'            => 'New Message',
                'message'          => "New message from Admin for Order #{$orderId}",
                'is_read'          => 0,
                'created_at'       => now(),
            ]);
        }

        CakeshopHelper::logActivity($user['id'], $user['role'], 'Reply Message', "Order #{$orderId}");
        return redirect()->route('admin.messages.thread', $orderId);
    }
}
