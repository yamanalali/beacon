<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Section 53 "encrypted transmission": redirect to HTTPS and send HSTS when BEACON_FORCE_HTTPS is on. */
class EnforceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('operations.force_https')) {
            return $next($request);
        }
        if (! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }
        $response = $next($request);
        $response->headers->set('Strict-Transport-Security', 'max-age='.(int) config('operations.hsts_max_age').'; includeSubDomains');

        return $response;
    }
}
