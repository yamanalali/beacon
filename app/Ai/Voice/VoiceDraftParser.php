<?php

namespace App\Ai\Voice;

/**
 * Heuristic: transcript -> draft transaction suggestion. Income/expense comes
 * from the shared App\Finance\TransactionTypeSuggester, not from here.
 * (low confidence, review only).
 * Spoken Arabic numbers (MSA + Levantine) are converted by SpokenNumberNormalizer.
 * Currency and date only when explicitly said; otherwise null (no defaults).
 * TODO: BUSINESS DECISION REQUIRED — dialect vocabulary coverage and the
 * confidence threshold.
 */
class VoiceDraftParser
{
    /** Checked in order; "ليرة تركية" before plain "ليرة". Codes outside bookkeeping.currencies are dropped by the caller. */
    private const CURRENCIES = [
        'TRY' => '/((ليرة|ليرات)\s*(تركية|تركي|تركيه)|₺|\bTRY\b)/iu',
        'SYP' => '/(ليرة|ليرات|ل\.\s?س|\bSYP\b)/iu',
        'USD' => '/(دولار|دولارات|\$|\bUSD\b)/iu',
        'EUR' => '/(يورو|€|\bEUR\b)/iu',
        'AED' => '/(درهم|دراهم|\bAED\b)/iu',
        'SAR' => '/(ريال|ريالات|\bSAR\b)/iu',
    ];

    /** @return array{amount:?string,date:?string,counterparty:?string,currency:?string,confidence:float} */
    public function parse(string $text): array
    {
        $t = (new SpokenNumberNormalizer)->normalize($text);

        $amount = null;
        if (preg_match_all('/\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?/u', $t, $m)) {
            $nums = array_filter(array_map(fn ($n) => (float) str_replace(',', '', $n), $m[0]), fn ($n) => $n > 0);
            if ($nums) {
                $amount = rtrim(rtrim(number_format(max($nums), 2, '.', ''), '0'), '.');
            }
        }

        $date = match (true) {
            (bool) preg_match('/(أول\s*(امبارح|مبارح|البارحة|أمس|امس)|اول\s*(امبارح|مبارح|امس)|قبل\s*امبارح|قبل\s*أمس)/iu', $t) => now()->subDays(2)->toDateString(),
            (bool) preg_match('/(مبارح|امبارح|أمس|امس|البارحة|yesterday)/iu', $t) => now()->subDay()->toDateString(),
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
