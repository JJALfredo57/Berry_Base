<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnclaimedCakeService
{
    public const POLICY_VERSION = 'request-bake-unclaimed-v1';

    public function startGrace(object $order, string $note, string $photoPath, float $resalePrice, ?string $safeUntil = null): void
    {
        if (!$this->eligible($order)) throw new \DomainException('Only paid Request to Bake orders waiting for pickup or returned from delivery can start the unclaimed window.');
        if (!empty($order->unclaimed_grace_expires_at)) throw new \DomainException('The 3-day unclaimed window is already active.');
        if ($resalePrice <= 0) throw new \DomainException('Enter a valid resale price.');

        DB::transaction(function () use ($order, $note, $photoPath, $resalePrice, $safeUntil) {
            $expires = now()->addDays(3);
            DB::table('orders')->where('id', $order->id)->update([
                'unclaimed_grace_started_at' => now(), 'unclaimed_grace_expires_at' => $expires,
                'unclaimed_contact_note' => $note, 'updated_at' => now(),
            ]);
            DB::table('unclaimed_cake_listings')->updateOrInsert(['order_id' => $order->id], [
                'shop_id' => $order->shop_id, 'product_id' => $order->product_id,
                'selected_size' => $order->selected_size ?: null, 'quantity' => max(1, (int) $order->quantity),
                'resale_price' => $resalePrice, 'photo_path' => $photoPath, 'safe_until' => $safeUntil,
                'status' => 'scheduled', 'updated_at' => now(), 'created_at' => now(),
            ]);
        });
    }

    public function publishExpired(): int
    {
        if (!Schema::hasTable('unclaimed_cake_listings')) return 0;
        $orders = DB::table('orders')->whereNotNull('order_request_id')->whereNotNull('unclaimed_grace_expires_at')
            ->where('unclaimed_grace_expires_at', '<=', now())->whereIn('status', ['Pickup', 'Returned to Shop'])->get();
        $count = 0;
        foreach ($orders as $order) {
            DB::transaction(function () use ($order, &$count) {
                $listing = DB::table('unclaimed_cake_listings')->where('order_id', $order->id)->lockForUpdate()->first();
                if (!$listing || $listing->status !== 'scheduled' || (!empty($listing->safe_until) && $listing->safe_until <= now())) return;
                DB::table('orders')->where('id', $order->id)->update(['status' => 'No Show', 'updated_at' => now()]);
                DB::table('unclaimed_cake_listings')->where('id', $listing->id)->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);
                DB::table('order_tracking')->insert(['order_id' => $order->id, 'status' => 'No Show', 'notes' => 'Three-day unclaimed window expired. Ready cake published to catalog.', 'created_at' => now()]);
                app(MobileNotificationService::class)->notifyOrderCustomer($order, 'Order Marked Unclaimed', "Order #{$order->id} was not claimed within the three-day grace period.", ['event' => 'customer_no_show']);
                $count++;
            });
        }
        return $count;
    }

    private function eligible(object $order): bool
    {
        return !empty($order->order_request_id) && in_array((string) $order->status, ['Pickup', 'Returned to Shop'], true)
            && in_array((string) $order->payment_status, ['Partial Payment', 'Paid'], true);
    }
}
