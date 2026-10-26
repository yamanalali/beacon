<?php

namespace App\Http\Middleware;

use App\Support\AccountSecurity;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSessionCredentialsCurrent
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $request->hasSession()) {
            $stamp = $request->session()->get(AccountSecurity::SESSION_STAMP);
            $boundUser = $request->session()->get(AccountSecurity::SESSION_USER);
            if ((string) $boundUser === (string) $user->id && is_string($stamp) && ! hash_equals($stamp, AccountSecurity::stamp($user))) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                throw new AuthenticationException('انتهت صلاحية الجلسة بعد تحديث أمان الحساب.');
            }
            // Existing sessions acquire a baseline on first use after deployment.
            if ($stamp === null || (string) $boundUser !== (string) $user->id) {
                AccountSecurity::bind($request, $user);
            }
        }

        return $next($request);
    }
}
