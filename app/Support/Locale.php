<?php

namespace App\Support;

final class Locale
{
    public const SUPPORTED = ['ar', 'en'];

    public const DEFAULT = 'ar';

    public static function isSupported(?string $locale): bool
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED, true);
    }

    public static function normalize(?string $locale): string
    {
        return self::isSupported($locale) ? $locale : self::DEFAULT;
    }

    public static function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }
}
