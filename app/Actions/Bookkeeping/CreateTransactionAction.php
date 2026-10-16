<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\TransactionSettlement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTransactionAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated data
     */
    public function execute(User $actor, Enterprise $enterprise, array $data, ?FinancialTransaction $corrects = null, ?string $reason = null): FinancialTransaction
    {
        abort_unless($actor->can('create', [FinancialTransaction::class, $enterprise]), 403);

        $amountMinor = Money::toMinor($data['amount']);
        $paymentStatus = PaymentStatus::from($data['payment_status']);
        $paidMinor = $this->initialPaidMinor($paymentStatus, $amountMinor, $data['paid_amount'] ?? null);

        return DB::transaction(function () use ($actor, $enterprise, $data, $corrects, $reason, $amountMinor, $paymentStatus, $paidMinor): FinancialTransaction {
            $transaction = new FinancialTransaction;
            $transaction->enterprise_id = $enterprise->id;
            $transaction->type = TransactionType::from($data['type']);
            $transaction->transaction_date = $data['transaction_date'];
            $transaction->amount = Money::fromMinor($amountMinor);
            $transaction->currency = $data['currency'];
            $transaction->category = $data['category'];
            $transaction->payment_status = $paymentStatus;
            $transaction->counterparty = $data['counterparty'] ?? null;
            $transaction->description = $data['description'] ?? null;
            $transaction->status = TransactionStatus::Posted;
            $transaction->corrects_transaction_id = $corrects?->id;
            $transaction->correction_reason = $reason;
            $transaction->created_by = $actor->id;
            $transaction->save();

            if ($paidMinor > 0) {
                $settlement = new TransactionSettlement;
                $settlement->financial_transaction_id = $transaction->id;
                $settlement->settlement_date = $data['transaction_date'];
                $settlement->amount = Money::fromMinor($paidMinor);
                $settlement->note = null;
                $settlement->created_by = $actor->id;
                $settlement->save();
            }

            $this->audit->record($actor, 'transaction.created', $transaction, [
                'enterprise_id' => $enterprise->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'payment_status' => $paymentStatus->value,
                'corrects_transaction_id' => $corrects?->id,
            ]);

            return $transaction;
        });
    }

    private function initialPaidMinor(PaymentStatus $status, int $amountMinor, mixed $paidAmount): int
    {
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'يجب أن يكون المبلغ أكبر من صفر.']);
        }

        return match ($status) {
            PaymentStatus::Paid => $amountMinor,
            PaymentStatus::Unpaid => 0,
            PaymentStatus::PartiallyPaid => $this->partialMinor($amountMinor, $paidAmount),
        };
    }

    private function partialMinor(int $amountMinor, mixed $paidAmount): int
    {
        $paidMinor = $paidAmount === null || $paidAmount === '' ? 0 : Money::toMinor((string) $paidAmount);

        if ($paidMinor <= 0 || $paidMinor >= $amountMinor) {
            throw ValidationException::withMessages([
                'paid_amount' => 'المبلغ المدفوع جزئياً يجب أن يكون أكبر من صفر وأقل من المبلغ الكلي.',
            ]);
        }

        return $paidMinor;
    }
}
