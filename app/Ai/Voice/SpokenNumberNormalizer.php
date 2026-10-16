<?php

namespace App\Ai\Voice;

/**
 * Turns spoken Arabic numbers (MSA + Levantine) in a transcript into digits,
 * e.g. "خمسين ألف" → 50000, "ميتين وخمسين" → 250, "مليون ونص" → 1500000.
 * Arabic-Indic digits are converted too. Pure text transform; no business rules.
 */
class SpokenNumberNormalizer
{
    private const UNITS = [
        'صفر' => 0, 'واحد' => 1, 'وحدة' => 1, 'واحدة' => 1,
        'اثنين' => 2, 'إثنين' => 2, 'اتنين' => 2, 'تنين' => 2, 'اثنان' => 2, 'ثنتين' => 2,
        'ثلاث' => 3, 'ثلاثة' => 3, 'تلات' => 3, 'تلاتة' => 3, 'تلاته' => 3, 'ثلاثه' => 3,
        'أربع' => 4, 'اربع' => 4, 'أربعة' => 4, 'اربعة' => 4, 'اربعه' => 4,
        'خمس' => 5, 'خمسة' => 5, 'خمسه' => 5,
        'ست' => 6, 'ستة' => 6, 'سته' => 6, 'سِت' => 6,
        'سبع' => 7, 'سبعة' => 7, 'سبعه' => 7,
        'ثمان' => 8, 'ثماني' => 8, 'ثمانية' => 8, 'تمان' => 8, 'تمانية' => 8, 'تمانيه' => 8, 'تمن' => 8,
        'تسع' => 9, 'تسعة' => 9, 'تسعه' => 9,
        'عشر' => 10, 'عشرة' => 10, 'عشره' => 10,
        'احدعش' => 11, 'حداعش' => 11, 'اطنعش' => 12, 'اتنعش' => 12, 'تلطعش' => 13, 'تلتطعش' => 13,
        'اربعطعش' => 14, 'خمسطعش' => 15, 'ستطعش' => 16, 'سبعطعش' => 17, 'تمنطعش' => 18, 'تسعطعش' => 19,
        'عشرين' => 20, 'عشرون' => 20, 'ثلاثين' => 30, 'تلاتين' => 30, 'ثلاثون' => 30,
        'أربعين' => 40, 'اربعين' => 40, 'أربعون' => 40, 'خمسين' => 50, 'خمسون' => 50,
        'ستين' => 60, 'ستون' => 60, 'سبعين' => 70, 'سبعون' => 70,
        'ثمانين' => 80, 'تمانين' => 80, 'ثمانون' => 80, 'تسعين' => 90, 'تسعون' => 90,
    ];

    private const HUNDREDS = ['مية' => 100, 'مئة' => 100, 'ميه' => 100, 'مائة' => 100, 'ميت' => 100,
        'ميتين' => 200, 'مئتين' => 200, 'مائتين' => 200, 'مئتان' => 200, 'ميتان' => 200];

    private const SCALES = ['ألف' => 1000, 'الف' => 1000, 'آلاف' => 1000, 'الاف' => 1000, 'ألاف' => 1000, 'آلف' => 1000,
        'ألفين' => 2000, 'الفين' => 2000, 'ألفان' => 2000,
        'مليون' => 1000000, 'ملايين' => 1000000, 'مليونين' => 2000000, 'مليونان' => 2000000];

    private const FRACTIONS = ['نص' => 0.5, 'نصف' => 0.5, 'ربع' => 0.25];

    public static function digits(string $s): string
    {
        return strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.', '٬' => ',']);
    }

    /** True after normalize() when a full number was followed by «الاف» (e.g. «3000 الاف»): not multiplied, ambiguous. */
    public bool $ambiguous = false;

    public function normalize(string $text): string
    {
        $this->ambiguous = false;
        $tokens = preg_split('/(\s+)/u', self::digits($text), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = [];
        $i = 0;
        $n = count($tokens);
        while ($i < $n) {
            if (trim($tokens[$i]) === '') {
                $out[] = $tokens[$i];
                $i++;

                continue;
            }
            [$value, $next] = $this->readNumber($tokens, $i);
            if ($value === null) {
                $out[] = $tokens[$i];
                $i++;

                continue;
            }
            $out[] = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
            $i = $next;
        }

        return implode('', $out);
    }

    /** @return array{0:?float,1:int} value and index after the number phrase */
    private function readNumber(array $tokens, int $start): array
    {
        $total = 0.0;
        $current = 0.0;
        $lastScale = 1;
        $consumed = 0;
        $i = $start;
        $end = $start;
        while ($i < count($tokens)) {
            $raw = $tokens[$i];
            if (trim($raw) === '') {
                $i++;

                continue;
            }
            $w = preg_replace('/[\p{P}]+$/u', '', $raw);
            $joined = $consumed > 0 && str_starts_with($w, 'و') && mb_strlen($w) > 1;
            $word = $joined ? mb_substr($w, 1) : $w;
            $v = $this->value($word);
            if ($v === null) {
                break;
            }
            [$kind, $num] = $v;
            if ($kind === 'digit' && $consumed > 0 && $current > 0) {
                break; // two separate numbers
            }
            switch ($kind) {
                case 'digit':
                case 'unit':
                    if ($kind === 'unit' && $num === 10 && $current > 0 && $current < 10) {
                        $current += 10; // MSA teen: "خمسة عشر"
                    } else {
                        $current += $num;
                    }
                    break;
                case 'hundred':
                    $current = $num === 100 && $current > 0 && $current < 10 ? $current * 100 : $current + $num;
                    break;
                case 'compound_hundred':
                    $current += $num;
                    break;
                case 'scale':
                    // QA fix: «3000 الاف» is 3000, not 3,000,000 — a number already >= 1000
                    // before a thousand-word is taken as written (flagged as ambiguous).
                    if ($num === 1000 && $current >= 1000) {
                        $total += $current;
                        $current = 0;
                        $lastScale = 1000;
                        $this->ambiguous = true;
                        break;
                    }
                    $mult = in_array($num, [2000, 2000000], true) ? 1 : ($current > 0 ? $current : 1);
                    $total += $mult * $num;
                    $current = 0;
                    $lastScale = $num >= 1000000 ? 1000000 : 1000;
                    break;
                case 'fraction':
                    if ($consumed === 0) {
                        $current = $num; // "ربع مليون", "نص مليون": only kept if a scale follows
                    } elseif ($current > 0) {
                        $current += $num;
                    } else {
                        $total += $num * $lastScale;
                    }
                    break;
            }
            $consumed++;
            $i++;
            $end = $i;
            if (preg_match('/[\p{P}]$/u', $raw)) {
                break;
            }
        }
        if ($consumed === 0) {
            return [null, $start + 1];
        }
        // a lone digit token or a lone fraction word ("نص كيلو") is left as-is
        if ($consumed === 1 && ($total + $current < 1 || preg_match('/^\d/', $tokens[$start]))) {
            return [null, $start + 1];
        }

        return [$total + $current, $end];
    }

    /** @return array{0:string,1:float|int}|null */
    private function value(string $w): ?array
    {
        if (preg_match('/^\d+(?:[.,]\d+)?$/', $w)) {
            return ['digit', (float) str_replace(',', '', $w)];
        }
        if (isset(self::UNITS[$w])) {
            return ['unit', self::UNITS[$w]];
        }
        if (isset(self::HUNDREDS[$w])) {
            return ['hundred', self::HUNDREDS[$w]];
        }
        if (isset(self::SCALES[$w])) {
            return ['scale', self::SCALES[$w]];
        }
        if (isset(self::FRACTIONS[$w])) {
            return ['fraction', self::FRACTIONS[$w]];
        }
        // "تلتمية", "خمسمية", "ثلاثمئة", "سبعمائة"...
        if (preg_match('/^(.+?)(مية|مئة|مائة|ميه)$/u', $w, $m)) {
            $prefix = ['تلت' => 3, 'ثلاث' => 3, 'تلات' => 3, 'ربع' => 4, 'اربع' => 4, 'أربع' => 4, 'خمس' => 5, 'ست' => 6, 'سبع' => 7, 'تمن' => 8, 'ثمان' => 8, 'ثمن' => 8, 'تسع' => 9][$m[1]] ?? null;
            if ($prefix !== null) {
                return ['compound_hundred', $prefix * 100];
            }
        }

        return null;
    }
}
