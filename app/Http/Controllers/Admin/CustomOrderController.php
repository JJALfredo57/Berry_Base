<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomOrderController extends Controller
{
    use UploadsFiles;
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'All');

        $customOrders = DB::table('custom_orders as co')
            ->leftJoin('users as u', 'u.id', '=', 'co.user_id')
            ->leftJoin('orders as o', 'o.id', '=', 'co.order_id')
            ->select(
                'co.*',
                DB::raw('COALESCE(o.guest_name, u.fullname) as fullname'),
                DB::raw('COALESCE(o.guest_phone, u.phone) as phone'),
                DB::raw("COALESCE(u.username, 'Guest') as username"),
                DB::raw("COALESCE(u.email, '') as email"),
                DB::raw('COALESCE(u.profile_photo, NULL) as profile_photo'),
                'o.status as order_status', 'o.total_price as order_total',
                'o.fulfillment_type', 'o.schedule_date', 'o.schedule_time',
                'o.payment_method', 'o.payment_status', 'o.address'
            )
            ->when($search, fn($q) => $q->where(fn($sq) => $sq
                ->where('co.cake_name', 'like', "%$search%")
                ->orWhereRaw("COALESCE(o.guest_name, u.fullname) like ?", ["%$search%"])
                ->orWhere('co.order_id', 'like', "%$search%")
            ))
            ->when($status && $status !== 'All', fn($q) => $q->where('co.review_status', $status))
            ->orderByDesc('co.id')
            ->paginate(10)
            ->withQueryString();

        $orderIds = collect($customOrders->items())->pluck('order_id')->filter()->values()->toArray();
        $orderAddons = [];
        if ($orderIds) {
            try {
                $rows = DB::table('order_addons')->whereIn('order_id', $orderIds)->get();
                foreach ($rows as $a) $orderAddons[$a->order_id][] = $a;
            } catch (\Exception $e) {}
        }

        $pendingCount  = DB::table('custom_orders')->where('review_status', 'pending')->count();
        $approvedCount = DB::table('custom_orders')->where('review_status', 'approved')->count();
        $rejectedCount = DB::table('custom_orders')->where('review_status', 'rejected')->count();

        return view('admin.custom_orders', compact('customOrders', 'orderAddons', 'pendingCount', 'approvedCount', 'rejectedCount', 'search', 'status'));
    }

    public function approve(Request $request, string $id)
    {
        $user    = session('user');
        $price   = (float) $request->input('admin_price', 0);
        $comment = trim($request->input('admin_comment', ''));
        $note    = trim($request->input('admin_internal_note', ''));

        $co = DB::table('custom_orders')->where('id', $id)->first();
        if (!$co) return redirect()->route('admin.custom_orders.index')->with('err', 'Custom order not found.');
        if ($co->review_status !== 'pending') return redirect()->route('admin.custom_orders.index')->with('err', 'Already reviewed.');
        if ($price <= 0) return redirect()->route('admin.custom_orders.index')->with('err', 'Please enter a valid price.');

        // Always require customer to confirm price and pay deposit before confirming
        DB::table('custom_orders')->where('id', $id)->update([
            'review_status'       => 'approved',
            'admin_price'         => $price,
            'admin_comment'       => $comment ?: null,
            'admin_internal_note' => $note ?: null,
            'reviewed_at'         => now(),
            'reviewed_by'         => $user['id'],
            'price_confirmed'     => 'pending',
        ]);

        if ($co->order_id) {
            $order = DB::table('orders')->where('id', $co->order_id)->first();
            if ($order) {
                $newTotal = $price + ($order->delivery_fee ?? 0) + ($order->service_charge ?? 0);
                // Always stay Pending Review — customer must accept price + pay deposit first
                DB::table('orders')->where('id', $co->order_id)->update([
                    'status'      => 'Pending Review',
                    'total_price' => $newTotal,
                ]);

                // Always send price proposal — customer must confirm and pay deposit first
                $estimatedPrice = (float)($co->estimated_price ?? 0);
                $msg = "Price Proposal for Your Custom Order #{$co->order_id}\n\n"
                     . "Our baker has reviewed your custom cake request and has set a final price:\n\n"
                     . "Cake: {$co->cake_name}\n"
                     . "Final Price: PHP " . number_format($price, 2)
                     . ($estimatedPrice > 0 ? "\nEstimated was: PHP " . number_format($estimatedPrice, 2) : "")
                     . "\n"
                     . ($comment ? "\nBaker's note: {$comment}\n" : "")
                     . "\nPlease go to My Orders to ACCEPT or CANCEL this price.";

                DB::table('messages')->insert([
                    'order_id'    => $co->order_id,
                    'sender_role' => 'admin',
                    'sender_id'   => $user['id'],
                    'message'     => $msg,
                    'is_read' => false,
                    'created_at'  => now(),
                ]);
                DB::table('notifications')->insert([
                    'receiver_role'    => 'customer',
                    'receiver_user_id' => $co->user_id,
                    'title'            => 'Price Proposal — Custom Order #' . $co->order_id,
                    'message'          => 'Baker set final price: PHP ' . number_format($price, 2) . '. Please accept or cancel in My Orders.',
                    'is_read' => false,
                    'created_at'       => now(),
                ]);
                DB::table('order_tracking')->insert([
                    'order_id'   => $co->order_id,
                    'status'     => 'Pending Review',
                    'notes'      => 'Admin sent price proposal: PHP ' . number_format($price, 2) . '. Awaiting customer confirmation.',
                    'created_at' => now(),
                ]);

                // SMS notification
                $phone = DB::table('users')->where('id', $co->user_id)->value('phone');
                if ($phone) {
                    $siteName = config('app.name', 'Cake Shop');
                    \App\Helpers\SmsHelper::send($phone,
                        "[{$siteName}]\nPrice proposal for your custom order #{$co->order_id} - PHP " . number_format($price, 2) . ". Please check My Orders to accept or cancel."
                    );
                }
            }
        }

        $successMsg = "Price proposal sent to customer. Waiting for their confirmation.";

        CakeshopHelper::logActivity($user['id'], 'admin', 'Approve Custom Order', "Custom Order #{$id}");
        return redirect()->route('admin.custom_orders.index')->with('msg', $successMsg);
    }


    public function reject(Request $request, string $id)
    {
        $user   = session('user');
        $reason = trim($request->input('admin_comment', ''));

        if (empty($reason)) return redirect()->route('admin.custom_orders.index')->with('err', 'Please provide a reason for rejection.');

        $co = DB::table('custom_orders')->where('id', $id)->first();
        if (!$co) return redirect()->route('admin.custom_orders.index')->with('err', 'Custom order not found.');
        if ($co->review_status !== 'pending') return redirect()->route('admin.custom_orders.index')->with('err', 'Already reviewed.');

        DB::table('custom_orders')->where('id', $id)->update([
            'review_status' => 'rejected',
            'admin_comment' => $reason,
            'reviewed_at'   => now(),
            'reviewed_by'   => $user['id'],
        ]);

        if ($co->order_id) {
            DB::table('orders')->where('id', $co->order_id)->update(['status' => 'Cancelled']);
            DB::table('order_tracking')->insert([
                'order_id'   => $co->order_id,
                'status'     => 'Cancelled',
                'notes'      => 'Custom order rejected. Reason: ' . $reason,
                'created_at' => now(),
            ]);

            $msg = "❌ Your Custom Order #{$co->order_id} was not approved.\nReason: {$reason}\n\nFeel free to message us if you have questions or want to modify your request.";
            DB::table('messages')->insert([
                'order_id'    => $co->order_id,
                'sender_role' => 'admin',
                'sender_id'   => $user['id'],
                'message'     => $msg,
                'is_read' => false,
                'created_at'  => now(),
            ]);
            DB::table('notifications')->insert([
                'receiver_role'    => 'customer',
                'receiver_user_id' => $co->user_id,
                'title'            => 'Custom Order #' . $co->order_id . ' Not Approved',
                'message'          => 'Reason: ' . $reason,
                'is_read' => false,
                'created_at'       => now(),
            ]);
        }

        CakeshopHelper::logActivity($user['id'], 'admin', 'Reject Custom Order', "Custom Order #{$id}");
        return redirect()->route('admin.custom_orders.index')->with('msg', "Custom Order #{$id} rejected.");
    }

    public function sendProgress(Request $request, string $id)
    {
        $user    = session('user');
        $message = trim($request->input('progress_message', ''));
        $imgPath = null;

        if ($request->hasFile('progress_image') && $request->file('progress_image')->isValid()) {
            $file = $request->file('progress_image');
            $ext  = strtolower($file->getClientOriginalExtension());
            if ($file->getSize() <= 5 * 1024 * 1024 && in_array($ext, ['jpg','jpeg','png','webp'])) {
                $imgPath = $this->uploadFile($file, 'uploads/custom_orders');
            }
        }

        if (!$message && !$imgPath) return redirect()->route('admin.custom_orders.index')->with('err', 'Please add a message or image.');

        $co = DB::table('custom_orders')->where('id', $id)->first();
        if (!$co) return redirect()->route('admin.custom_orders.index')->with('err', 'Custom order not found.');

        // Save progress to custom_orders
        DB::table('custom_orders')->where('id', $id)->update([
            'progress_image'   => $imgPath,
            'progress_message' => $message ?: null,
            'progress_sent_at' => now(),
        ]);

        // Send to message thread
        $fullMsg = $message ? "🍰 Progress Update: {$message}" : '🍰 Progress update for your custom cake!';
        if ($imgPath) $fullMsg .= "\n📎 See the progress photo attached.";
        $fullMsg .= "\n\n[View Custom Order Details: #co{$id}]";

        $msgId = DB::table('messages')->insertGetId([
                        'sender_role' => 'admin',
            'sender_id'   => $user['id'],
            'message'     => $fullMsg,
            'image_path'  => $imgPath,
            'is_read' => false,
            'created_at'  => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role'    => 'customer',
            'receiver_user_id' => $co->user_id,
            'title'   => '🍰 Progress Update on Your Custom Cake!',
            'message' => $message ?: 'Admin sent a progress photo of your custom cake.',
            'is_read' => false,
            'created_at' => now(),
        ]);

        CakeshopHelper::logActivity($user['id'], 'admin', 'Send Progress Photo', "Custom Order #{$id}");
        return redirect()->route('admin.custom_orders.index')->with('msg', '✅ Progress update sent to customer!');
    }
}
