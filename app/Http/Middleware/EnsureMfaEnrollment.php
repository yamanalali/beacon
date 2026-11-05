<?php

namespace App\Http\Middleware;

use App\Support\TwoFactor\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When MFA enrollment is enforced and the grace period has ended, privileged admins
 * who have not confirmed TOTP are locked to the MFA setup flow (and logout).
 */
class EnsureMfaEnrollment
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->twoFactor->enrollmentRequired($user)) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        return redirect()
            ->route('two-factor.show')
            ->with('error', 'يجب تفعيل التحقق بخطوتين للمتابعة. فترة السماح انتهت.');
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs([
            'two-factor.show',
            'two-factor.enable',
            'two-factor.confirm',
            'logout',
        ])) {
            return true;
        }

        return $request->is('account/two-factor', 'account/two-factor/*', 'logout');
    }
}
