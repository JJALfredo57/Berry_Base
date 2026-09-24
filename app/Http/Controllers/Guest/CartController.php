<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\ProductStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request, CartService $cartService)
    {
        return view('guest.cart', $cartService->items($request, null));
    }

    public function store(Request $request, CartService $cartService)
    {
        $parts = [];
        if ($d = trim($request->input('dedication', ''))) $parts[] = 'Dedication: "' . $d . '"';
        if ($c = trim($request->input('color_theme', ''))) $parts[] = 'Color/Theme: ' . $c;
        if ($s = trim($request->input('special_note', ''))) $parts[] = 'Notes: ' . $s;
        if ($n = trim($request->input('custom_note', ''))) $parts[] = $n;

        $result = $cartService->add(
            $request,
            null,
            (string) $request->input('product_id'),
            (int) $request->input('quantity', 1),
            $request->input('selected_size'),
            implode(' | ', $parts)
        );

        if ($result['ok'] && !$request->boolean('stay_on_catalog')) {
            return redirect()->route('cart')->with('msg', $result['message']);
        }

        return back()->with($result['ok'] ? 'msg' : 'error', $result['message']);
    }

    public function update(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return back()->with('error', 'Cart not found.');
        $item = DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->first();
        if (!$item) return back()->with('error', 'Cart item not found.');
        $meta = json_decode($item->meta ?? '[]', true) ?: [];
        if (($meta['cart_type'] ?? '') === 'custom_cake') {
            return back()->with('error', 'Custom cake quantity is part of the design request. Please remove and add the custom cake again to change it.');
        }
        $qty = max(1, min(99, (int) $request->input('quantity', 1)));
        $stock = app(ProductStockService::class)->validateProductQuantity((string) $item->product_id, $qty, $item->selected_size ?? null);
        if (!$stock['ok']) return back()->with('error', $stock['message']);
        if ((float) ($item->discount_amount_snapshot ?? 0) > 0) {
            $dealCheck = app(\App\Services\SweetDealService::class)->validateCartItems([(object) array_merge((array) $item, ['quantity' => $qty])]);
            if (!$dealCheck['ok']) return back()->with('error', $dealCheck['message']);
        }

        DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->update([
            'quantity' => $qty,
            'updated_at' => now(),
        ]);
        return back()->with('msg', 'Cart updated.');
    }

    public function destroy(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if ($cart) {
            DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->delete();
            $cartService->removeEmptyShop((int) $cart->id);
        }
        return back()->with('msg', 'Item removed from cart.');
    }

    public function refreshCustomHold(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return back()->with('error', 'Cart not found.');

        $item = DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->first();
        if (!$item) return back()->with('error', 'Cart item not found.');

        $meta = json_decode($item->meta ?? '[]', true) ?: [];
        if (($meta['cart_type'] ?? '') !== 'custom_cake') {
            return back()->with('error', 'This cart item is not a custom cake draft.');
        }

        $shopId = $item->shop_id ?? ($meta['shop_id'] ?? null);
        $scheduleDate = $meta['schedule_date'] ?? null;
        $quantity = max(1, (int) $item->quantity);
        $prep = app(\App\Services\PreparationWindowService::class);

        $prepDate = $prep->validateDate($shopId, $scheduleDate, 'custom');
        if (!$prepDate['ok']) return back()->with('error', $prepDate['message']);

        $capacity = app(\App\Services\DailyCapacityService::class)->validate($shopId, $scheduleDate, $quantity);
        if (!$capacity['allowed']) return back()->with('error', $capacity['message']);

        $settings = $prep->settings($shopId);
        $meta['fulfillment_hold_expires_at'] = $prep->holdUntil($shopId)->toDateTimeString();
        $meta['fulfillment_hold_minutes'] = (int) $settings->custom_cart_hold_minutes;
        $meta['seller_review_deadline_at'] = optional($prep->reviewDeadline($shopId, $scheduleDate))->toDateTimeString();
        $meta['custom_prep_days'] = (int) $settings->custom_cake_prep_days;

        DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->update([
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ]);

        return back()->with('msg', 'Fulfillment refreshed. Your custom cake schedule hold is active again.');
    }
    public function rescheduleCustomHold(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return back()->with('error', 'Cart not found.');

        $item = DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->first();
        if (!$item) return back()->with('error', 'Cart item not found.');

        $meta = json_decode($item->meta ?? '[]', true) ?: [];
        if (($meta['cart_type'] ?? '') !== 'custom_cake') {
            return back()->with('error', 'This cart item is not a custom cake draft.');
        }

        $shopId = $item->shop_id ?? ($meta['shop_id'] ?? null);
        $scheduleDate = $request->input('schedule_date') ?: null;
        $scheduleTime = trim((string) $request->input('time_slot', ''));
        $fulfillment = $request->input('fulfillment_type', $meta['fulfillment_type'] ?? 'Pickup');
        $fulfillment = in_array($fulfillment, ['Pickup', 'Delivery'], true) ? $fulfillment : 'Pickup';
        $quantity = max(1, (int) $item->quantity);
        $lat = isset($meta['latitude']) && $meta['latitude'] !== '' ? (float) $meta['latitude'] : null;
        $lng = isset($meta['longitude']) && $meta['longitude'] !== '' ? (float) $meta['longitude'] : null;

        if (!$scheduleDate) return back()->with('error', 'Please choose a new date for this custom cake.');
        if ($scheduleTime === '') return back()->with('error', 'Please choose a new time for this custom cake.');
        if ($fulfillment === 'Delivery' && (empty($meta['address']) || $lat === null || $lng === null)) {
            return back()->with('error', 'Delivery reschedule needs a saved delivery address. Please recreate the draft with delivery details.');
        }

        $prep = app(\App\Services\PreparationWindowService::class);
        $prepDate = $prep->validateDate($shopId, $scheduleDate, 'custom');
        if (!$prepDate['ok']) return back()->with('error', $prepDate['message']);

        $scheduleCheck = app(\App\Services\OrderScheduleService::class)->validate($scheduleDate, $scheduleTime, $shopId, 'custom', $fulfillment, $lat, $lng, false);
        if (!$scheduleCheck['ok']) return back()->with('error', $scheduleCheck['message']);

        $capacity = app(\App\Services\DailyCapacityService::class)->validate($shopId, $scheduleDate, $quantity);
        if (!$capacity['allowed']) return back()->with('error', $capacity['message']);

        $settings = $prep->settings($shopId);
        $meta['schedule_date'] = $scheduleDate;
        $meta['schedule_time'] = $scheduleTime;
        $meta['time_slot'] = $scheduleCheck['slot']->label ?? $scheduleTime;
        $meta['fulfillment_type'] = $fulfillment;
        $meta['fulfillment_hold_expires_at'] = $prep->holdUntil($shopId)->toDateTimeString();
        $meta['fulfillment_hold_minutes'] = (int) $settings->custom_cart_hold_minutes;
        $meta['seller_review_deadline_at'] = optional($prep->reviewDeadline($shopId, $scheduleDate))->toDateTimeString();
        $meta['custom_prep_days'] = (int) $settings->custom_cake_prep_days;

        DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->update([
            'selected_size' => $meta['size'] ?? $item->selected_size,
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ]);

        return back()->with('msg', 'Custom cake schedule updated. Your schedule hold is active again.');
    }
    public function checkoutItem(Request $request, string $id, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return redirect()->route('cart')->with('error', 'Cart not found.');
        $item = DB::table('customer_cart_items')->where('id', $id)->where('cart_id', $cart->id)->first();
        if (!$item) return redirect()->route('cart')->with('error', 'Cart item not found.');

        $request->session()->put('guest_checkout', [
            'cart_id' => $cart->id,
            'cart_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => max(1, (int) $item->quantity),
            'custom_note' => $item->custom_note ?? '',
            'selected_size' => $item->selected_size ?? '',
        ]);

        return redirect()->route('guest.checkout');
    }

    public function checkoutShop(Request $request, string $shopId, CartService $cartService)
    {
        $cart = $cartService->cart($request, null);
        if (!$cart) return redirect()->route('cart')->with('error', 'Cart not found.');

        $shopKey = $shopId === 'platform' ? null : $shopId;
        $selectedIds = collect($request->input('selected_item_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Please select at least one cake to checkout.');
        }

        $items = DB::table('customer_cart_items')
            ->where('cart_id', $cart->id)
            ->when($shopKey, fn ($q) => $q->where('shop_id', $shopKey), fn ($q) => $q->whereNull('shop_id'))
            ->whereIn('id', $selectedIds->all())
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart')->with('error', 'No selected cart items found for that seller.');
        }

        if ($items->count() !== $selectedIds->count()) {
            return redirect()->route('cart')->with('error', 'Some selected cakes are no longer available in this seller cart. Please review your cart.');
        }

        foreach ($items as $item) {
            $meta = json_decode($item->meta ?? '[]', true) ?: [];
            if (($meta['cart_type'] ?? '') === 'custom_cake') continue;
            $stock = app(ProductStockService::class)->validateProductQuantity((string) $item->product_id, max(1, (int) $item->quantity), $item->selected_size ?? null);
            if (!$stock['ok']) {
                return redirect()->route('cart')->with('error', $stock['message']);
            }
        }

        $dealCheck = app(\App\Services\SweetDealService::class)->validateCartItems($items);
        if (!$dealCheck['ok']) {
            return redirect()->route('cart')->with('error', $dealCheck['message']);
        }

        $first = $items->first(function ($item) {
            $meta = json_decode($item->meta ?? '[]', true) ?: [];
            return ($meta['cart_type'] ?? '') !== 'custom_cake';
        }) ?: $items->first();
        $request->session()->put('guest_checkout', [
            'cart_id' => $cart->id,
            'cart_shop_id' => $shopKey,
            'cart_item_ids' => $items->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'product_id' => $first->product_id,
            'quantity' => (int) $items->sum('quantity'),
            'custom_note' => '',
            'selected_size' => '',
            'is_group' => true,
        ]);

        return redirect()->route('guest.checkout');
    }
}
