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
        // Tolerant to whisper spellings: ليره، ليرا، ليرائي (ليرة+اجار glued), standalone لير.
        'SYP' => '/((?<![\p{L}])(?:ليرة|ليره|ليرات|ليرا|ليرائي|لير)(?![\p{L}])|ل\.\s?س|\bSYP\b)/iu',
        'USD' => '/(دولار|دولارات|\$|\bUSD\b)/iu',
        'EUR' => '/(يورو|€|\bEUR\b)/iu',
        'AED' => '/(درهم|دراهم|\bAED\b)/iu',
        'SAR' => '/(ريال|ريالات|\bSAR\b)/iu',
    ];

    /** Words that are never a vendor name: dates, currencies, verbs, fillers. */
    private const NOT_VENDOR = [
        'اليوم', 'مبارح', 'امبارح', 'أمس', 'امس', 'البارحة', 'أول', 'اول', 'قبل', 'today', 'yesterday',
        'ليرة', 'ليرات', 'تركية', 'تركي', 'سورية', 'دولار', 'دولارات', 'يورو', 'درهم', 'دراهم', 'ريال', 'ريالات',
        // Generic people words are never a vendor (decision 2026-10-16: «قبضت مية الف من زبون» => null).
        'زبون', 'الزبون', 'لزبون', 'زباين', 'الزباين', 'زبائن', 'الزبائن', 'عميل', 'العميل', 'لعميل', 'عملاء', 'العملاء',
        'شخص', 'الشخص', 'لشخص', 'واحد', 'لواحد', 'حدا', 'لحدا', 'أحد', 'احد', 'زلمة', 'رجال', 'مرا',
        'دفعت', 'قبضت', 'اشتريت', 'شريت', 'بعت', 'صرفت', 'استلمت', 'وصلني', 'حطيت',
        'ليره', 'ليرا', 'ليرائي', 'لير', 'جر', 'ئي', 'شقة', 'بيت', 'مكتب', 'عن', 'لـ', 'ل', 'الى', 'إلى', 'مع', 'لمدة', 'مدة', 'و', 'يا', 'أجار', 'اجار', 'إيجار', 'ايجار', 'محل', 'لمحل', 'المحل', 'ب', 'بـ', 'من', 'عند', 'على', 'في', 'هاد', 'هذا',
    ];

    private function cleanVendor(string $candidate): ?string
    {
        $words = array_filter(preg_split('/\s+/u', trim($candidate)) ?: [], fn ($w) => $w !== ''
            && ! in_array($w, self::NOT_VENDOR, true) && ! preg_match('/\d/u', $w));
        $name = trim(implode(' ', $words));
        // A lone short token (e.g. «عن») is never a name.
        if (count($words) === 1 && mb_strlen($name) <= 2) {
            return null;
        }

        return mb_strlen($name) >= 2 ? mb_substr($name, 0, 150) : null;
    }

    /** @return array{amount:?string,date:?string,counterparty:?string,currency:?string,note:?string,normalized:string,items:list<array>,items_mismatch:bool,amount_warning:?string,confidence:float} */
    /** "لمدة ثلاثة اشهر", "مدة شهرين", "لمدة سنة"... goes to the note only (manager decision). */
    private const PERIOD = '/(?<![\p{L}])(?:لمدة|لمده|مدة|مده|عن\s+مدة|عن|ل)\s+(?:(?:[\p{L}\d]+)\s+)?(?:شهر|شهرين|أشهر|اشهر|شهور|سنة|سنه|سنتين|سنوات|سنين|أسبوع|اسبوع|أسبوعين|اسبوعين|أسابيع|اسابيع|يوم|يومين|أيام|ايام)(?=\s|[\p{P}]|$)/u';

    /** «ثلاثه شهور» / «3 اشهر» without «لمدة»: a count word right before a plural period. */
    private const BARE_PERIOD = '/(?<![\p{L}])(?:\d+|واحد|تنين|اتنين|اثنين|تلات|تلاتة|تلاته|ثلاث|ثلاثة|ثلاثه|اربع|أربع|اربعة|أربعة|اربعه|خمس|خمسة|خمسه|ست|ستة|سته|سبع|سبعة|سبعه|تمن|تمنية|ثمانية|ثمانيه|تسع|تسعة|تسعه|عشر|عشرة|عشره)\s+(?:شهور|أشهر|اشهر|سنين|سنوات|أسابيع|اسابيع|ايام|أيام)(?=\s|[\p{P}]|$)/u';

    /**
     * Voice-only fixes for whisper splitting «ليرة اجار» into «ليرائي جر»:
     * «جر» / «ئي جر» after a lira word, or «جر» before محل/بيت/شقة/مكتب, becomes «اجار»
     * so the shared category suggesters see rent. Receipt categorizer untouched.
     */
    public function normalizeTerms(string $text): string
    {
        $t = preg_replace('/(?<![\p{L}])(ليرائي|ليرا|ليره|ليرة|ليرات|لير)\s+(?:ئي\s+)?جر(?![\p{L}])/u', '$1 اجار', $text);
        $t = preg_replace('/(?<![\p{L}])جر(?=\s+(?:محل|المحل|بيت|البيت|شقة|الشقة|مكتب|المكتب)(?![\p{L}]))/u', 'اجار', $t);

        return $t;
    }

    public function parse(string $text): array
    {
        $text = $this->normalizeTerms($text);
        $normalized = $text;
        // A period phrase is kept verbatim as a note and removed before reading the
        // amount, so "3 اشهر" never becomes or multiplies the amount.
        $note = preg_match(self::PERIOD, $text, $pm) || preg_match(self::BARE_PERIOD, $text, $pm) ? trim($pm[0]) : null;
        $normalizer = new SpokenNumberNormalizer;
        $t = $normalizer->normalize($note !== null ? str_replace($pm[0], ' ', $text) : $text);
        $ambiguous = $normalizer->ambiguous;

        $amount = null;
        $nums = [];
        if (preg_match_all('/\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?/u', $t, $m)) {
            $nums = array_values(array_filter(array_map(fn ($n) => (float) str_replace(',', '', $n), $m[0]), fn ($n) => $n > 0));
            if ($nums) {
                $amount = max($nums);
            }
        }

        [$items, $used] = $this->items($text, $t, $note);
        $itemsMismatch = false;
        if ($items !== []) {
            $sum = array_sum(array_column($items, 'total'));
            foreach ($used as $u) {
                $k = array_search($u, $nums, true);
                if ($k !== false) {
                    unset($nums[$k]);
                }
            }
            // A remaining spoken number is the stated total; otherwise the amount is the items' sum.
            $amount = $nums ? max($nums) : $sum;
        }

        $date = match (true) {
            (bool) preg_match('/(أول\s*(امبارح|مبارح|البارحة|أمس|امس)|اول\s*(امبارح|مبارح|امس)|قبل\s*امبارح|قبل\s*أمس)/iu', $t) => now()->subDays(2)->toDateString(),
            (bool) preg_match('/(مبارح|امبارح|أمس|امس|البارحة|yesterday)/iu', $t) => now()->subDay()->toDateString(),
            (bool) preg_match('/(اليوم|today)/iu', $t) => now()->toDateString(),
            default => null,
        };

        $counterparty = null;
        if (preg_match('/(?:لمحل|محل|من عند|عند|لـ|إلى|الى|من)\s+([\p{L}][\p{L}\s]{1,40}?)(?=\s+(?:أجار|اجار|ب|بـ|اليوم|مبارح|امبارح|امس|أمس|\d)|[\.,،]|$)/u', $t, $c)) {
            $counterparty = $this->cleanVendor($c[1]);
        }
        // «لابو سمير»: «ل» glued to a title. Only known titles, so «ليرة»/«لمدة» are never names.
        if ($counterparty === null && preg_match('/(?<![\p{L}])ل((?:ابو|أبو|ام|أم|استاذ|أستاذ|دكتور|الدكتور|عم|خال|حجي|الحج)\s+[\p{L}]{2,20})(?![\p{L}])/u', $t, $c)) {
            $counterparty = $this->cleanVendor($c[1]);
        }

        $currency = null;
        foreach (self::CURRENCIES as $code => $re) {
            if (preg_match($re, $t)) {
                $currency = $code;
                break;
            }
        }

        if ($items !== []) {
            // Same tolerance as FinancialTransaction::itemsMismatch (SYP 0.5, others 0.005).
            $tol = ($currency === null || $currency === 'SYP') ? 0.5 : 0.005;
            $itemsMismatch = abs($sum - $amount) > $tol;
        }
        $amount = $amount !== null ? rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') : null;
        $amount_warning = match (true) {
            $itemsMismatch => 'تأكد من المبلغ: مجموع البنود ('.$this->fmt($sum).') لا يساوي المبلغ المذكور ('.$amount.').',
            // «3 مية ألف» (often whisper's «3 مياه ألف»): digits before «مية» are easy to mis-hear.
            (bool) preg_match('/(?<![\d٠-٩])[\d٠-٩]+\s+مي(?:ة|ه|ات)\s+(?:ألف|الف|آلاف|الاف)/u', $normalized) => 'تأكد من المبلغ: «رقم + مية ألف» ممكن تكون انسمعت غلط، واعتُبر المبلغ '.$amount.'.',
            // «3 شهور، كل شهر 1000» → 3000 (manager 2026-10-16): computed, so ask to confirm.
            $items !== [] && str_contains($items[0]['description'] ?? '', '(') && $items[0]['quantity'] !== null => 'تأكد من المبلغ: انحسب '.$this->fmt((float) $items[0]['quantity']).' × '.$this->fmt((float) $items[0]['unit_price']).' = '.$amount.'.',
            $ambiguous => 'تأكد من المبلغ: صيغة الرقم مع «آلاف» غير واضحة، واعتُبر المبلغ '.$amount.'.',
            default => null,
        };
        $items_mismatch = $itemsMismatch;

        $found = count(array_filter([$amount, $date, $counterparty]));

        return compact('amount', 'date', 'counterparty', 'currency', 'note', 'normalized', 'items', 'items_mismatch', 'amount_warning') + ['confidence' => round(min(0.5, 0.15 * $found), 2)];
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private function num(float $n): int|float
    {
        return floor($n) == $n ? (int) $n : $n;
    }

    private const RECURRING = '/(?<![\p{L}])(?:كل\s+(شهر|أسبوع|اسبوع|جمعة|سنة|سنه|يوم)|(بالشهر|بالأسبوع|بالاسبوع|بالسنة|بالسنه|باليوم))\s+(?:(?:هو|هي|حقه|حقو|سعره|سعرو|بـ|ب)\s+)?(\d+(?:\.\d+)?)/u';

    private const PERIOD_LABEL = ['شهر' => 'شهري', 'أسبوع' => 'أسبوعي', 'اسبوع' => 'أسبوعي', 'جمعة' => 'أسبوعي', 'سنة' => 'سنوي', 'سنه' => 'سنوي', 'يوم' => 'يومي'];

    /**
     * Suggested line items in the OCR shape {description, quantity, unit_price, total}.
     * 1) «كل شهر X» + period «لمدة N ...» → ONE item (qty N, unit X); the transaction stays one.
     * 2) «2 كرسي بـ500 وطاولة بـ1000» → one item per good; «بـX» after a quantity is the UNIT price.
     *    Decided: unit price, not total (BEACON-DECISIONS.md, 2026-10-16, «2 كرسي بـ500» = سعر الوحدة).
     *
     * @return array{0: list<array{description:?string,quantity:int|float|null,unit_price:int|float|null,total:int|float|null}>, 1: list<float>}
     */
    private function items(string $text, string $t, ?string $note): array
    {
        if (preg_match(self::RECURRING, $t, $r)) {
            $unitWord = $r[1] !== '' ? $r[1] : mb_substr($r[2], 3);
            $price = (float) $r[3];
            $qty = $note !== null ? $this->periodCount($note) : null;
            $rent = preg_match('/(?<![\p{L}])(?:إيجار|ايجار|اجار|أجار|آجار)(?:\s+(محل|المحل|بيت|البيت|شقة|الشقة|مكتب|المكتب))?/u', $text, $rm);
            $desc = $rent ? trim('إيجار '.preg_replace('/^ال/u', '', $rm[1] ?? '')) : 'دفعة';
            $desc .= ' ('.(self::PERIOD_LABEL[$unitWord] ?? 'دوري').')';

            return [[[
                'description' => $desc,
                'quantity' => $qty !== null ? $this->num($qty) : null,
                'unit_price' => $this->num($price),
                'total' => $qty !== null ? $this->num($qty * $price) : null,
            ]], [$price]];
        }

        $items = [];
        $used = [];
        if (preg_match_all('/(?:(\d+(?:\.\d+)?)\s+)?([\p{L}]{2,})\s*بـ?\s*(\d+(?:\.\d+)?)/u', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $g) {
                $word = $g[2];
                if (str_starts_with($word, 'و') && mb_strlen($word) > 3) {
                    $word = mb_substr($word, 1);
                }
                if (in_array($word, self::NOT_VENDOR, true) || preg_match('/^(?:ليرة|ليرات|دولار|يورو|درهم|ريال)/u', $word)) {
                    continue;
                }
                $qty = $g[1] !== '' ? (float) $g[1] : 1.0;
                $price = (float) $g[3];
                $items[] = ['description' => $word, 'quantity' => $this->num($qty), 'unit_price' => $this->num($price), 'total' => $this->num($qty * $price)];
                if ($g[1] !== '') {
                    $used[] = $qty;
                }
                $used[] = $price;
            }
        }

        return [$items, $used];
    }

    /** «3 اشهر» → 3, «ثلاثة اشهر» → 3, «شهرين» → 2, «لمدة سنة» → 1. */
    private function periodCount(string $note): ?float
    {
        $n = (new SpokenNumberNormalizer)->normalize($note);
        if (preg_match('/\d+(?:\.\d+)?/u', $n, $d)) {
            return (float) $d[0];
        }
        if (preg_match('/(?:شهرين|سنتين|أسبوعين|اسبوعين|يومين)/u', $n)) {
            return 2.0;
        }
        if (preg_match('/(?<![\p{L}])(?:شهر|سنة|سنه|أسبوع|اسبوع|يوم)(?![\p{L}])/u', $n)) {
            return 1.0;
        }

        return null;
    }
}
