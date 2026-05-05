<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    use UploadsFiles;
    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403, 'Shop not found or not approved.');
        return $shop;
    }

    public function index()
    {
        $shop = $this->getShop();
        $threads = DB::select("
            SELECT o.id order_id, o.status,
                COALESCE(o.guest_name, u.fullname) as fullname,
                COALESCE(u.username, 'Guest') as username,
                (SELECT message FROM messages m WHERE m.order_id=o.id ORDER BY m.created_at DESC LIMIT 1) last_message,
                (SELECT created_at FROM messages m WHERE m.order_id=o.id ORDER BY m.created_at DESC LIMIT 1) last_time,
                (SELECT COUNT(*) FROM messages m WHERE m.order_id=o.id AND m.sender_role='customer' AND m.is_read=false) unread_count
            FROM orders o
            LEFT JOIN users u ON u.id=o.user_id
            WHERE o.shop_id=? AND EXISTS (SELECT 1 FROM messages m WHERE m.order_id=o.id)
            ORDER BY last_time DESC
        ", [$shop->id]);
        return view('seller.messages', compact('threads','shop'));
    }

    public function thread(string $orderId)
    {
        $shop  = $this->getShop();
        $order = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $orderId)
            ->where('o.shop_id', $shop->id)
            ->select('o.*', 'p.name as product_name', 'p.image_path',
                DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                DB::raw("COALESCE(u.username, 'Guest') as username"),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'))
            ->first();

        if (!$order) return redirect()->route('seller.messages');

        DB::table('messages')
            ->where('order_id', $orderId)
            ->where('sender_role', 'customer')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = DB::table('messages')->where('order_id', $orderId)->orderBy('created_at')->get();
        return view('seller.thread', compact('order','messages','orderId','shop'));
    }

    public function send(Request $request, string $orderId)
    {
        $shop    = $this->getShop();
        $order   = DB::table('orders')->where('id', $orderId)->where('shop_id', $shop->id)->first();
        if (!$order) return response()->json(['ok'=>false,'error'=>'Order not found.']);

        $text  = trim($request->input('message', ''));
        $files = $request->file('images') ?? [];
        if (!is_array($files)) $files = [$files];

        $paths = [];
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $url = $this->uploadFile($file, 'uploads/messages');
                if ($url) $paths[] = $url;
            }
        }
        $imgPath = count($paths) === 1 ? $paths[0] : (count($paths) > 1 ? json_encode($paths) : null);

        if (!$text && !$imgPath) return response()->json(['ok' => false, 'error' => 'Cannot send empty message.']);

        $msgId = DB::table('messages')->insertGetId([
            'order_id'    => $orderId,
            'sender_role' => 'seller',
            'sender_id'   => session('user')['id'],
            'message'     => $text ?: null,
            'image_path'  => $imgPath,
            'is_read'     => false,
            'created_at'  => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Send Message', "Order #{$orderId}");
        return response()->json(['ok'=>true,'id'=>$msgId]);
    }

    public function markReadMsg(Request $request, string $id)
    {
        DB::table('messages')->where('id', $id)->where('sender_role', 'customer')->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    public function markOrderRead(Request $request, string $orderId)
    {
        $shop = $this->getShop();
        DB::table('messages')
            ->where('order_id', $orderId)
            ->where('sender_role', 'customer')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    public function popupData(Request $request)
    {
        $shop  = $this->getShop();
        $limit = (int)$request->input('limit', 40);
        $messages = DB::table('messages as m')
            ->join('orders as o', 'o.id', '=', 'm.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.shop_id', $shop->id)
            ->select('m.*','o.track_code',
                DB::raw('COALESCE(o.guest_name, (SELECT fullname FROM users WHERE id=o.user_id)) as customer_name'),
                'p.name as product_name')
            ->orderByDesc('m.created_at')
            ->limit($limit)->get();
        $unread = $messages->where('sender_role','customer')->where('is_read', false)->count();
        return response()->json(['messages'=>$messages,'unread'=>$unread]);
    }

    public function popupSend(Request $request)
    {
        $shop    = $this->getShop();
        $orderId = $request->input('order_id');
        $order   = DB::table('orders')->where('id',$orderId)->where('shop_id',$shop->id)->first();
        if (!$order) return response()->json(['ok'=>false,'error'=>'Order not found.']);

        $text = trim($request->input('message',''));
        if (!$text) return response()->json(['ok'=>false,'error'=>'Message cannot be empty.']);

        DB::table('messages')->insert([
            'order_id'    => $orderId,
            'sender_role' => 'seller',
            'sender_id'   => session('user')['id'],
            'message'     => $text,
            'is_read' => false,
            'created_at'  => now(),
        ]);
        return response()->json(['ok'=>true]);
    }
}
