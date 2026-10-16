<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\FinancialTransaction;
use App\Models\ReceiptExtraction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY path from an extraction to a transaction: explicit user
 * confirmation of user-reviewed values.
 */
class ConfirmReceiptExtractionAction
{
    public function __construct(
        private readonly CreateTransactionAction $createTransaction,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  user-reviewed values
     */
    public function confirm(User $actor, ReceiptExtraction $extraction, array $data): FinancialTransaction
    {
        abort_unless($actor->can('create', [FinancialTransaction::class, $extraction->enterprise]), 403);

        return DB::transaction(function () use ($actor, $extraction, $data): FinancialTransaction {
            // Serialize confirmations for the document, including different extractions.
            $document = Document::query()->lockForUpdate()->findOrFail($extraction->document_id);
            $locked = ReceiptExtraction::query()->lockForUpdate()->findOrFail($extraction->id);
            abort_unless($locked->status === ExtractionStatus::PendingReview, 409);
            abort_if($document->financial_transaction_id !== null, 409, 'تم تسجيل عملية مالية لهذا المستند مسبقاً.');

            $transaction = $this->createTransaction->execute($actor, $extraction->enterprise, $data);

            $document->financial_transaction_id = $transaction->id;
            $document->save();

            $locked->status = ExtractionStatus::Confirmed;
            $locked->confirmed_transaction_id = $transaction->id;
            $locked->reviewed_by = $actor->id;
            $locked->reviewed_at = now();
            $locked->save();

            // Legacy parallel reads must not remain in the review queue.
            $duplicates = ReceiptExtraction::query()->where('document_id', $document->id)
                ->whereKeyNot($locked->id)->where('status', ExtractionStatus::PendingReview);
            $duplicateIds = (clone $duplicates)->pluck('id')->all();
            $duplicates->update(['status' => ExtractionStatus::Rejected->value, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);

            $this->audit->record($actor, 'receipt_extraction.confirmed', $locked, [
                'transaction_id' => $transaction->id,
                'closed_duplicate_extraction_ids' => $duplicateIds,
                'amount_changed' => (string) $locked->suggested_amount !== (string) $transaction->amount,
                'category_changed' => $locked->suggested_category !== null && $locked->suggested_category !== $transaction->category,
            ]);

            return $transaction;
        });
    }

    public function reject(User $actor, ReceiptExtraction $extraction): void
    {
        abort_unless($actor->can('create', [FinancialTransaction::class, $extraction->enterprise]), 403);
        DB::transaction(function () use ($actor, $extraction): void {
            $locked = ReceiptExtraction::query()->lockForUpdate()->findOrFail($extraction->id);
            abort_unless($locked->status === ExtractionStatus::PendingReview, 409);
            $locked->status = ExtractionStatus::Rejected;
            $locked->reviewed_by = $actor->id;
            $locked->reviewed_at = now();
            $locked->save();
            $this->audit->record($actor, 'receipt_extraction.rejected', $locked);
        });
    }
}
