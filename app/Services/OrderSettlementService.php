<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Produces one financial snapshot for a completed order.  The same snapshot is
 * used by payouts and reports so cash, online payments, delivery fees and
 * commission cannot be counted twice in different dashboards.
 */
class OrderSettlementService
{
    public function snapshot(object $order, object $shop): array
    {
        $total = $this->money($order->total_price ?? 0);
        $deliveryFee = min($total, $this->money($order->delivery_fee ?? 0));
        // Gateway fees are paid by the customer and are never seller sales.
        $serviceCharge = min(max(0, $total - $deliveryFee), $this->money($order->service_charge ?? 0));
        $cakeAmount = max(0, $total - $deliveryFee - $serviceCharge);

        $online = $this->onlinePaid((string) $order->id, $order);
        $remittance = $this->remittance((string) $order->id, $order);
        $cashCollected = $this->cashCollected($order, $online, $remittance);
        $riderCashFee = $this->riderCashFee($order, $cashCollected, $remittance);
        $cashToSeller = $this->cashToSeller($order, $cashCollected, $riderCashFee, $remittance);
        $riderOnlineFee = max(0, $deliveryFee - $riderCashFee);

        $rate = !empty($shop->commission_enabled)
            ? max(0, $this->money($shop->commission_rate ?? 0))
            : 0.0;
        $commission = $this->money($cakeAmount * $rate / 100);
        // Only money held online is payable through the seller-payout flow.
        // Cash already handed to the seller is deliberately excluded.
        $onlineSellerPayout = max(0, $this->money($online - $riderOnlineFee - $commission));

        return [
            'gross_amount' => $online,
            'cake_amount' => $cakeAmount,
            'delivery_fund_amount' => $riderOnlineFee,
            'commission_base' => $cakeAmount,
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'seller_net_amount' => $onlineSellerPayout,
            'online_collected_amount' => $online,
            'cash_collected_amount' => $cashCollected,
            'cash_to_seller_amount' => $cashToSeller,
            'rider_cash_fee_amount' => $riderCashFee,
            'rider_online_fee_amount' => $riderOnlineFee,
            'total_seller_earnings' => $this->money($cashToSeller + $onlineSellerPayout),
            'remaining_balance' => max(0, $this->money($total - $online - $cashCollected)),
        ];
    }

    private function onlinePaid(string $orderId, object $order): float
    {
        // Test and reconciliation callers may provide an already-verified
        // payment total, avoiding a second receipt query.
        if (property_exists($order, 'online_paid_amount')) return $this->money($order->online_paid_amount);
        if (Schema::hasTable('payment_transactions')) {
            $amount = DB::table('payment_transactions')
                ->where('order_id', $orderId)->where('payment_status', 'paid')
                ->whereRaw('UPPER(method) = ?', ['GCASH'])->sum('amount');
            if ((float) $amount > 0) return $this->money($amount);
        }
        return ($order->deposit_status ?? '') === 'paid' ? $this->money($order->deposit_amount ?? 0) : 0.0;
    }

    private function remittance(string $orderId, ?object $order = null): ?object
    {
        if ($order && property_exists($order, 'remittance_snapshot')) return $order->remittance_snapshot;
        return Schema::hasTable('rider_remittances')
            ? DB::table('rider_remittances')->where('order_id', $orderId)->first()
            : null;
    }

    private function cashCollected(object $order, float $online, ?object $remittance): float
    {
        $method = strtoupper((string) ($order->payment_method ?? ''));
        if ($method === 'COD' && ($order->fulfillment_type ?? '') === 'Delivery') {
            if ($remittance && isset($remittance->customer_collected_amount)) return $this->money($remittance->customer_collected_amount);
            return ($order->payment_status ?? '') === 'Paid' ? max(0, $this->money($order->total_price ?? 0) - $online) : 0.0;
        }
        if (in_array($method, ['COP', 'COD'], true) && ($order->fulfillment_type ?? '') === 'Pickup') {
            return ($order->payment_status ?? '') === 'Paid' ? max(0, $this->money($order->total_price ?? 0) - $online) : 0.0;
        }
        return 0.0;
    }

    private function riderCashFee(object $order, float $cash, ?object $remittance): float
    {
        if (($order->fulfillment_type ?? '') !== 'Delivery' || strtoupper((string) ($order->payment_method ?? '')) !== 'COD') return 0.0;
        if ($remittance && isset($remittance->delivery_fee_retained)) return $this->money($remittance->delivery_fee_retained);
        return min($this->money($order->delivery_fee ?? 0), $cash);
    }

    private function cashToSeller(object $order, float $cash, float $riderCashFee, ?object $remittance): float
    {
        $method = strtoupper((string) ($order->payment_method ?? ''));
        if (($order->fulfillment_type ?? '') === 'Pickup' && in_array($method, ['COP', 'COD'], true)) return $cash;
        if (($order->fulfillment_type ?? '') === 'Delivery' && $method === 'COD') {
            if (($remittance->status ?? '') !== 'confirmed') return 0.0;
            return $this->money($remittance->cake_remittance_amount ?? max(0, $cash - $riderCashFee));
        }
        return 0.0;
    }

    private function money(mixed $amount): float { return round(max(0, (float) $amount), 2); }
}
