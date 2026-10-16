<?php

namespace App\Contracts;

use App\Models\Document;
use App\Services\Ai\ReceiptExtractionResult;

/**
 * Extracts SUGGESTED values from a receipt/invoice (OCR).
 * Results are never authoritative: the user must review and confirm
 * before any transaction is created. Implementations must not create
 * or modify financial records.
 */
interface ReceiptExtractor
{
    public function name(): string;

    public function extract(Document $document): ReceiptExtractionResult;
}
