<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Contracts\ReceiptExtractor;
use App\Enums\ExtractionStatus;
use App\Finance\TransactionTypeSuggester;
use App\Models\Document;
use App\Models\FinancialTransaction;
use App\Models\ReceiptExtraction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class StartReceiptExtractionAction
{
    public function __construct(
        private readonly ReceiptExtractor $extractor,
        private readonly RecordAuditLog $audit,
        private readonly TransactionTypeSuggester $typeSuggester,
    ) {}

    /** Same checks as documents.extractions.store: view the document AND create transactions in its enterprise. */
    public function allows(User $actor, Document $document): bool
    {
        $document->loadMissing('enterprise');

        return $actor->can('view', $document)
            && $actor->can('create', [FinancialTransaction::class, $document->enterprise]);
    }

    public function execute(User $actor, Document $document): ReceiptExtraction
    {
        abort_unless($actor->can('create', [FinancialTransaction::class, $document->enterprise]), 403);

        try {
            $result = $this->extractor->extract($document);
        } catch (Throwable $e) {
            // OCR failure must never block manual entry.
            Log::warning('Receipt extraction failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $result = \App\Services\Ai\ReceiptExtractionResult::empty();
        }

        $currencies = array_keys(config('bookkeeping.currencies'));

        $extraction = new ReceiptExtraction;
        $extraction->enterprise_id = $document->enterprise_id;
        $extraction->document_id = $document->id;
        $extraction->status = ExtractionStatus::PendingReview;
        $extraction->extractor = $this->extractor->name();
        $extraction->suggested_date = $result->transactionDate;
        $extraction->suggested_amount = $result->amount !== null && preg_match('/^\d{1,16}(\.\d{1,2})?$/', $result->amount) ? $result->amount : null;
        $extraction->suggested_currency = in_array($result->currency, $currencies, true) ? $result->currency : null;
        $extraction->suggested_counterparty = $result->counterparty !== null ? mb_substr($result->counterparty, 0, 150) : null;
        $hasSuggestion = ! $result->failed && ($extraction->suggested_date !== null || $extraction->suggested_amount !== null
            || $extraction->suggested_currency !== null || $extraction->suggested_counterparty !== null);
        // Suggestion only; null when OCR produced nothing (manual entry).
        $extraction->suggested_transaction_type = $hasSuggestion
            ? ($this->typeSuggester->suggest($result->rawText) ?? 'expense')
            : null;
        $extraction->confidence = $result->confidence;
        // Always reviewed by a human; flag stays true unless provider is confident AND complete.
        $extraction->needs_review = true;
        $extraction->created_by = $actor->id;
        $extraction->save();

        $this->audit->record($actor, 'receipt_extraction.created', $extraction, [
            'document_id' => $document->id,
            'extractor' => $extraction->extractor,
        ]);

        return $extraction;
    }
}
