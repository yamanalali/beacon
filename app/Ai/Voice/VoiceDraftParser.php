<?php

namespace App\Ai\Voice;

/**
 * Heuristic: transcript -> draft transaction suggestion. Income/expense comes
 * from the shared App\Finance\TransactionTypeSuggester, not from here.
 * (low confidence, review only).
 * TODO: BUSINESS DECISION REQUIRED — spoken-number words ("خمس آلاف"), dialect
 * vocabulary and the confidence threshold. Digits only for now.
 */
class VoiceDraftParser
{
    private const CURRENCIES = ['SYP' => '/(ليرة|ليرات|ل\.?\s?س|SYP)/iu', 'USD' => '/(دولار|\$|USD)/iu', 'EUR' => '/(يورو|€|EUR)/iu', 'TRY' => '/(تركي|₺|TRY)/iu'];

    /** @return array{amount:?string,date:?string,counterparty:?string,currency:?string,confidence:float} */
    public function parse(string $text): array
    {
        $t = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => ',']);

        $amount = null;
        if (preg_match_all('/\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?/u', $t, $m)) {
            $nums = array_filter(array_map(fn ($n) => (float) str_replace(',', '', $n), $m[0]), fn ($n) => $n > 0);
            if ($nums) {
                $amount = rtrim(rtrim(number_format(max($nums), 2, '.', ''), '0'), '.');
            }
        }

        $date = match (true) {
            (bool) preg_match('/(مبارح|امبارح|أمس|البارحة|yesterday)/iu', $t) => now()->subDay()->toDateString(),
            (bool) preg_match('/(اليوم|today)/iu', $t) => now()->toDateString(),
            default => null,
        };

        $counterparty = null;
        if (preg_match('/(?:لمحل|محل|من عند|عند|لـ|إلى|الى|من)\s+([\p{L}][\p{L}\s]{1,40}?)(?=\s+(?:أجار|اجار|ب|بـ|\d)|[\.,،]|$)/u', $t, $c)) {
            $counterparty = mb_substr(trim($c[1]), 0, 150);
        }

        $currency = null;
        foreach (self::CURRENCIES as $code => $re) {
            if (preg_match($re, $t)) {
                $currency = $code;
                break;
            }
        }

        $found = count(array_filter([$amount, $date, $counterparty]));

        return compact('amount', 'date', 'counterparty', 'currency') + ['confidence' => round(min(0.5, 0.15 * $found), 2)];
    }
}
