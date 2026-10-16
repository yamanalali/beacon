<?php

namespace App\Ai\Voice;

/**
 * Cheap post-correction of whisper.cpp mis-hearings of Levantine money words
 * (seen in scripts/whisper-benchmark.sh), applied before VoiceDraftParser.
 * Whole-token replacements. Real words that are also mis-hearings («همس»,
 * «مياه») are changed only next to a number or money word.
 */
class WhisperMoneyCorrector
{
    /** Exact token => replacement. */
    private const TOKENS = [
        'الفا' => 'ألف', 'ألفا' => 'ألف', 'آلفا' => 'ألف', 'أفا' => 'ألف', 'آفا' => 'ألف', 'افا' => 'ألف',
        'الافا' => 'الاف', 'آلافا' => 'الاف',
        'لفليرا' => 'ألف ليرة', 'الفليرا' => 'ألف ليرة', 'للفليرة' => 'ألف ليرة', 'عالفليرا' => 'ألف ليرة', 'لفليرة' => 'ألف ليرة',
        'ليرا' => 'ليرة', 'ليره' => 'ليرة',
        'حمس' => 'خمس', 'حمسين' => 'خمسين', 'حمسي' => 'خمسين', 'خمسي' => 'خمسين', 'حمسيا' => 'خمسين', 'حموسيا' => 'خمسين',
        'مسمية' => 'خمس مية', 'مصمية' => 'خمس مية', 'خمسمية' => 'خمس مية',
        'تلاث' => 'تلات', 'تينين' => 'تنين',
        'أمليون' => 'مليون', 'امليون' => 'مليون', 'ماليون' => 'مليون', 'رماليون' => 'مليون',
        'دولا' => 'دولار', 'دولر' => 'دولار',
    ];

    /** Money words after «مية»/«خمس». */
    private const MONEY_NEXT = ['ألف', 'الف', 'الاف', 'آلاف', 'ليرة', 'دولار', 'مية', 'مليون'];

    /** Number words that may precede «مية» (hundred). */
    private const NUMBERS = ['واحد', 'تنين', 'اتنين', 'تلات', 'تلاث', 'اربع', 'أربع', 'خمس', 'ست', 'سبع', 'تمن', 'تسع', 'عشر'];

    private static function isNumber(string $w): bool
    {
        return preg_match('/^[\d٠-٩]+$/u', $w) === 1 || in_array($w, self::NUMBERS, true);
    }

    private static function isMoney(string $w): bool
    {
        return in_array(explode(' ', $w)[0], self::MONEY_NEXT, true);
    }

    public function correct(string $text): string
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($tokens as $tok) {
            [$word, $punct] = preg_match('/^(.*?)([،,.؟!]*)$/u', $tok, $m) ? [$m[1], $m[2]] : [$tok, ''];
            $out[] = [self::TOKENS[$word] ?? $word, $punct];
        }
        foreach ($out as $i => [$word, $punct]) {
            $prev = $out[$i - 1][0] ?? '';
            $next = $punct === '' ? ($out[$i + 1][0] ?? '') : '';
            // «خمس مياه ألف» = 500 thousand, but «فاتورة مياه ألف ليرة» stays water.
            if ($word === 'مياه' && self::isNumber($prev) && self::isMoney($next)) {
                $out[$i][0] = 'مية';
            }
            // «همس» (whisper) is a real word: only «همس مية/ألف» means «خمس».
            if ($word === 'همس' && (self::isMoney($next) || ($next === 'مياه' && self::isMoney($out[$i + 2][0] ?? '')))) {
                $out[$i][0] = 'خمس';
            }
        }

        return implode(' ', array_map(fn ($p) => $p[0].$p[1], $out));
    }
}
