<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseIntentService
{
    public function remember(Request $request, string $action): void
    {
        $data = $request->validate([
            'product_id' => 'nullable|string|max:80',
            'quantity' => 'nullable|integer|min:1|max:99',
            'selected_size' => 'nullable|string|max:120',
            'dedication' => 'nullable|string|max:300',
            'color_theme' => 'nullable|string|max:300',
            'special_note' => 'nullable|string|max:1000',
            'custom_note' => 'nullable|string|max:1000',
            'shop' => 'nullable|string|max:150',
        ]);
        $request->session()->put('purchase_intent', [
            'id' => (string) Str::uuid(),
            'action' => $action,
            'data' => $data,
            'expires_at' => now()->addMinutes((int) config('purchase.intent_minutes', 30))->timestamp,
        ]);
    }

    public function resume(Request $request)
    {
        $intent = $request->session()->get('purchase_intent');
        $user = $request->session()->get('user');
        if (! $intent || ($user['role'] ?? null) !== 'customer') {
            return null;
        }

        if (($intent['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget('purchase_intent');

            return redirect()->route('customer.catalog')->with('error', 'Your saved selection expired. Please select your cake again.');
        }

        $request->session()->forget('customer_intended_url');

        // Only one request may consume this selection, even across concurrent tabs.
        try {
            return Cache::lock('purchase-intent:'.$intent['id'], 15)->block(3, function () use ($request, $intent, $user) {
                $key = 'purchase-intent-result:'.$intent['id'];
                if ($result = Cache::get($key)) {
                    $request->session()->forget('purchase_intent');

                    return redirect()->to($result);
                }

                $data = $intent['data'];
                if ($intent['action'] === 'custom') {
                    $shop = DB::table('shops')->where('shop_slug', $data['shop'] ?? '')
                        ->where('status', 'approved')->where('tier', 'verified')->first();
                    $response = $shop
                        ? redirect()->route('customer.custom_order', ['shop' => $shop->shop_slug])
                        : redirect()->route('customer.catalog')->with('error', 'Please choose an available verified shop for your custom cake.');
                } elseif ($intent['action'] === 'order_request') {
                    // Return to the shop to review and submit the request as a customer.
                    $product = DB::table('products')->where('id', $data['product_id'] ?? '')->first();
                    $slug = $product && $product->shop_id
                        ? DB::table('shops')->where('id', $product->shop_id)->value('shop_slug') : null;
                    $response = $slug
                        ? redirect()->route('platform.shop', ['slug' => $slug])->with('msg', 'You are signed in. Please review and submit your cake request.')
                        : redirect()->route('customer.catalog');
                } else {
                    $product = DB::table('products')->where('id', $data['product_id'] ?? '')
                        ->where('is_available', true)->whereNull('archived_at')->first();
                    $shopAvailable = $product && (empty($product->shop_id) || DB::table('shops')
                        ->where('id', $product->shop_id)->where('status', 'approved')->exists());
                    $stock = $shopAvailable
                        ? app(ProductStockService::class)->validateProductQuantity($product->id, (int) ($data['quantity'] ?? 1), $data['selected_size'] ?? null)
                        : ['ok' => false, 'message' => 'This cake or shop is no longer available. Please choose another cake.'];

                    if (! $stock['ok']) {
                        $response = redirect()->route('customer.catalog')->with('error', $stock['message']);
                    } else {
                        $parts = [];
                        foreach (['dedication' => 'Dedication', 'color_theme' => 'Color/Theme', 'special_note' => 'Notes'] as $field => $label) {
                            if ($value = trim($data[$field] ?? '')) {
                            $parts[] = $label.': '.($field === 'dedication' ? '"'.$value.'"' : $value);
                            }
                        }
                        if ($value = trim($data['custom_note'] ?? '')) {
                            $parts[] = $value;
                        }
                        $note = implode(' | ', $parts);
                        if ($intent['action'] === 'cart') {
                            $result = DB::transaction(fn () => app(CartService::class)->add($request, (string) $user['id'], $product->id, (int) ($data['quantity'] ?? 1), $data['selected_size'] ?? null, $note));
                            $response = redirect()->route('customer.cart')->with($result['ok'] ? 'msg' : 'error', $result['message']);
                        } else {
                            $request->session()->put('checkout', [
                                'product_id' => $product->id,
                                'quantity' => (int) ($data['quantity'] ?? 1),
                                'selected_size' => $data['selected_size'] ?? '',
                                'custom_note' => $note,
                                'customer_id' => (string) $user['id'],
                            ]);
                            $response = redirect()->route('customer.checkout')->with('msg', 'Your cake selection is ready. Review the current price and details before placing your order.');
                        }
                    }
                }

                Cache::put($key, $response->getTargetUrl(), now()->addMinutes(30));
                $request->session()->forget(['purchase_intent', 'guest_checkout', 'customer_intended_url']);

                return $response;
            });
        } catch (LockTimeoutException $exception) {
            return redirect()->route('purchase.resume')->with('msg', 'Your selection is being restored. Please continue in a moment.');
        }
    }
}
