<?php

namespace App\Services\Ai;

final class CategorySuggestion
{
    public function __construct(
        public readonly string $category,
        public readonly ?float $confidence = null,
        public readonly bool $needsReview = true,
    ) {}
}
