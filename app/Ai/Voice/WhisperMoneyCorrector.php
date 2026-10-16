<?php

namespace App\Ai\Voice;

/**
 * Cheap post-correction of whisper.cpp mis-hearings of Levantine money words
 * (seen in scripts/whisper-benchmark.sh), applied before VoiceDraftParser.
 * Whole-token replacements only, so normal words are never touched.
 */
class WhisperMoneyCorrector
{
    /** Exact token => replacement. */
    private const TOKENS = [
        'الفا' => 'ألف', 'ألفا' => 'ألف', 'آلفا' => 'ألف', 'أفا' => 'ألف', 'آفا' => 'ألف', 'افا' => 'ألف',
        'الافا' => 'الاف', 'آلافا' => 'الاف',
        'لفليرا' => 'ألف ليرة', 'الفليرا' => 'ألف ليرة', 'للفليرة' => 'ألف ليرة', 'عالفليرا' => 'ألف ليرة', 'لفليرة' => 'ألف ليرة',
        'ليرا' => 'ليرة', 'ليره' => 'ليرة',
        'همس' => 'خمس', 'حمس' => 'خمس', 'حمسين' => 'خمسين', 'حمسي' => 'خمسين', 'خمسي' => 'خمسين', 'حمسيا' => 'خمسين', 'حموسيا' => 'خمسين',
        'مسمية' => 'خمس مية', 'مصمية' => 'خمس مية', 'خمسمية' => 'خمس مية',
        'تلاث' => 'تلات', 'تينين' => 'تنين',
        'أمليون' => 'مليون', 'امليون' => 'مليون', 'ماليون' => 'مليون', 'رماليون' => 'مليون',
        'دولا' => 'دولار', 'دولر' => 'دولار',
    ];

    /** «مياه» (water) right before a money word is «مية» (hundred). */
    private const MONEY_NEXT = ['ألف', 'الف', 'الاف', 'آلاف', 'ليرة', 'دولار'];

    public function correct(string $text): string
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($tokens as $tok) {
            [$word, $punct] = preg_match('/^(.*?)([،,.؟!]*)$/u', $tok, $m) ? [$m[1], $m[2]] : [$tok, ''];
            $out[] = [self::TOKENS[$word] ?? $word, $punct];
        }
        foreach ($out as $i => [$word, $punct]) {
            if ($word === 'مياه' && $punct === '' && isset($out[$i + 1]) && in_array(explode(' ', $out[$i + 1][0])[0], self::MONEY_NEXT, true)) {
                $out[$i][0] = 'مية';
            }
        }

        return implode(' ', array_map(fn ($p) => $p[0].$p[1], $out));
    }
}
