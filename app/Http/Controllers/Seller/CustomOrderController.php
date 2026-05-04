<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Helpers\SmsHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        try {
            $viewData = $this->buildIndex($request);
            $html = view('seller.custom_orders', $viewData)->render();
            return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e; // let abort(403) / abort(404) pass through normally
        } catch (\Throwable $e) {
            Log::error('Seller CustomOrders 500: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 3000),
            ]);
            $msg = htmlspecialchars($e->getMessage());
            $file = htmlspecialchars(basename($e->getFile()) . ':' . $e->getLine());
            return response("<!DOCTYPE html><html><head><meta charset='utf-8'><title>Custom Orders Error</title>
<style>body{font-family:sans-serif;padding:40px;background:#fff8f8}.box{background:#fff;border:1.5px solid #f87171;border-radius:12px;padding:32px;max-width:720px;margin:auto}.title{color:#b91c1c;font-size:1.2rem;font-weight:700;margin-bottom:12px}.msg{background:#fef2f2;border-radius:8px;padding:12px 16px;font-family:monospace;font-size:.9rem;word-break:break-all;color:#7f1d1d}.file{color:#6b7280;font-size:.8rem;margin-top:8px}.back{display:inline-block;margin-top:20px;padding:8px 20px;background:#e11d48;color:#fff;text-decoration:none;border-radius:8px;font-weight:600}</style>
</head><body><div class='box'>
<div class='title'>⚠ Custom Orders failed to load</div>
<div class='msg'>{$msg}</div>
<div class='file'>{$file}</div>
<a class='back' href='/seller/dashboard'>← Back to Dashboard</a>
</div></body></html>", 200)->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    private function buildIndex(Request $request): array
    {
        $shop   = $this->getShop();
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'All');

        $hasCakeName = Schema::hasColumn('custom_orders', 'cake_name');

        $customOrders = DB::table('custom_orders as co')
            ->leftJoin('users as u', 'u.id', '=', 'co.user_id')
            ->leftJoin('orders as o', 'o.id', '=', 'co.order_id')
            ->where('co.shop_id', $shop->id)
            ->select('co.*',
                DB::raw('COALESCE(o.guest_name, co.guest_name, u.fullname) as fullname'),
                DB::raw('COALESCE(o.guest_phone, co.guest_phone, u.phone) as phone'),
                DB::raw("COALESCE(u.username, 'Guest') as username"),
                'u.profile_photo',
                'o.status as order_status', 'o.total_price as order_total',
                'o.fulfillment_type', 'o.schedule_date', 'o.payment_method', 'o.payment_status',
                'o.delivery_address as address')
            ->when($search, fn($q) => $q->where(fn($sq) => $hasCakeName
                ? $sq->where('co.cake_name', 'like', "%$search%")
                      ->orWhereRaw("COALESCE(o.guest_name, co.guest_name, u.fullname) like ?", ["%$search%"])
                : $sq->whereRaw("COALESCE(o.guest_name, co.guest_name, u.fullname) like ?", ["%$search%"])
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
        $pendingCount = 0;
        try {
            $pendingCount = DB::table('custom_orders')
                ->where('shop_id', $shop->id)->where('review_status', 'pending')->count();
        } catch (\Exception $e) {}

        return compact('customOrders', 'orderAddons', 'pendingCount', 'search', 'status');
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
        if ($photoPath) DB::table('custom_orders')->where('id', $id)->update(['progress_image' => $photoPath]);
        // No SMS for progress update — customer can view updates on tracking page
        return back()->with('msg', 'Progress update sent.');
    }
}
