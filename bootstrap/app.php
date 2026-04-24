<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.admin'      => \App\Http\Middleware\AuthAdmin::class,
            'auth.customer'   => \App\Http\Middleware\AuthCustomer::class,
            'auth.superadmin' => \App\Http\Middleware\AuthSuperAdmin::class,
            'auth.seller'     => \App\Http\Middleware\AuthSeller::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/logout',
        ]);
    })
    ->withProviders([
        \App\Providers\ViewServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'otp_code']))
                ->with('error', 'Your session has expired. Please try again.');
        });
    })->create();
