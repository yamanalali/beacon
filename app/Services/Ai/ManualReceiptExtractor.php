<?php

namespace App\Services\Ai;

use App\Contracts\ReceiptExtractor;
use App\Models\Document;

/**
 * Default implementation: no OCR. Returns an empty result so the user
 * enters the values manually. The real OCR provider will be bound later.
 */
class ManualReceiptExtractor implements ReceiptExtractor
{
    public function name(): string
    {
        return 'manual';
    }

    public function extract(Document $document): ReceiptExtractionResult
    {
        return ReceiptExtractionResult::empty();
    }
}
