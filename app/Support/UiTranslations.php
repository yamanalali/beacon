<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class UiTranslations
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array<string, mixed>
     */
    public static function forLocale(string $locale): array
    {
        $locale = Locale::normalize($locale);

        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $path = lang_path($locale.'.json');

        if (! File::exists($path)) {
            return self::$cache[$locale] = [];
        }

        $decoded = json_decode(File::get($path), true);

        return self::$cache[$locale] = is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, string|int|float>  $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $dict = self::forLocale($locale ?? app()->getLocale());
        $value = $dict[$key] ?? null;

        if (! is_string($value)) {
            $value = $key;
        }

        foreach ($replace as $search => $replacement) {
            $value = str_replace('{'.$search.'}', (string) $replacement, $value);
        }

        return $value;
    }
}
