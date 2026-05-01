<?php
namespace App\Http\Controllers;

use App\Helpers\SmsHelper;
use App\Helpers\CakeshopHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderController extends Controller
{
    use UploadsFiles;

    /** Resolve a pasted PHONE|PIN access code from the catalog sidebar */
    public function accessByCode(Request $request)
    {
        $raw = trim($request->input('code', ''));

        if (!str_contains($raw, '|')) {
            return back()->with('rider_err', 'Invalid code. Paste the full delivery code from your SMS (e.g. 09171234567|492847).');
        }

        [$phone, $pin] = explode('|', $raw, 2);
        $phone = trim($phone);
        $pin   = trim($pin);

        if (!$phone || !$pin) {
            return back()->with('rider_err', 'Incomplete code. Make sure you copied the full delivery code.');
        }

        $clean = preg_replace('/\D/', '', $phone);
        if (str_starts_with($clean, '0'))   $clean = '63' . substr($clean, 1);
        if (!str_starts_with($clean, '63')) $clean = '63' . $clean;
        $formats = [$phone, '+' . $clean, $clean, '0' . substr($clean, 2)];

        $rider = DB::table('riders')
            ->where('is_active', true)
            ->where(function ($q) use ($formats) {
                foreach ($formats as $f) $q->orWhere('phone', $f);
            })->first();

        if (!$rider) {
            return back()->with('rider_err', 'Code not recognized. Check that you pasted the correct code.');
        }

        $order = DB::table('orders')
            ->where('rider_id', $rider->id)
            ->where('rider_pin', $pin)
            ->whereIn('status', ['Out for Delivery', 'Attempted Delivery'])
            ->whereNotNull('rider_token')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            return back()->with('rider_err', 'No active delivery found. The code may have expired or already been used.');
        }

        return redirect()->route('rider.show', [$order->id, $order->rider_token]);
    }

    /** Show rider delivery page */
    public function show(string $orderId, string $token)
    {
        $order = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('riders as r', 'r.id', '=', 'o.rider_id')
            ->where('o.id', $orderId)
            ->where('o.rider_token', $token)
            ->select('o.*', 'p.name as product_name', 'r.name as rider_name')
            ->first();

        if (!$order) abort(404, 'Invalid delivery link.');

        if (!in_array($order->status, ['Out for Delivery'])) {
            return view('rider.delivery', ['order' => $order, 'done' => true]);
        }

        $addons = DB::table('order_addons')->where('order_id', $orderId)->get();
        $settings = CakeshopHelper::getSettings();

        return view('rider.delivery', compact('order','addons','settings'));
    }

    /** Rider marks as delivered */
    public function markDelivered(Request $request, string $orderId, string $token)
    {
        $order = DB::table('orders')->where('id',$orderId)->where('rider_token',$token)->first();
        if (!$order || $order->status !== 'Out for Delivery')
            return response()->json(['ok'=>false,'error'=>'Invalid or already updated.']);

        try {
            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $file  = $request->file('photo');
                $photoPath = $this->uploadFile($file, 'uploads/delivery');
            }

            $riderNote = trim($request->input('note', '')) ?: null;

            $upd = [
                'status'           => 'Delivered',
                'delivered_at'     => now()->format('Y-m-d H:i:s'),
                'review_requested' => 1,
                'delivery_photo'   => $photoPath,
            ];

            // COD → auto Paid
            if ($order->payment_method === 'COD' && $order->payment_status !== 'Paid') {
                $upd['payment_status'] = 'Paid';
                $upd['paid_at'] = now()->format('Y-m-d H:i:s');
            }

            DB::table('orders')->where('id',$orderId)->update($upd);

            $trackingNotes = 'Marked as delivered by rider.';
            if ($photoPath)   $trackingNotes .= ' Proof of delivery photo uploaded.';
            if ($riderNote)   $trackingNotes .= ' Note: ' . $riderNote;

            DB::table('order_tracking')->insert([
                'order_id'   => $orderId,
                'status'     => 'Delivered',
                'notes'      => $trackingNotes,
                'created_at' => now(),
            ]);

            // Update rider delivery count
            if ($order->rider_id) {
                DB::table('riders')->where('id',$order->rider_id)->increment('deliveries_count');
            }

            $siteName = config('app.name','Cake Shop');
            $riderName = DB::table('riders')->where('id',$order->rider_id)->value('name') ?? 'Rider';

            // No SMS to customer on delivery — per plan, visible on tracking page
            // No SMS to admin — visible in admin panel notifications

            // Admin notification
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => "Order #{$orderId} Delivered",
                'message'          => "Rider {$riderName} marked Order #{$orderId} as delivered.",
                'is_read'          => 0,
                'created_at'       => now(),
            ]);

            return response()->json(['ok'=>true]);

        } catch (\Throwable $e) {
            Log::error('Rider markDelivered: ' . $e->getMessage());
            return response()->json(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
        }
    }

    /** Rider reports an issue */
    public function reportIssue(Request $request, string $orderId, string $token)
    {
        $order = DB::table('orders')->where('id',$orderId)->where('rider_token',$token)->first();
        if (!$order || $order->status !== 'Out for Delivery')
            return response()->json(['ok'=>false,'error'=>'Invalid or already updated.']);

        try {
            $issueType = $request->input('issue_type'); // damaged/not_home/other
            $note      = trim($request->input('note',''));

            $photoPath = null;
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $file  = $request->file('photo');
                $photoPath = $this->uploadFile($file, 'uploads/delivery');
            }

            $newStatus = match($issueType) {
                'not_home' => 'Attempted Delivery',
                default    => 'Issue Reported',
            };

            DB::table('orders')->where('id',$orderId)->update([
                'status'            => $newStatus,
                'issue_type'        => $issueType,
                'issue_photo'       => $photoPath,
                'issue_note'        => $note ?: null,
                'issue_status'      => 'pending',
                'issue_reported_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id'   => $orderId,
                'status'     => $newStatus,
                'notes'      => "Rider reported: {$issueType}" . ($note ? " - {$note}" : ''),
                'created_at' => now(),
            ]);

            $siteName  = config('app.name','Cake Shop');
            $riderName = DB::table('riders')->where('id',$order->rider_id)->value('name') ?? 'Rider';
            $issueLabel = match($issueType) {
                'damaged'  => 'Damaged Cake',
                'not_home' => 'Customer Not Home',
                default    => 'Other Issue',
            };

            // SMS to customer
            $custPhone = $order->guest_phone ?? null;
            if ($custPhone) {
                $shopName = SmsHelper::getShopName($order->shop_id ?? null);
                $header   = SmsHelper::header($siteName, $shopName);
                $shopLine = $shopName ? "\nShop: {$shopName}" : '';
                $custName = $order->guest_name ?? 'Customer';
                $msg = $issueType === 'not_home'
                    ? "{$header}\nHi {$custName}, we attempted to deliver your order but no one was available.\n\nOrder No.: #{$orderId}{$shopLine}\n\nOur team will contact you shortly to arrange a reschedule. We apologize for the inconvenience."
                    : "{$header}\nHi {$custName}, we encountered an issue with your delivery.\n\nOrder No.: #{$orderId}{$shopLine}\n\nOur team will contact you shortly to resolve this. We sincerely apologize for the inconvenience.";
                SmsHelper::send($custPhone, $msg);
            }

            // No SMS to admin — visible in admin panel notifications

            // Admin notification
            DB::table('notifications')->insert([
                'receiver_role'    => 'admin',
                'receiver_user_id' => null,
                'title'            => "Delivery Issue — Order #{$orderId}",
                'message'          => "Rider {$riderName} reported: {$issueLabel}." . ($note ? " Note: {$note}" : ''),
                'is_read'          => 0,
                'created_at'       => now(),
            ]);

            return response()->json(['ok'=>true,'status'=>$newStatus]);

        } catch (\Throwable $e) {
            Log::error('Rider reportIssue: ' . $e->getMessage());
            return response()->json(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
        }
    }
}
