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

        // CSRF token mismatch — redirect back with inputs preserved
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'Session expired. Please refresh and try again.'], 419);
            }
            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'otp_code']))
                ->with('error', 'Your session has expired. Please try again.');
        });

        // Model not found → 404
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'Resource not found.'], 404);
            }
            return response()->view('errors.404', [], 404);
        });

        // Authorization failure → 403
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'Access denied.'], 403);
            }
            return response()->view('errors.403', [], 403);
        });

        // Rate limiting → 429
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'Too many requests. Please slow down.'], 429);
            }
            return response()->view('errors.429', [], 429);
        });

        // General HTTP errors (abort()) — let Laravel use our custom error views
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage() ?: 'An error occurred.'], $e->getStatusCode());
            }
            $code = $e->getStatusCode();
            $view = "errors.{$code}";
            if (view()->exists($view)) {
                return response()->view($view, [], $code);
            }
            return response()->view('errors.500', [], $code);
        });

        // Catch-all: log and show professional 500 page
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'An unexpected error occurred. Please try again later.'], 500);
            }
            // Let Laravel handle known HTTP exceptions above; only catch true 500s here
            if (!($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException)) {
                \Illuminate\Support\Facades\Log::error('Unhandled exception: ' . $e->getMessage(), [
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                    'url'   => $request->fullUrl(),
                    'trace' => $e->getTraceAsString(),
                ]);
                if (!config('app.debug')) {
                    return response()->view('errors.500', [], 500);
                }
            }
        });

    })->create();
