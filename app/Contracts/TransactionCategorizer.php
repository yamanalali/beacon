<?php

namespace App\Contracts;

use App\Services\Ai\CategorySuggestion;

/**
 * Suggests a category from the configured/allowed list only.
 * Uncertain suggestions must be returned with needsReview = true.
 */
interface TransactionCategorizer
{
    public function name(): string;

    /**
     * @param  array<string, string>  $allowedCategories  key => label
     */
    public function suggest(string $type, ?string $description, ?string $counterparty, array $allowedCategories): ?CategorySuggestion;
}
