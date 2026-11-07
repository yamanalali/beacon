<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Soft-gates SME users to signature onboarding until completed or skipped. */
class EnsureSignatureOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== UserRole::SmeUser) {
            return $next($request);
        }

        if ($user->signature_path || $user->signature_skipped_at) {
            return $next($request);
        }

        // Keep API/JSON clients on their authorization path; only gate browser navigation.
        if ($request->expectsJson() || $this->isExempt($request)) {
            return $next($request);
        }

        return redirect()->route('onboarding.show');
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs([
            'onboarding.show',
            'onboarding.signature',
            'onboarding.skip',
            'logout',
            'locale.update',
        ])) {
            return true;
        }

        return $request->is('onboarding', 'onboarding/*', 'logout', 'locale');
    }
}
