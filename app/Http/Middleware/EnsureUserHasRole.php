<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(self::allows($request->user(), $roles), 403);

        return $next($request);
    }

    /**
     * The single check behind `role:...`; NavigationBuilder calls it for the same route parameters.
     *
     * @param  list<string>  $roles
     */
    public static function allows(mixed $user, array $roles): bool
    {
        return $user instanceof User
            && $user->isActive()
            && in_array($user->role->value, $roles, true);
    }
}
