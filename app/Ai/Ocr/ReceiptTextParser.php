<?php

namespace App\Ai\Ocr;

use App\Services\Ai\ReceiptExtractionResult;

/**
 * Heuristic parser for raw OCR text of a receipt (rules §15). Output is a
 * low-confidence SUGGESTION only; the user always reviews and confirms.
 *
 * TODO: BUSINESS DECISION REQUIRED — the confidence threshold for a "weak"
 * result. Until decided every result is marked needsReview = true.
 */
class ReceiptTextParser
{
    /** Strong "amount payable" wording, checked first (bottom-up). */
    private const TOTAL_STRONG = '/(المبلغ\s*المستحق|الإجمالي|الاجمالي|إجمالي|اجمالي|المجموع\s*الكلي|الصافي|grand\s*total|total\s*due|amount\s*due|\btotal\b)/iu';

    /** Weaker wording, used only when no strong line has a number. */
    private const TOTAL_WORDS = '/(الإجمالي|الاجمالي|المجموع|الصافي|المبلغ|total|amount|sum)/iu';

    /** Sub-totals / tax lines are never the payable amount. */
    private const NOT_TOTAL = '/(المجموع\s*الفرعي|الإجمالي\s*الفرعي|sub\s*-?\s*total|ضريبة|vat|tax|خصم|discount|الباقي|change|نقدا|cash)/iu';

    /**
     * Currency only from explicit tokens printed on the receipt (never inferred from
     * the address/country). Codes outside config('bookkeeping.currencies') are
     * dropped later, leaving the field empty; amounts are never converted.
     */
    private const CURRENCIES = [
        'AED' => '/(درهم|د\.\s?إ|\bAED\b|\bDHS?\b)/iu',
        'SAR' => '/(ريال\s*سعودي|ر\.\s?س|\bSAR\b)/iu',
        'SYP' => '/(ليرة\s*سورية|ل\.\s?س|\bSYP\b|\bS\.P\b)/iu',
        'TRY' => '/(ليرة\s*تركية|₺|\bTRY\b|\bTL\b)/iu',
        'USD' => '/(دولار|\$|\bUSD\b)/iu',
        'EUR' => '/(يورو|€|\bEUR\b)/iu',
    ];

    public function parse(string $raw): ReceiptExtractionResult
    {
        $text = $this->normaliseDigits($raw);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn ($l) => $l !== ''));
        if ($lines === []) {
            return new ReceiptExtractionResult(failed: true);
        }

        $amount = $this->amount($lines);
        $date = $this->date($text);
        $vendor = $this->vendor($lines);
        $currency = null;
        foreach (self::CURRENCIES as $code => $re) {
            if (preg_match($re, $text)) {
                $currency = $code;
                break;
            }
        }

        $found = count(array_filter([$amount, $date, $vendor]));

        return new ReceiptExtractionResult(
            transactionDate: $date,
            amount: $amount,
            currency: $currency,
            counterparty: $vendor,
            // Heuristic only: never above 0.5.
            confidence: round(0.15 * $found + ($found ? 0.05 : 0), 2),
            needsReview: true,
            failed: $found === 0,
            rawText: $raw,
        );
    }

    public function normaliseDigits(string $s): string
    {
        return strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.', '٬' => ',']);
    }

    /** Prefer a number on a "total" line; otherwise the largest money-like number. */
    private function amount(array $lines): ?string
    {
        $pick = function (string $line): array {
            preg_match_all('/\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?/u', $line, $m);

            return array_values(array_filter(array_map(fn ($n) => str_replace(',', '', $n), $m[0]), fn ($n) => (float) $n > 0 && ! preg_match('/^(19|20)\d{2}$/', $n)));
        };
        foreach ([self::TOTAL_STRONG, self::TOTAL_WORDS] as $re) {
            foreach (array_reverse($lines) as $line) {
                if (preg_match($re, $line) && ! preg_match(self::NOT_TOTAL, $line) && ($nums = $pick($line)) !== []) {
                    return $this->money((float) end($nums));
                }
            }
        }
        $all = [];
        foreach ($lines as $line) {
            if (! preg_match('/\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}/', $line)) {
                $all = array_merge($all, $pick($line));
            }
        }

        return $all === [] ? null : $this->money(max(array_map('floatval', $all)));
    }

    private function money(float $v): ?string
    {
        $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return preg_match('/^\d{1,16}(\.\d{1,2})?$/', $s) ? $s : null;
    }

    private function date(string $text): ?string
    {
        if (preg_match('/\b(20\d{2})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\b/', $text, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](20\d{2})\b/', $text, $m)) {
            // Day-first (common in Syria/Türkiye).
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2})\b/', $text, $m)) {
            // Two-digit year: only when day/month order is unambiguous (one part > 12).
            [$a, $b, $y] = [(int) $m[1], (int) $m[2], 2000 + (int) $m[3]];
            if ($a > 12 && $b <= 12) {
                [$d, $mo] = [$a, $b];
            } elseif ($b > 12 && $a <= 12) {
                [$mo, $d] = [$a, $b];
            } else {
                return null; // ambiguous: leave empty for the user
            }
        } else {
            return null;
        }

        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /** First line that has letters and is not a total/date line. */
    private function vendor(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 5) as $line) {
            if (preg_match('/\p{L}{2,}/u', $line) && ! preg_match(self::TOTAL_WORDS, $line) && ! preg_match('/\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}/', $line)) {
                return mb_substr(strip_tags($line), 0, 150);
            }
        }

        return null;
    }
}
