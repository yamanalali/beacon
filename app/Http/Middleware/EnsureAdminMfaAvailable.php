<?php

namespace App\Http\Middleware;

use App\Support\TwoFactor\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Hides the MFA settings when the feature is off (404) and limits them to admin roles (403). */
class EnsureAdminMfaAvailable
{
    public function __construct(private readonly TwoFactorService $twoFactor)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->twoFactor->enabled(), 404);
        abort_unless($this->twoFactor->appliesTo($request->user()), 403);

        return $next($request);
    }

    /** Same outcome as handle() without aborting: feature on and the user's role is eligible. */
    public static function allows(mixed $user): bool
    {
        $twoFactor = app(TwoFactorService::class);

        return $twoFactor->enabled() && $twoFactor->appliesTo($user instanceof \App\Models\User ? $user : null);
    }
}
