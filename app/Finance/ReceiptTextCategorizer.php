<?php

namespace App\Finance;

use App\Services\Ai\CategorySuggestion;

/**
 * Keyword-based category suggestion from the receipt's raw OCR text
 * (line items, header, counterparty). Arabic + English.
 *
 * Only ever returns a key that exists in the allowed (configured) list.
 * Each group lists candidate category keys in preference order; a group
 * whose keys are all missing from config is skipped. Returns null when
 * nothing matches or two groups tie (not confident).
 */
class ReceiptTextCategorizer
{
    /**
     * @var array<string, array{keys: list<string>, words: list<string>}>
     */
    private const GROUPS = [
        'food' => [
            'keys' => ['meals', 'food', 'food_beverages', 'food_and_beverages', 'restaurants', 'hospitality'],
            'words' => [
                'مطعم', 'مطاعم', 'كافيه', 'مقهى', 'قهوه', 'شاي', 'بيتزا', 'سلطه', 'عصير', 'شاورما', 'فلافل', 'برغر', 'برجر',
                'سندويش', 'وجبه', 'وجبات', 'مشروب', 'مشروبات', 'حلويات', 'معجنات', 'فطور', 'غداء', 'عشاء', 'دجاج', 'مشاوي', 'جبنه',
                'restaurant', 'cafe', 'coffee', 'pizza', 'salad', 'juice', 'burger', 'sandwich', 'meal', 'food', 'drink', 'beverage', 'dinner', 'lunch', 'breakfast',
            ],
        ],
        'fuel' => [
            'keys' => ['fuel', 'transport'],
            'words' => ['وقود', 'بنزين', 'مازوت', 'ديزل', 'محروقات', 'محطه وقود', 'fuel', 'petrol', 'gasoline', 'diesel', 'gas station'],
        ],
        'transport' => [
            'keys' => ['transport'],
            'words' => ['تكسي', 'تاكسي', 'اجره', 'مواصلات', 'نقل', 'شحن', 'taxi', 'uber', 'careem', 'transport', 'shipping', 'freight'],
        ],
        'utilities' => [
            'keys' => ['utilities'],
            'words' => [
                'كهرباء', 'فاتوره كهرباء', 'مياه', 'فاتوره ماء', 'فاتوره مياه', 'انترنت', 'اتصالات', 'هاتف', 'رصيد', 'غاز منزلي',
                'electricity', 'water bill', 'internet', 'telecom', 'phone bill', 'utility', 'utilities',
            ],
        ],
        'rent' => [
            'keys' => ['rent'],
            'words' => ['ايجار', 'اجار', 'rent', 'lease'],
        ],
        'salaries' => [
            'keys' => ['salaries'],
            'words' => ['راتب', 'رواتب', 'اجور', 'salary', 'salaries', 'payroll', 'wages'],
        ],
    ];

    /**
     * @param  array<string, string>  $allowedCategories  key => label
     */
    public function suggest(?string $text, array $allowedCategories): ?CategorySuggestion
    {
        $text = self::normalize((string) $text);
        if ($text === '' || $allowedCategories === []) {
            return null;
        }

        $scores = [];
        foreach (self::GROUPS as $group) {
            $key = null;
            foreach ($group['keys'] as $candidate) {
                if (array_key_exists($candidate, $allowedCategories)) {
                    $key = $candidate;
                    break;
                }
            }
            if ($key === null) {
                continue;
            }
            $hits = 0;
            foreach ($group['words'] as $word) {
                $hits += self::countWord($text, self::normalize($word));
            }
            if ($hits > 0) {
                $scores[$key] = ($scores[$key] ?? 0) + $hits;
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);
        $values = array_values($scores);
        if (count($values) > 1 && $values[0] === $values[1]) {
            return null;
        }

        $category = (string) array_key_first($scores);
        $total = array_sum($values);

        return new CategorySuggestion($category, round($values[0] / $total, 2), true);
    }

    private static function countWord(string $text, string $word): int
    {
        // Whole word, allowing common Arabic prefixes (ال، و، ب، ل، بال، وال، لل).
        $pattern = '/(?<![\p{L}\p{N}])(?:و|ب|ل|ف)?(?:ال|لل)?'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u';

        return (int) preg_match_all($pattern, $text);
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text; // tashkeel, tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }
}
