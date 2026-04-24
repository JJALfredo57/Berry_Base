<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request, string $trackCode)
    {
        $order = DB::table('orders')->where('track_code', strtoupper($trackCode))->first();
        if (!$order) return back()->with('err', 'Order not found.');
        if ($order->status !== 'Delivered') return back()->with('err', 'You can only review delivered orders.');

        // Prevent duplicate review
        if (DB::table('order_reviews')->where('order_id', $order->id)->exists())
            return back()->with('err', 'You already submitted a review for this order.');

        $rating  = max(1, min(5, (int)$request->input('rating', 5)));
        $review  = trim($request->input('review', ''));
        $imgPath = null;

        if ($request->hasFile('review_image') && $request->file('review_image')->isValid()) {
            $file = $request->file('review_image');
            $ext  = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $file->getSize() <= 5*1024*1024) {
                $filename = date('YmdHis').'_'.bin2hex(random_bytes(6)).'.'.$ext;
                $file->storeAs('uploads/reviews', $filename, 'public');
                $imgPath = '/storage/uploads/reviews/'.$filename;
            }
        }

        $riderRating = null;
        if (!empty($order->rider_id) && $request->input('rider_rating')) {
            $riderRating = max(1, min(5, (int)$request->input('rider_rating')));
        }

        DB::table('order_reviews')->insert([
            'order_id'     => $order->id,
            'user_id'      => null,
            'guest_name'   => $order->guest_name,
            'rating'       => $rating,
            'rider_rating' => $riderRating,
            'review'       => $review ?: null,
            'image_path'   => $imgPath,
            'created_at'   => now(),
        ]);

        // Mark as reviewed in notifications
        DB::table('notifications')->insert([
            'receiver_role'    => 'admin',
            'receiver_user_id' => null,
            'title'            => '⭐ New Review from '.($order->guest_name ?? 'Customer'),
            'message'          => ($order->guest_name ?? 'Customer').' left a '.str_repeat('★',$rating).' review for Order #'.$order->id,
            'is_read'          => 0,
            'created_at'       => now(),
        ]);

        return back()->with('msg', 'Thank you for your review! ⭐');
    }
}
