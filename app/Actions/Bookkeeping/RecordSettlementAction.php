<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\FinancialTransaction;
use App\Models\TransactionSettlement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a payment received (credit sale) or paid (credit purchase/expense).
 * It only moves cash; it never adds revenue or expense a second time.
 */
class RecordSettlementAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, FinancialTransaction $transaction, array $data): TransactionSettlement
    {
        abort_unless($actor->can('settle', $transaction), 403);

        return DB::transaction(function () use ($actor, $transaction, $data): TransactionSettlement {
            $locked = FinancialTransaction::query()->lockForUpdate()->with('settlements')->findOrFail($transaction->id);
            abort_unless($locked->status === TransactionStatus::Posted, 409);

            $amountMinor = Money::toMinor($data['amount']);
            $outstanding = $locked->outstandingMinor();

            if ($amountMinor <= 0 || $amountMinor > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يجب أن يكون أكبر من صفر ولا يتجاوز المبلغ المتبقي ('.Money::fromMinor($outstanding).').',
                ]);
            }

            $settlement = new TransactionSettlement;
            $settlement->financial_transaction_id = $locked->id;
            $settlement->settlement_date = $data['settlement_date'];
            $settlement->amount = Money::fromMinor($amountMinor);
            $settlement->note = $data['note'] ?? null;
            $settlement->created_by = $actor->id;
            $settlement->save();

            $locked->payment_status = $amountMinor === $outstanding ? PaymentStatus::Paid : PaymentStatus::PartiallyPaid;
            $locked->save();

            $this->audit->record($actor, 'transaction.settlement_recorded', $locked, [
                'settlement_id' => $settlement->id,
                'amount' => $settlement->amount,
                'payment_status' => $locked->payment_status->value,
            ]);

            return $settlement;
        });
    }
}
