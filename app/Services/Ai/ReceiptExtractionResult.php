<?php

namespace App\Services\Ai;

final class ReceiptExtractionResult
{
    public function __construct(
        public readonly ?string $transactionDate = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $counterparty = null,
        public readonly ?float $confidence = null,
        public readonly bool $needsReview = true,
        public readonly bool $failed = false,
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
