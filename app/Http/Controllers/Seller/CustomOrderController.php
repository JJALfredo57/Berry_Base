<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomOrderController extends Controller
{
    use UploadsFiles;

    private function getShop(): object
    {
        $uid  = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->where('status', 'approved')->first();
        if (!$shop) abort(403);
        return $shop;
    }

    public function index(Request $request)
    {
        $shop   = $this->getShop();
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'All');

        $customOrders = DB::table('custom_orders as co')
            ->leftJoin('users as u', 'u.id', '=', 'co.user_id')
            ->leftJoin('orders as o', 'o.id', '=', 'co.order_id')
            ->where('o.shop_id', $shop->id)
            ->select('co.*',
                DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'),
                DB::raw("COALESCE(u.username, 'Guest') as username"),
                'o.status as order_status', 'o.total_price as order_total',
                'o.fulfillment_type', 'o.schedule_date', 'o.payment_method', 'o.payment_status', 'o.address')
            ->when($search, fn($q) => $q->where(fn($sq) => $sq
                ->where('co.cake_name', 'like', "%$search%")
                ->orWhereRaw("COALESCE(o.guest_name, u.fullname) like ?", ["%$search%"])
            ))
            ->when($status && $status !== 'All', fn($q) => $q->where('co.review_status', $status))
            ->orderByDesc('co.id')
            ->paginate(10)
            ->withQueryString();

        $orderIds   = collect($customOrders->items())->pluck('order_id')->filter()->values()->toArray();
        $orderAddons = [];
        if ($orderIds) {
            try {
                foreach (DB::table('order_addons')->whereIn('order_id', $orderIds)->get() as $a)
                    $orderAddons[$a->order_id][] = $a;
            } catch (\Exception $e) {}
        }
        $pendingCount = DB::table('custom_orders')
            ->join('orders', 'orders.id', '=', 'custom_orders.order_id')
            ->where('orders.shop_id', $shop->id)->where('custom_orders.review_status', 'pending')->count();
        return view('admin.custom_orders', compact('customOrders', 'orderAddons', 'pendingCount', 'search', 'status'));
    }

    public function approve(Request $request, string $id)
    {
        $shop  = $this->getShop();
        $price = (float)$request->input('admin_price', 0);
        $co    = DB::table('custom_orders as co')->join('orders as o', 'o.id', '=', 'co.order_id')
            ->where('co.id', $id)->where('o.shop_id', $shop->id)->select('co.*', 'o.guest_phone', 'o.guest_name', 'o.track_code')->first();
        if (!$co) return back()->with('err', 'Custom order not found.');
        if ($co->review_status !== 'pending') return back()->with('err', 'Already reviewed.');
        if ($price <= 0) return back()->with('err', 'Please enter a valid price.');

        DB::table('custom_orders')->where('id', $id)->update([
            'review_status'  => 'approved',
            'admin_price'    => $price,
            'admin_comment'  => trim($request->input('admin_comment', '')),
            'price_confirmed'=> 'pending',
            'reviewed_at'    => now(),
        ]);
        DB::table('orders')->where('id', $co->order_id)->update(['status' => 'Pending Price Confirmation', 'total_price' => $price]);

        // Notify customer
        if ($co->guest_phone) {
            $siteName = config('app.name', 'Cake Shop');
            $shopName = SmsHelper::getShopName($shop->id ?? null);
            $header   = SmsHelper::header($siteName, $shopName);
            $shopLine = $shopName ? "\nShop: {$shopName}" : '';
            SmsHelper::send($co->guest_phone,
                "{$header}\n"
                . "Hi {$co->guest_name}! Your custom cake order has been reviewed.\n\n"
                . "Order No.: #{$co->order_id}{$shopLine}\n"
                . "Final Price: PHP " . number_format($price, 2) . "\n\n"
                . "Your Tracking Code: {$co->track_code}\n"
                . "Log in to our website and use your tracking code to view and confirm your order.\n\n"
                . "This offer is subject to availability. Please confirm as soon as possible."
            );
        }
        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Approve Custom Order', "CO #{$id} — ₱{$price}");
        return back()->with('msg', 'Custom order approved with price ₱'.number_format($price,2).'.');
    }

    public function reject(Request $request, string $id)
    {
        $shop   = $this->getShop();
        $reason = trim($request->input('reason', ''));
        $co     = DB::table('custom_orders as co')->join('orders as o', 'o.id', '=', 'co.order_id')
            ->where('co.id', $id)->where('o.shop_id', $shop->id)->select('co.*', 'o.guest_phone', 'o.guest_name')->first();
        if (!$co) return back()->with('err', 'Not found.');
        if (!$reason) return back()->with('err', 'Please provide a reason.');
        DB::table('custom_orders')->where('id', $id)->update(['review_status' => 'rejected', 'admin_comment' => $reason]);
        DB::table('orders')->where('id', $co->order_id)->update(['status' => 'Cancelled']);
        if ($co->guest_phone) {
            $siteName = config('app.name', 'Cake Shop');
            $shopName = SmsHelper::getShopName($shop->id ?? null);
            $header   = SmsHelper::header($siteName, $shopName);
            $shopLine = $shopName ? "\nShop: {$shopName}" : '';
            SmsHelper::send($co->guest_phone,
                "{$header}\n"
                . "Hi {$co->guest_name}, we're sorry but we couldn't accommodate your custom order.\n\n"
                . "Order No.: #{$co->order_id}{$shopLine}\n"
                . "Reason: {$reason}\n\n"
                . "We apologize for the inconvenience. Feel free to reach out to us for alternative options."
            );
        }
        CakeshopHelper::logActivity(session('user')['id'], 'seller', 'Reject Custom Order', "CO #{$id}");
        return back()->with('msg', 'Custom order rejected.');
    }

    public function sendProgress(Request $request, string $id)
    {
        $shop = $this->getShop();
        $co   = DB::table('custom_orders as co')->join('orders as o', 'o.id', '=', 'co.order_id')
            ->where('co.id', $id)->where('o.shop_id', $shop->id)->select('co.*', 'o.guest_phone', 'o.guest_name', 'o.track_code')->first();
        if (!$co) return back()->with('err', 'Not found.');

        $photoPath = null;
        if ($request->hasFile('progress_image') && $request->file('progress_image')->isValid()) {
            $photoPath = $this->uploadFile($request->file('progress_image'), 'uploads/custom_orders');
        }
        $note = trim($request->input('progress_note', ''));
        if ($photoPath) DB::table('custom_orders')->where('id', $id)->update(['progress_image' => $photoPath]);
        // No SMS for progress update — customer can view updates on tracking page
        return back()->with('msg', 'Progress update sent.');
    }
}
