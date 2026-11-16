<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\OrderRefundService;
use App\Services\PostDeliveryReturnService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostDeliveryReturnController extends Controller
{
    use UploadsFiles;

    public function __construct(private PostDeliveryReturnService $returns, private OrderRefundService $refunds) {}

    public function create(string $orderId)
    {
        $order = DB::table('orders')->where('id', $orderId)->where('user_id', session('user')['id'])->first();
        if (!$order) abort(404);
        $isPickupInspection = ($order->fulfillment_type ?? '') === 'Pickup' && ($order->status ?? '') === 'Pickup';
        [$eligible, $message] = $this->returns->eligible($order);
        if (!$eligible) return redirect()->route('customer.orders')->with('error', $message);
        $case = DB::table('post_delivery_return_cases')->where('order_id', $orderId)->first();
        if ($case) return redirect()->route('customer.orders')->with('error', 'A return/refund case is already open for this order.');
        $allowedReasons = $this->allowedReasons($order);
        return view('customer.return_request', compact('order', 'isPickupInspection', 'allowedReasons'));
    }

    public function store(Request $request, string $orderId)
    {
        $order = DB::table('orders')->where('id', $orderId)->where('user_id', session('user')['id'])->first();
        if (!$order) abort(404);
        $isPickupInspection = ($order->fulfillment_type ?? '') === 'Pickup' && ($order->status ?? '') === 'Pickup';
        [$eligible, $message] = $this->returns->eligible($order);
        if (!$eligible) return back()->with('error', $message);
        $allowedReasons = $this->allowedReasons($order);
        $rules = [
            'reason' => ['required', 'in:' . implode(',', $allowedReasons)],
            'other_reason' => ['nullable', 'string', 'min:10', 'max:500', 'required_if:reason,other'],
            'description' => ['required', 'string', 'min:20', 'max:1500'],
            'refund_gcash_name' => ['required', 'string', 'max:160'],
            'refund_gcash_number' => ['required', 'string', 'max:80'],
            'photos' => ['required', 'array', 'min:2', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video' => ['required', 'file', 'mimes:mp4,mov,webm', 'max:30720'],
            'refund_confirmation' => ['accepted'],
        ]; $data = $request->validate($rules);
        if (!$this->refunds->validateGcashAccountName($data['refund_gcash_name']) || !$this->refunds->validateGcashNumber($data['refund_gcash_number'])) return back()->withInput()->with('error', 'Enter the exact GCash account name and a valid Philippine mobile number.');
        $evidence = [];
        foreach ($request->file('photos', []) ?? [] as $photo) {
            $path = $this->uploadFile($photo, 'uploads/post_delivery_returns');
            if (!$path) return back()->withInput()->with('error', 'A photo could not be stored. Please try again.');
            $evidence[] = ['kind' => 'photo', 'path' => $path, 'sha256' => hash_file('sha256', $photo->getRealPath()), 'mime_type' => $photo->getMimeType(), 'size_bytes' => $photo->getSize()];
        }
        if ($video = $request->file('video')) { $videoPath = $this->uploadFile($video, 'uploads/post_delivery_returns'); if (!$videoPath) return back()->withInput()->with('error', 'The video could not be stored. Please try again.'); $evidence[] = ['kind' => 'video', 'path' => $videoPath, 'sha256' => hash_file('sha256', $video->getRealPath()), 'mime_type' => $video->getMimeType(), 'size_bytes' => $video->getSize()]; }
        try {
            $description = trim($data['description']) . ($data['reason'] === 'other' ? "\nOther verified issue: " . trim($data['other_reason']) : '');
            $case = $this->returns->submit($order, (string) session('user')['id'], ['reason' => $data['reason'], 'description' => $description, 'refund_gcash_name' => trim($data['refund_gcash_name']), 'refund_gcash_number' => $this->refunds->normalizeGcashNumber($data['refund_gcash_number'])], $evidence);
            $this->notifySeller($order, $case);
        } catch (\DomainException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        $message = $isPickupInspection
            ? 'Your pickup return request was submitted. Please wait while the seller reviews it at the pickup counter.'
            : 'Your delivery return request was submitted. The rider is waiting for the seller decision.';
        return redirect()->route('customer.orders')->with('msg', $message);
    }

    public function acceptPickup(string $orderId)
    { $order=DB::table('orders')->where('id',$orderId)->where('user_id',session('user')['id'])->first(); if(!$order||($order->fulfillment_type??'')!=='Pickup'||($order->status??'')!=='Pickup')return back()->with('error','This pickup is not ready for inspection.'); $case=DB::table('post_delivery_return_cases')->where('order_id',$orderId)->first(); if($case&&$case->status!=='seller_rejected')return back()->with('error','You cannot accept this pickup while a return request is active.'); DB::table('orders')->where('id',$orderId)->whereNull('pickup_inspected_at')->update(['pickup_inspected_at'=>now(),'pickup_inspected_by_user_id'=>(string)session('user')['id'],'updated_at'=>now()]); DB::table('order_tracking')->insert(['order_id'=>$orderId,'status'=>'Pickup','notes'=>'Customer inspected and accepted cake at pickup counter.','created_at'=>now()]); return back()->with('msg','Cake inspection accepted. The seller may now complete your pickup.'); }

    private function allowedReasons(object $order): array
    { $reasons=['wrong_item','damaged','quality_safety','other']; $isCustom=DB::table('custom_orders')->where('order_id',$order->id)->exists(); if($isCustom)$reasons[]='not_as_approved'; if(($order->fulfillment_type??'')==='Delivery')$reasons[]='delivery_damage'; if(DB::table('order_addons')->where('order_id',$order->id)->exists() || (Schema::hasTable('order_items') && DB::table('order_items')->where('order_id',$order->id)->count()>1))$reasons[]='missing_item'; return $reasons; }

    private function notifySeller(object $order, object $case): void
    {
        $sellerId = (string) ($case->seller_user_id ?? '');
        if ($sellerId === '') return;
        $title = 'Return Request Needs Review';
        $message = ($order->fulfillment_type ?? '') === 'Pickup'
            ? "Customer submitted a pickup return request for Order #{$order->id}."
            : "Customer submitted a delivery return request for Order #{$order->id}. The rider is waiting for your decision.";
        if (Schema::hasTable('notifications')) DB::table('notifications')->insert(['receiver_role'=>'seller','receiver_user_id'=>$sellerId,'title'=>$title,'message'=>$message,'is_read'=>false,'created_at'=>now()]);
        try { app(\App\Services\MobileNotificationService::class)->notifyOrderSeller($order, $title, $message, ['event'=>'return_request_submitted','return_case_id'=>(string)$case->id]); } catch (\Throwable) {}
    }
}
