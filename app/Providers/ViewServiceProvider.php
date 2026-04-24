<?php
namespace App\Providers;
use App\Helpers\CakeshopHelper;
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

            if ($user && isset($user['role'])) {
                try {
                    $unreadMessages = CakeshopHelper::unreadMessagesCount($user['role'], $user['id']);
                } catch (\Exception $e) {}

                if (!isset($user['profile_photo'])) {
                    try {
                        $dbUser = \Illuminate\Support\Facades\DB::table('users')
                            ->where('id', $user['id'])->value('profile_photo');
                        $user['profile_photo'] = $dbUser ?? null;
                        session(['user' => $user]);
                    } catch (\Exception $e) {}
                }
            }

            $view->with(compact('settings','bgCss','unreadMessages'));
        });
    }
}
