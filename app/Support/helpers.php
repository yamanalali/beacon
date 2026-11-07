<?php

use App\Support\UiTranslations;

if (! function_exists('ui')) {
    /**
     * Resolve a UI string from lang/{locale}.json (flat dotted keys).
     *
     * @param  array<string, string|int|float>  $replace
     */
    function ui(string $key, array $replace = [], ?string $locale = null): string
    {
        return UiTranslations::get($key, $replace, $locale);
    }
}
