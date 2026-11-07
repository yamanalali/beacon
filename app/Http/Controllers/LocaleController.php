<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', Locale::SUPPORTED)],
        ]);

        $locale = Locale::normalize($validated['locale']);

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);
        $request->attributes->set('locale', $locale);

        $user = $request->user();
        if ($user !== null) {
            $user->forceFill(['locale' => $locale])->save();
        }

        Cookie::queue(cookie(
            'locale',
            $locale,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'lax',
        ));

        return back();
    }
}
