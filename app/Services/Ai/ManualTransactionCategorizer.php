<?php

namespace App\Services\Ai;

use App\Contracts\TransactionCategorizer;

/**
 * Default implementation: no AI. Never suggests; the user picks a category.
 */
class ManualTransactionCategorizer implements TransactionCategorizer
{
    public function name(): string
    {
        return 'manual';
    }

    public function suggest(string $type, ?string $description, ?string $counterparty, array $allowedCategories): ?CategorySuggestion
    {
        return null;
    }
}
