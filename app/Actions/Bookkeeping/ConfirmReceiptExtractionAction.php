<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ExtractionStatus;
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
            $locked = ReceiptExtraction::query()->lockForUpdate()->findOrFail($extraction->id);
            abort_unless($locked->status === ExtractionStatus::PendingReview, 409);

            $transaction = $this->createTransaction->execute($actor, $extraction->enterprise, $data);

            $document = $locked->document;
            if ($document->financial_transaction_id === null) {
                $document->financial_transaction_id = $transaction->id;
                $document->save();
            }

            $locked->status = ExtractionStatus::Confirmed;
            $locked->confirmed_transaction_id = $transaction->id;
            $locked->reviewed_by = $actor->id;
            $locked->reviewed_at = now();
            $locked->save();

            $this->audit->record($actor, 'receipt_extraction.confirmed', $locked, [
                'transaction_id' => $transaction->id,
                'amount_changed' => (string) $locked->suggested_amount !== (string) $transaction->amount,
                'category_changed' => $locked->suggested_category !== null && $locked->suggested_category !== $transaction->category,
            ]);

            return $transaction;
        });
    }

    public function reject(User $actor, ReceiptExtraction $extraction): void
    {
        abort_unless($actor->can('create', [FinancialTransaction::class, $extraction->enterprise]), 403);
        abort_unless($extraction->status === ExtractionStatus::PendingReview, 409);

        $extraction->status = ExtractionStatus::Rejected;
        $extraction->reviewed_by = $actor->id;
        $extraction->reviewed_at = now();
        $extraction->save();

        $this->audit->record($actor, 'receipt_extraction.rejected', $extraction);
    }
}
