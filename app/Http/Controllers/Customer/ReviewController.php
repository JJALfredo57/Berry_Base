<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request, string $orderId)
    {
        $uid    = session('user')['id'];
        $rating = (int) $request->input('rating', 0);
        $review = trim($request->input('review', ''));

        if ($rating < 1 || $rating > 5) return back()->with('error', 'Please select a rating from 1 to 5.');

        $order = DB::table('orders')
            ->where('id', $orderId)
            ->where('user_id', $uid)
            ->whereIn('status', ['Delivered', 'Picked Up'])
            ->first();

        if (!$order) return back()->with('error', 'Order not found or not yet delivered.');

        $existing = DB::table('order_reviews')->where('order_id', $orderId)->where('user_id', $uid)->first();
        if ($existing) return back()->with('error', 'You have already reviewed this order.');

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('review_image')) {
            $file = $request->file('review_image');
            if ($file->isValid() && $file->getSize() <= 5 * 1024 * 1024) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $file->storeAs('uploads/reviews', $filename, 'public');
                    $imagePath = '/storage/uploads/reviews/' . $filename;
                }
            }
        }

        $riderRating = null;
        if ($order->rider_id && $request->input('rider_rating')) {
            $riderRating = max(1, min(5, (int)$request->input('rider_rating')));
        }

        DB::table('order_reviews')->insert([
            'order_id'     => $orderId,
            'user_id'      => $uid,
            'rating'       => $rating,
            'rider_rating' => $riderRating,
            'review'       => $review ?: null,
            'image_path'   => $imagePath,
            'created_at'   => now(),
        ]);

        return back()->with('msg', 'Thank you for your review! ⭐');
    }
}
