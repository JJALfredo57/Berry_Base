<?php
namespace App\Providers;
use App\Helpers\CakeshopHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $settings = CakeshopHelper::getSettings();
            $bgCss    = CakeshopHelper::backgroundCss($settings);
            $user     = session('user');    // admin only now
            $unreadMessages = 0;
            $sellerSidebarCounts = [
                'orders' => 0,
                'kitchen' => 0,
                'messages' => 0,
                'custom_orders' => 0,
                'reviews' => 0,
                'feedback' => 0,
                'pickup_ready' => 0,
                'pending_orders' => 0,
                'kitchen_pending' => 0,
                'kitchen_preparing' => 0,
            ];

            if ($user && isset($user['role'])) {
                try {
                    $unreadMessages = CakeshopHelper::unreadMessagesCount($user['role'], $user['id']);
                } catch (\Exception $e) {}

                if (($user['role'] ?? null) === 'seller') {
                    try {
                        $shop = DB::table('shops')
                            ->where('seller_id', $user['id'])
                            ->where('status', 'approved')
                            ->first();

                        if ($shop) {
                            $pendingOrders = (int) DB::table('orders')
                                ->where('shop_id', $shop->id)
                                ->whereIn('status', ['Pending', 'Pending Review'])
                                ->count();

                            $pickupReady = (int) DB::table('orders')
                                ->where('shop_id', $shop->id)
                                ->where('status', 'Pickup')
                                ->count();

                            $messages = (int) DB::table('messages as m')
                                ->join('orders as o', 'o.id', '=', 'm.order_id')
                                ->where('o.shop_id', $shop->id)
                                ->where('m.sender_role', 'customer')
                                ->where('m.is_read', false)
                                ->count();

                            $kitchenPending = 0;
                            $kitchenPreparing = 0;
                            if (Schema::hasTable('kitchen_tickets')) {
                                $kitchenPending = (int) DB::table('kitchen_tickets as kt')
                                    ->join('orders as o', 'o.id', '=', 'kt.order_id')
                                    ->where('o.shop_id', $shop->id)
                                    ->where('kt.status', 'pending')
                                    ->count();
                                $kitchenPreparing = (int) DB::table('kitchen_tickets as kt')
                                    ->join('orders as o', 'o.id', '=', 'kt.order_id')
                                    ->where('o.shop_id', $shop->id)
                                    ->where('kt.status', 'in_progress')
                                    ->count();
                            }

                            $customOrders = Schema::hasTable('custom_orders')
                                ? (int) DB::table('custom_orders')
                                    ->where('shop_id', $shop->id)
                                    ->where('review_status', 'pending')
                                    ->count()
                                : 0;

                            $reviews = Schema::hasTable('order_reviews')
                                ? (int) DB::table('order_reviews')
                                    ->where('shop_id', $shop->id)
                                    ->where('review_status', 'pending')
                                    ->count()
                                : 0;

                            $feedback = 0;
                            if (Schema::hasTable('customer_feedback')) {
                                $feedbackQuery = DB::table('customer_feedback')->where('status', 'open');
                                if (Schema::hasColumn('customer_feedback', 'shop_id')) {
                                    $feedbackQuery->where('shop_id', $shop->id);
                                }
                                if (Schema::hasColumn('customer_feedback', 'source_role')) {
                                    $feedbackQuery->where('source_role', 'seller');
                                }
                                $feedback = (int) $feedbackQuery->count();
                            }

                            $sellerSidebarCounts = [
                                'orders' => $pendingOrders + $pickupReady,
                                'kitchen' => $kitchenPending + $kitchenPreparing,
                                'messages' => $messages,
                                'custom_orders' => $customOrders,
                                'reviews' => $reviews,
                                'feedback' => $feedback,
                                'pickup_ready' => $pickupReady,
                                'pending_orders' => $pendingOrders,
                                'kitchen_pending' => $kitchenPending,
                                'kitchen_preparing' => $kitchenPreparing,
                            ];
                            $unreadMessages = $messages;
                        }
                    } catch (\Throwable $e) {}
                }

                if (!isset($user['profile_photo'])) {
                    try {
                        $dbUser = DB::table('users')
                            ->where('id', $user['id'])->value('profile_photo');
                        $user['profile_photo'] = $dbUser ?? null;
                        session(['user' => $user]);
                    } catch (\Exception $e) {}
                }
            }

            $view->with(compact('settings','bgCss','unreadMessages','sellerSidebarCounts'));
        });
    }
}
