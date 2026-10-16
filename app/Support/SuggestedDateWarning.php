<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Non-blocking hint for the review form when an OCR/voice suggested date looks wrong:
 * older than config('ai.review.old_date_days') days, or in the future. Never blocks saving.
 */
final class SuggestedDateWarning
{
    public static function for(?string $date, ?CarbonImmutable $today = null): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        try {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        } catch (Throwable) {
            return null;
        }
        if ($d === false) {
            return null;
        }
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        if ($d->greaterThan($today)) {
            return 'التاريخ المقترح ('.$d->toDateString().') بالمستقبل، تأكد منه قبل الحفظ.';
        }
        $days = max(1, (int) config('ai.review.old_date_days', 365));
        if ($d->lessThan($today->subDays($days))) {
            return 'التاريخ المقترح ('.$d->toDateString().') قديم كتير، تأكد منه قبل الحفظ.';
        }

        return null;
    }
}
