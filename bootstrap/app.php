<?php

use App\Http\Middleware\CheckSubscriptionLimit;
use App\Http\Middleware\EnsureTenancyEnded;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyFromUser;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->web(append: [
            InitializeTenancyFromUser::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'subscription.limit' => CheckSubscriptionLimit::class,
            'ensure.tenancy.ended' => EnsureTenancyEnded::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            // Jika bukan local/testing ATAU jika app.debug=false (seperti saat testing ErrorPageTest)
            if ((! app()->environment(['local', 'testing']) || config('app.debug') === false) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                return Inertia::render('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            if ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })
    ->create();
