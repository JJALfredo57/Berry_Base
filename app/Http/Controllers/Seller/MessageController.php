<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Services\MobileNotificationService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    private function findShopOrder(object $shop, string $orderRef): ?object
    {
        $ref = trim($orderRef);

        return DB::table('orders')
            ->where('shop_id', $shop->id)
            ->where(function ($query) use ($ref) {
                $query->where('id', $ref)
                    ->orWhereRaw('LOWER(track_code) = ?', [strtolower($ref)]);
            })
            ->first();
    }

    private function insertSellerMessageRows(string $orderId, ?string $senderId, string $text, array $paths, ?string $imgPath): int
    {
        try {
            return DB::table('messages')->insertGetId([
                'order_id'    => $orderId,
                'sender_role' => 'seller',
                'sender_id'   => $senderId,
                'message'     => $text ?: null,
                'image_path'  => $imgPath,
                'is_read'     => false,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            if (count($paths) <= 1 || strlen((string) $imgPath) <= 240) {
                throw $e;
            }

            Log::warning('Seller message image payload split after insert failed', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            $firstId = 0;
            foreach ($paths as $index => $path) {
                $id = DB::table('messages')->insertGetId([
                    'order_id'    => $orderId,
                    'sender_role' => 'seller',
                    'sender_id'   => $senderId,
                    'message'     => $index === 0 ? ($text ?: null) : null,
                    'image_path'  => $path,
                    'is_read'     => false,
                    'created_at'  => now(),
                ]);
                if ($index === 0) $firstId = $id;
            }

            return $firstId;
        }
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
        $baseOrder = $this->findShopOrder($shop, $orderId);
        if (!$baseOrder) return redirect()->route('seller.messages');

        $order = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.id', $baseOrder->id)
            ->where('o.shop_id', $shop->id)
            ->select('o.*', 'p.name as product_name', 'p.image_path as product_image_path',
                DB::raw("COALESCE(o.guest_name, o.fullname, u.fullname, 'Customer') as fullname"),
                DB::raw("COALESCE(u.username, 'Guest') as username"),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'))
            ->first();

        if (!$order) return redirect()->route('seller.messages');
        $orderId = $order->id;

        $orderAddons = DB::table('order_addons')
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $customOrder = DB::table('custom_orders')
            ->where('order_id', $orderId)
            ->first();

        DB::table('messages')
            ->where('order_id', $orderId)
            ->where('sender_role', 'customer')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = DB::table('messages')->where('order_id', $orderId)->orderBy('created_at')->get();
        return view('seller.thread', compact('order','messages','orderId','shop','orderAddons','customOrder'));
    }

    public function send(Request $request, string $orderId)
    {
        try {
            $shop    = $this->getShop();
            $order   = $this->findShopOrder($shop, $orderId);
            if (!$order) return response()->json(['ok'=>false,'error'=>'Order not found.'], 404);
            $orderId = $order->id;

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

            if (!$text && !$imgPath) return response()->json(['ok' => false, 'error' => 'Cannot send empty message.'], 422);

            $msgId = $this->insertSellerMessageRows(
                $order->id,
                session('user')['id'] ?? null,
                $text,
                $paths,
                $imgPath
            );

            try {
                CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Send Message', "Order #{$orderId}");
            } catch (\Throwable $e) {
                Log::warning('Seller message activity log failed', [
                    'order_id' => $orderId,
                    'message' => $e->getMessage(),
                ]);
            }

            try {
                app(MobileNotificationService::class)->notifyOrderCustomer(
                    $order,
                    'New Message from Seller',
                    $text !== '' ? mb_strimwidth($text, 0, 90, '...') : 'The seller sent image attachments.',
                    ['event' => 'message']
                );
            } catch (\Throwable $e) {
                Log::warning('Seller message push failed: ' . $e->getMessage());
            }

            return response()->json(['ok'=>true,'id'=>$msgId]);
        } catch (\Throwable $e) {
            Log::error('Seller message send failed', [
                'order_id' => $orderId,
                'message'  => $e->getMessage(),
                'trace'    => substr($e->getTraceAsString(), 0, 3000),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Message could not be sent. Please try again.',
            ], 500);
        }
    }

    public function markReadMsg(Request $request, string $id)
    {
        DB::table('messages')->where('id', $id)->where('sender_role', 'customer')->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    public function markOrderRead(Request $request, string $orderId)
    {
        $shop = $this->getShop();
        $order = $this->findShopOrder($shop, $orderId);
        if (!$order) return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);
        DB::table('messages')
            ->where('order_id', $order->id)
            ->where('sender_role', 'customer')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    public function threadOrderData(string $orderId)
    {
        $shop  = $this->getShop();
        $baseOrder = $this->findShopOrder($shop, $orderId);
        if (!$baseOrder) return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);

        $order = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->where('o.id', $baseOrder->id)
            ->where('o.shop_id', $shop->id)
            ->select(
                'o.id',
                'o.status',
                'o.track_code',
                'o.fulfillment_type',
                'o.schedule_date',
                'o.schedule_time',
                'o.payment_method',
                'o.payment_status',
                'o.total_price',
                'o.delivery_address',
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone')
            )
            ->first();

        if (!$order) return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);

        $schedule = $order->schedule_date
            ? \Carbon\Carbon::parse($order->schedule_date)->format('M d, Y') . ($order->schedule_time ? ' ' . $order->schedule_time : '')
            : 'Not set';

        return response()->json([
            'ok' => true,
            'order' => [
                'status'           => $order->status === 'Pickup' ? 'Ready for Pickup' : $order->status,
                'track_code'       => $order->track_code ?: $order->id,
                'schedule'         => $schedule,
                'fulfillment_type' => $order->fulfillment_type ?? 'Pickup',
                'payment_method'   => $order->payment_method ?? 'Not set',
                'payment_status'   => $order->payment_status ?? 'Unpaid',
                'phone'            => $order->phone ?? 'Not provided',
                'total_price'      => number_format((float)($order->total_price ?? 0), 2),
                'delivery_address' => $order->delivery_address ?? 'Not provided',
            ],
        ]);
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
        $unread = DB::table('messages as m')
            ->join('orders as o', 'o.id', '=', 'm.order_id')
            ->where('o.shop_id', $shop->id)
            ->where('m.sender_role', 'customer')
            ->where('m.is_read', false)
            ->count();
        return response()->json(['messages'=>$messages,'unread'=>$unread]);
    }

    public function popupSend(Request $request)
    {
        $shop    = $this->getShop();
        $orderId = $request->input('order_id');
        $order   = $this->findShopOrder($shop, (string) $orderId);
        if (!$order) return response()->json(['ok'=>false,'error'=>'Order not found.']);
        $orderId = $order->id;

        $text  = trim($request->input('message',''));
        $files = $request->file('images') ?? [];
        if (!is_array($files)) $files = [$files];

        $paths = [];
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (in_array($ext, ['jpg','jpeg','png','webp','gif']) && $file->getSize() <= 10 * 1024 * 1024) {
                    $url = $this->uploadFile($file, 'uploads/messages');
                    if ($url) $paths[] = $url;
                }
            }
        }
        $imgPath = count($paths) === 1 ? $paths[0] : (count($paths) > 1 ? json_encode($paths) : null);

        if (!$text && !$imgPath) return response()->json(['ok'=>false,'error'=>'Message cannot be empty.'], 422);

        $id = $this->insertSellerMessageRows(
            $order->id,
            session('user')['id'] ?? null,
            $text,
            $paths,
            $imgPath
        );
        try {
            app(MobileNotificationService::class)->notifyOrderCustomer(
                $order,
                'New Message from Seller',
                $text !== '' ? mb_strimwidth($text, 0, 90, '...') : 'The seller sent image attachments.',
                ['event' => 'message']
            );
        } catch (\Throwable $e) {
            Log::warning('Seller popup message push failed: ' . $e->getMessage());
        }
        return response()->json([
            'ok'           => true,
            'id'           => $id,
            'order_id'     => $orderId,
            'sender_role'  => 'seller',
            'message'      => $text,
            'image_path'   => $imgPath,
            'created_at'   => now(),
        ]);
    }
}
