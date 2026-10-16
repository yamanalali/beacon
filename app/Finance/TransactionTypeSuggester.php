<?php

namespace App\Finance;

use App\Enums\TransactionType;

/**
 * Suggests whether free text (OCR of a receipt, or a voice transcript) is an
 * expense or income. A SUGGESTION only: nothing is saved until the user
 * confirms. Shared by receipt OCR and voice drafts.
 *
 * TODO: BUSINESS DECISION REQUIRED — Beacon must confirm the rule. For now a
 * receipt with any text defaults to 'expense'; it becomes 'income' only when
 * the text clearly says money was received (receipt-of-payment wording).
 */
class TransactionTypeSuggester
{
    /** Phrases that clearly indicate money received by the enterprise. */
    private const INCOME_PATTERNS = [
        '/مقبوضات/u',
        '/سند\s*قبض/u',
        '/[إا]يصال\s*استلام/u',
        '/تم\s*(ال)?استلام/u',
        '/استلمنا\s*من/u',
        '/دفعة\s*من\s*(ال)?(زبون|عميل|زبائن)/u',
        '/دفعة\s*(ال)?(زبون|عميل)/u',
        // Levantine spoken income (voice transcripts).
        // TODO: BUSINESS DECISION REQUIRED — confirm the spoken keyword list.
        '/(?<!\p{L})قبضت/u',
        '/(?<!\p{L})استلمت/u',
        '/(?<!\p{L})دخل\x{0651}?ي(?!\p{L})/u',
        '/(?<!\p{L})مبيع(ات)?(?!\p{L})/u',
        '/(?<!\p{L})[إا]يراد/u',
        // "بعت" = sold, but NOT "بعتلو/بعت لها/بعت على" (= I sent to someone).
        '/(?<!\p{L})بعت(?!\s*(?:ل|على))/u',
        '/received\s+from/iu',
        '/payment\s+received/iu',
        '/receipt\s+voucher/iu',
        '/customer\s+payment/iu',
        '/payment\s+from\s+(a\s+)?(customer|client)/iu',
    ];

    /** @return 'income'|'expense'|null */
    public function suggest(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        foreach (self::INCOME_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return TransactionType::Income->value;
            }
        }

        return TransactionType::Expense->value;
    }

    /** @return list<array{value: 'expense'|'income', label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (TransactionType $t) => ['value' => $t->value, 'label' => $t->label()],
            [TransactionType::Expense, TransactionType::Income],
        );
    }
}
