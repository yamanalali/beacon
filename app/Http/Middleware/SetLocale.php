<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::DEFAULT;

        $user = $request->user();
        if ($user !== null && Locale::isSupported($user->locale ?? null)) {
            $locale = $user->locale;
        } elseif (Locale::isSupported($request->session()->get('locale'))) {
            $locale = (string) $request->session()->get('locale');
        } elseif (Locale::isSupported($request->cookie('locale'))) {
            $locale = (string) $request->cookie('locale');
        } else {
            $locale = Locale::normalize(config('app.locale'));
        }

        app()->setLocale($locale);
        $request->attributes->set('locale', $locale);

        return $next($request);
    }
}
