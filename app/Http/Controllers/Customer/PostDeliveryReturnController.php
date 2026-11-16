<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\OrderRefundService;
use App\Services\PostDeliveryReturnService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostDeliveryReturnController extends Controller
{
    use UploadsFiles;

    public function __construct(private PostDeliveryReturnService $returns, private OrderRefundService $refunds) {}

    public function create(string $orderId)
    {
        $order = DB::table('orders')->where('id', $orderId)->where('user_id', session('user')['id'])->first();
        if (!$order) abort(404);
        [$eligible, $message] = $this->returns->eligibility($order);
        if (!$eligible) return redirect()->route('customer.orders')->with('error', $message);
        $case = DB::table('post_delivery_return_cases')->where('order_id', $orderId)->whereIn('status', ['submitted', 'seller_responded', 'approved'])->first();
        if ($case) return redirect()->route('customer.orders')->with('error', 'A return/refund case is already open for this order.');
        return view('customer.return_request', compact('order'));
    }

    public function store(Request $request, string $orderId)
    {
        $order = DB::table('orders')->where('id', $orderId)->where('user_id', session('user')['id'])->first();
        if (!$order) abort(404);
        $data = $request->validate([
            'reason' => ['required', 'in:' . implode(',', PostDeliveryReturnService::REASONS)],
            'description' => ['required', 'string', 'min:20', 'max:1500'],
            'refund_gcash_name' => ['required', 'string', 'max:160'],
            'refund_gcash_number' => ['required', 'string', 'max:80'],
            'photos' => ['required', 'array', 'min:2', 'max:6'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video' => ['required', 'file', 'mimes:mp4,mov,webm', 'max:30720'],
            'refund_confirmation' => ['accepted'],
        ]);
        if (!$this->refunds->validateGcashAccountName($data['refund_gcash_name']) || !$this->refunds->validateGcashNumber($data['refund_gcash_number'])) return back()->withInput()->with('error', 'Enter the exact GCash account name and a valid Philippine mobile number.');
        $evidence = [];
        foreach ($request->file('photos', []) as $photo) {
            $path = $this->uploadFile($photo, 'uploads/post_delivery_returns');
            if (!$path) return back()->withInput()->with('error', 'A photo could not be stored. Please try again.');
            $evidence[] = ['kind' => 'photo', 'path' => $path, 'sha256' => hash_file('sha256', $photo->getRealPath()), 'mime_type' => $photo->getMimeType(), 'size_bytes' => $photo->getSize()];
        }
        $video = $request->file('video');
        $videoPath = $this->uploadFile($video, 'uploads/post_delivery_returns');
        if (!$videoPath) return back()->withInput()->with('error', 'The video could not be stored. Please try again.');
        $evidence[] = ['kind' => 'video', 'path' => $videoPath, 'sha256' => hash_file('sha256', $video->getRealPath()), 'mime_type' => $video->getMimeType(), 'size_bytes' => $video->getSize()];
        try {
            $this->returns->submit($order, (string) session('user')['id'], ['reason' => $data['reason'], 'description' => trim($data['description']), 'refund_gcash_name' => trim($data['refund_gcash_name']), 'refund_gcash_number' => $this->refunds->normalizeGcashNumber($data['refund_gcash_number'])], $evidence);
        } catch (\DomainException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return redirect()->route('customer.orders')->with('msg', 'Your delivery issue was submitted. The affected seller payout is on hold while the case is reviewed.');
    }
}
