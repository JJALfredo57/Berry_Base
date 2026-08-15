<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function markReadAdmin()
    {
        $role = (string) (session('user.role') ?? 'admin');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            $role = 'admin';
        }

        $update = ['is_read' => true];
        if (Schema::hasColumn('notifications', 'read_at')) {
            $update['read_at'] = now();
        }

        DB::table('notifications')
            ->where('receiver_role', $role)
            ->where('is_read', false)
            ->update($update);
        return back();
    }

    public function markReadCustomer()
    {
        $uid = session('user')['id'];
        $update = ['is_read' => true];
        if (Schema::hasColumn('notifications', 'read_at')) {
            $update['read_at'] = now();
        }

        DB::table('notifications')
            ->where('receiver_role','customer')
            ->where('receiver_user_id', $uid)
            ->where('is_read', false)
            ->update($update);
        return back();
    }

    public function open(string $id)
    {
        $user = session('user', []);
        $role = (string) ($user['role'] ?? '');
        $uid = (string) ($user['id'] ?? '');

        $notification = DB::table('notifications')->where('id', $id)->first();
        if (!$notification || !$this->canAccess($notification, $role, $uid)) {
            abort(404);
        }

        $update = ['is_read' => true, 'updated_at' => now()];
        if (Schema::hasColumn('notifications', 'read_at')) {
            $update['read_at'] = now();
        }

        DB::table('notifications')->where('id', $id)->update($update);

        return redirect($this->targetUrl($notification, $role));
    }

    private function canAccess(object $notification, string $role, string $uid): bool
    {
        if ($role === '') {
            return false;
        }

        if ((string) $notification->receiver_role !== $role) {
            return false;
        }

        return empty($notification->receiver_user_id)
            || (string) $notification->receiver_user_id === $uid;
    }

    private function targetUrl(object $notification, string $role): string
    {
        if (Schema::hasColumn('notifications', 'url') && !empty($notification->url)) {
            return (string) $notification->url;
        }

        $orderId = trim((string) ($notification->order_id ?? ''));
        $text = strtolower(trim(($notification->title ?? '') . ' ' . ($notification->message ?? '')));
        $isMessage = str_contains($text, 'message');

        if ($role === 'seller') {
            return $isMessage && $orderId !== ''
                ? route('seller.messages.thread', $orderId)
                : route('seller.orders');
        }

        if ($role === 'customer') {
            return $isMessage && $orderId !== ''
                ? route('customer.messages.thread', $orderId)
                : route('customer.orders');
        }

        if ($role === 'superadmin') {
            if (str_contains($text, 'seller')) return route('superadmin.sellers');
            if (str_contains($text, 'payout')) return route('superadmin.payouts');
            if (str_contains($text, 'feedback')) return route('superadmin.feedback');
            return route('superadmin.dashboard');
        }

        if ($isMessage && $orderId !== '') {
            return route('admin.messages.thread', $orderId);
        }

        if (str_contains($text, 'custom')) {
            return route('admin.custom_orders.index');
        }

        return route('admin.orders.index');
    }
}
