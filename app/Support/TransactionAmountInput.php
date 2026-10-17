<?php

namespace App\Support;

class TransactionAmountInput
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $translated = strtr(trim($value), array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹٫٬', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789.,'),
        ));
        if (str_contains($translated, ',') && ! preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $translated)) {
            return $translated;
        }

        return str_replace(',', '', $translated);
    }
}
