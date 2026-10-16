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

    /** Words that are never a vendor name: dates, currencies, verbs, fillers. */
    private const NOT_VENDOR = [
        'اليوم', 'مبارح', 'امبارح', 'أمس', 'امس', 'البارحة', 'أول', 'اول', 'قبل', 'today', 'yesterday',
        'ليرة', 'ليرات', 'تركية', 'تركي', 'سورية', 'دولار', 'دولارات', 'يورو', 'درهم', 'دراهم', 'ريال', 'ريالات',
        'دفعت', 'قبضت', 'اشتريت', 'شريت', 'بعت', 'صرفت', 'استلمت', 'وصلني', 'حطيت',
        'أجار', 'اجار', 'إيجار', 'ايجار', 'محل', 'لمحل', 'المحل', 'ب', 'بـ', 'من', 'عند', 'على', 'في', 'هاد', 'هذا',
    ];

    private function cleanVendor(string $candidate): ?string
    {
        $words = array_filter(preg_split('/\s+/u', trim($candidate)) ?: [], fn ($w) => $w !== ''
            && ! in_array($w, self::NOT_VENDOR, true) && ! preg_match('/\d/u', $w));
        $name = trim(implode(' ', $words));

        return mb_strlen($name) >= 2 ? mb_substr($name, 0, 150) : null;
    }

    /** @return array{amount:?string,date:?string,counterparty:?string,currency:?string,note:?string,confidence:float} */
    /** "لمدة ثلاثة اشهر", "مدة شهرين", "لمدة سنة"... goes to the note only (manager decision). */
    private const PERIOD = '/(?:لمدة|لمده|مدة|مده|عن\s+مدة)\s+(?:(?:[\p{L}\d]+)\s+)?(?:شهر|شهرين|أشهر|اشهر|شهور|سنة|سنه|سنتين|سنوات|سنين|أسبوع|اسبوع|أسبوعين|اسبوعين|أسابيع|اسابيع|يوم|يومين|أيام|ايام)(?=\s|[\p{P}]|$)/u';

    public function parse(string $text): array
    {
        // A period phrase is kept verbatim as a note and removed before reading the
        // amount, so "3 اشهر" never becomes or multiplies the amount.
        $note = preg_match(self::PERIOD, $text, $pm) ? trim($pm[0]) : null;
        $t = (new SpokenNumberNormalizer)->normalize($note !== null ? str_replace($pm[0], ' ', $text) : $text);

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
            $counterparty = $this->cleanVendor($c[1]);
        }

        $currency = null;
        foreach (self::CURRENCIES as $code => $re) {
            if (preg_match($re, $t)) {
                $currency = $code;
                break;
            }
        }

        $found = count(array_filter([$amount, $date, $counterparty]));

        return compact('amount', 'date', 'counterparty', 'currency', 'note') + ['confidence' => round(min(0.5, 0.15 * $found), 2)];
    }
}
