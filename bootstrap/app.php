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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\OfflineSupport::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom Arabic error pages (Inertia). JSON requests keep JSON errors.
        // 500/503 use the custom page only when APP_DEBUG is off; 422 validation is not handled here, so no technical details reach users.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if (in_array($status, [500, 503], true) && config('app.debug')) {
                return $response;
            }

            $user = $request->user();

            return \Inertia\Inertia::render('Errors/ErrorPage', [
                'status' => $status,
                'homeUrl' => $user ? route('home') : route('login'),
                'isGuest' => $user === null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
