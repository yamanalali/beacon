<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\TwoFactor\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        // Demo credentials are shown ONLY in local development, never in production.
        $demo = app()->isLocal() && filled(config('demo.password'))
            ? [
                'password' => (string) config('demo.password'),
                'accounts' => collect(config('demo.accounts'))
                    ->map(fn (array $a): array => ['email' => $a['email'], 'role' => UserRole::from($a['role'])->label()])
                    ->values()
                    ->all(),
            ]
            : null;

        return Inertia::render('Auth/Login', ['demoAccounts' => $demo]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if ($user !== null && app(TwoFactorService::class)->challengeRequired($user)) {
            // Password is valid, but no session is granted until the second factor passes.
            Auth::guard('web')->logout();
            $request->session()->regenerate();
            $request->session()->put(TwoFactorChallengeController::SESSION_USER, $user->getKey());
            $request->session()->put(
                TwoFactorChallengeController::SESSION_EXPIRES,
                now()->addMinutes((int) config('mfa.challenge_ttl_minutes', 5))->timestamp,
            );

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        if (Route::has('home')) {
            return redirect()->intended(route('home'));
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
