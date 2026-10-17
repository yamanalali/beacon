<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\TransactionStatus;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Versioned correction: the original record is never overwritten. It is
 * marked as superseded and a new posted record (linked to it) holds the
 * corrected values together with the user's reason.
 *
 * TODO: BUSINESS DECISION REQUIRED
 * Confirm the final correction method with Beacon (versioned correction
 * records vs. reversal entries vs. another accounting procedure).
 */
class CorrectTransactionAction
{
    public function __construct(
        private readonly CreateTransactionAction $createTransaction,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, FinancialTransaction $original, array $data): FinancialTransaction
    {
        abort_unless($actor->can('correct', $original), 403);

        return DB::transaction(function () use ($actor, $original, $data): FinancialTransaction {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($original->id);
            abort_unless($locked->status === TransactionStatus::Posted, 409);

            $locked->status = TransactionStatus::Superseded;
            $locked->save();

            // `items` absent → carry the original's items over unchanged;
            // present (even [] / null) → use exactly what was submitted.
            if (! array_key_exists('items', $data)) {
                $data['items'] = $locked->items()->get()->map(fn ($i): array => [
                    'description' => $i->description,
                    'quantity' => (string) $i->getRawOriginal('quantity'),
                    'unit_price' => (string) $i->getRawOriginal('unit_price'),
                    'total' => (string) $i->getRawOriginal('total'),
                ])->values()->all();
            }

            $corrected = $this->createTransaction->execute(
                $actor,
                $original->enterprise,
                $data,
                $locked,
                $data['correction_reason'],
            );

            $this->audit->record($actor, 'transaction.corrected', $locked, [
                'original' => [
                    'type' => $locked->type->value,
                    'transaction_date' => $locked->transaction_date?->toDateString(),
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'category' => $locked->category,
                    'payment_status' => $locked->payment_status->value,
                ],
                'corrected_transaction_id' => $corrected->id,
                'reason' => $data['correction_reason'],
            ]);

            return $corrected;
        });
    }
}
