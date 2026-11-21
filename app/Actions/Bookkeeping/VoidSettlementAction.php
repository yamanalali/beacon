<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\FinancialTransaction;
use App\Models\TransactionSettlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Voids a recorded payment without deleting it, then recalculates the parent
 * transaction's payment status from the remaining (non-voided) settlements.
 */
class VoidSettlementAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, FinancialTransaction $transaction, TransactionSettlement $settlement, ?string $reason = null): TransactionSettlement
    {
        abort_unless($actor->can('voidSettlement', $transaction), 403);
        abort_unless($settlement->financial_transaction_id === $transaction->id, 404);

        return DB::transaction(function () use ($actor, $transaction, $settlement, $reason): TransactionSettlement {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->status === TransactionStatus::Posted, 409);

            $current = TransactionSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            abort_if($current->isVoided(), 409);

            $current->voided_at = now();
            $current->voided_by = $actor->id;
            $current->void_reason = filled($reason) ? $reason : null;
            $current->save();

            $locked->load('settlements'); // active settlements only
            $before = $locked->payment_status;
            $settled = $locked->settledMinor();
            $locked->payment_status = match (true) {
                $settled <= 0 => PaymentStatus::Unpaid,
                $settled >= $locked->amountMinor() => PaymentStatus::Paid,
                default => PaymentStatus::PartiallyPaid,
            };
            $locked->save();

            $this->audit->record($actor, 'transaction.settlement_voided', $locked, [
                'settlement_id' => $current->id,
                'amount' => (string) $current->amount,
                'reason' => $current->void_reason,
                'payment_status_before' => $before->value,
                'payment_status_after' => $locked->payment_status->value,
            ]);

            return $current;
        });
    }
}
