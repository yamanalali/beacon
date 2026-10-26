<?php

use App\Http\Middleware\EnsureSessionCredentialsCurrent;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\OfflineSupport;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
            EnsureUserIsActive::class,
            EnsureSessionCredentialsCurrent::class,
            OfflineSupport::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code', 'recovery_code', 'token']);

        // Custom Arabic error pages (Inertia). JSON requests keep JSON errors.
        // 500/503 use the custom page only when APP_DEBUG is off; 422 validation is not handled here, so no technical details reach users.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if (in_array($status, [500, 503], true) && config('app.debug')) {
                return $response;
            }

            $user = $request->user();

            return Inertia::render('Errors/ErrorPage', [
                'status' => $status,
                'homeUrl' => $user ? route('home') : route('login'),
                'isGuest' => $user === null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
