<?php

namespace App\Ai\Finance;

use App\Ai\Contracts\AiProvider;
use App\Ai\Support\AiDataGuard;
use App\Contracts\TransactionCategorizer;
use App\Services\Ai\CategorySuggestion;
use Throwable;

/**
 * AI category suggestion restricted to the configured list (rules §16).
 * Sends only type, description, counterparty and the allowed list.
 * Anything outside the list is discarded. Always needsReview = true.
 *
 * TODO: BUSINESS DECISION REQUIRED — confidence level (if any) at which a
 * suggestion is no longer "uncertain". Until decided all are marked for review.
 */
class AiTransactionCategorizer implements TransactionCategorizer
{
    public function __construct(private readonly AiProvider $provider) {}

    public function name(): string
    {
        return 'ai';
    }

    public function suggest(string $type, ?string $description, ?string $counterparty, array $allowedCategories): ?CategorySuggestion
    {
        if ($allowedCategories === [] || ! AiDataGuard::allows(AiDataGuard::CATEGORIZATION)) {
            return null;
        }

        try {
            $r = $this->provider->suggestCategory($type, $description, $counterparty, $allowedCategories);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $category = $r['category'] ?? null;
        if (! is_string($category) || ! array_key_exists($category, $allowedCategories)) {
            return null;
        }

        return new CategorySuggestion($category, isset($r['confidence']) ? (float) $r['confidence'] : null, true);
    }
}
