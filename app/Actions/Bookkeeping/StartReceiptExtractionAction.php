<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Contracts\ReceiptExtractor;
use App\Enums\ExtractionStatus;
use App\Finance\ReceiptTextCategorizer;
use App\Finance\TransactionTypeSuggester;
use App\Models\Document;
use App\Models\FinancialTransaction;
use App\Models\ReceiptExtraction;
use App\Models\User;
use App\Services\Ai\ReceiptExtractionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StartReceiptExtractionAction
{
    public function __construct(
        private readonly ReceiptExtractor $extractor,
        private readonly RecordAuditLog $audit,
        private readonly TransactionTypeSuggester $typeSuggester,
        private readonly ReceiptTextCategorizer $textCategorizer,
    ) {}

    /** Same checks as documents.extractions.store: view the document AND create transactions in its enterprise. */
    public function allows(User $actor, Document $document): bool
    {
        $document->loadMissing('enterprise');

        return $actor->can('view', $document)
            && $actor->can('create', [FinancialTransaction::class, $document->enterprise]);
    }

    /**
     * Draft line items: [{description, quantity, unit_price, total}] with numbers as int/float
     * and unreadable values null (never guessed). [] when there are none. One category per
     * transaction, so items carry none.
     *
     * @return list<array{description: ?string, quantity: int|float|null, unit_price: int|float|null, total: int|float|null}>
     */
    public static function sanitiseItems(array $items): array
    {
        $num = function ($v): int|float|null {
            if (! is_int($v) && ! is_float($v)) {
                return null;
            }
            $v = round((float) $v, 2);

            return $v < 0 || $v > 9_999_999_999_999 ? null : ($v == (int) $v ? (int) $v : $v);
        };
        $out = [];
        foreach (array_slice($items, 0, 100) as $i) {
            if (! is_array($i)) {
                continue;
            }
            $d = isset($i['description']) && is_string($i['description']) ? trim(strip_tags($i['description'])) : '';
            $row = [
                'description' => $d !== '' ? mb_substr($d, 0, 255) : null,
                'quantity' => $num($i['quantity'] ?? null),
                'unit_price' => $num($i['unit_price'] ?? null),
                'total' => $num($i['total'] ?? null),
            ];
            if ($row['description'] !== null || $row['total'] !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function execute(User $actor, Document $document): ReceiptExtraction
    {
        abort_unless($this->allows($actor, $document), 403);
        abort_if($document->fresh()->financial_transaction_id !== null, 409, 'تم تسجيل عملية مالية لهذا المستند مسبقاً.');

        try {
            $result = $this->extractor->extract($document);
        } catch (Throwable $e) {
            // OCR failure must never block manual entry.
            Log::warning('Receipt extraction failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $result = ReceiptExtractionResult::empty();
        }

        $currencies = array_keys(config('bookkeeping.currencies'));

        return DB::transaction(function () use ($actor, $document, $result, $currencies): ReceiptExtraction {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            abort_if($locked->financial_transaction_id !== null, 409, 'تم تسجيل عملية مالية لهذا المستند مسبقاً.');
            $pending = ReceiptExtraction::query()->where('document_id', $locked->id)
                ->where('status', ExtractionStatus::PendingReview)->latest('id')->first();
            if ($pending !== null) {
                return $pending;
            }

            $extraction = new ReceiptExtraction;
            $extraction->enterprise_id = $document->enterprise_id;
            $extraction->document_id = $document->id;
            $extraction->status = ExtractionStatus::PendingReview;
            $extraction->extractor = $this->extractor->name();
            $extraction->suggested_date = $result->transactionDate;
            $extraction->suggested_amount = $result->amount !== null && preg_match('/^\d{1,16}(\.\d{1,2})?$/', $result->amount) ? $result->amount : null;
            $extraction->suggested_currency = in_array($result->currency, $currencies, true) ? $result->currency : null;
            $extraction->suggested_counterparty = $result->counterparty !== null ? mb_substr($result->counterparty, 0, 150) : null;
            $extraction->suggested_items = self::sanitiseItems($result->items);
            $extraction->suggested_invoice_number = $result->invoiceNumber !== null && preg_match('/^[\p{L}\d\-\/]{1,50}$/u', $result->invoiceNumber) ? $result->invoiceNumber : null;
            $hasSuggestion = ! $result->failed && ($extraction->suggested_date !== null || $extraction->suggested_amount !== null
                || $extraction->suggested_currency !== null || $extraction->suggested_counterparty !== null
                || $extraction->suggested_items !== []);
            // Suggestion only; null when OCR produced nothing (manual entry).
            $extraction->suggested_transaction_type = $hasSuggestion
                ? ($this->typeSuggester->suggest($result->rawText) ?? 'expense')
                : null;
            // Category from the receipt's line items + counterparty (raw text stays in memory only).
            $extraction->suggested_category = $extraction->suggested_transaction_type !== null
                ? $this->textCategorizer->suggest(
                    trim(($result->rawText ?? '')."\n".($extraction->suggested_counterparty ?? '')),
                    config('bookkeeping.categories.'.$extraction->suggested_transaction_type, []),
                )?->category
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
        });
    }
}
