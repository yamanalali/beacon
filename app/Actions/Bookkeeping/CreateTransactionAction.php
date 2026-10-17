<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\TransactionItem;
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
        if ($corrects !== null) {
            // A correction versions the invoice, not the cash payments. Never
            // invent a replacement payment dated on the invoice date.
            $corrects->loadMissing('settlements');
            $paidMinor = $corrects->settledMinor();
            if ($amountMinor <= 0 || $amountMinor < $paidMinor) {
                throw ValidationException::withMessages(['amount' => 'يجب أن يكون المبلغ أكبر من صفر ولا يقل عن الدفعات المسجلة ('.Money::fromMinor($paidMinor).').']);
            }
            foreach (['currency', 'type'] as $field) {
                $previous = $field === 'type' ? $corrects->type->value : $corrects->currency;
                if ($paidMinor > 0 && $data[$field] !== $previous) {
                    throw ValidationException::withMessages([$field => 'لا يمكن تغيير العملة أو نوع العملية بعد تسجيل دفعات عليها.']);
                }
            }
            $paymentStatus = $paidMinor === 0 ? PaymentStatus::Unpaid
                : ($paidMinor === $amountMinor ? PaymentStatus::Paid : PaymentStatus::PartiallyPaid);
        } else {
            $paymentStatus = PaymentStatus::from($data['payment_status']);
            $paidMinor = $this->initialPaidMinor($paymentStatus, $amountMinor, $data['paid_amount'] ?? null);
        }

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

            if ($corrects !== null) {
                foreach ($corrects->settlements as $originalSettlement) {
                    // Keep the superseded version's history, dates, author and notes.
                    $settlement = $originalSettlement->replicate();
                    $settlement->financial_transaction_id = $transaction->id;
                    $settlement->created_at = $originalSettlement->created_at;
                    $settlement->updated_at = $originalSettlement->updated_at;
                    $settlement->save();
                }
            } elseif ($paidMinor > 0) {
                $settlement = new TransactionSettlement;
                $settlement->financial_transaction_id = $transaction->id;
                $settlement->settlement_date = $data['transaction_date'];
                $settlement->amount = Money::fromMinor($paidMinor);
                $settlement->note = null;
                $settlement->created_by = $actor->id;
                $settlement->save();
            }

            $this->saveItems($transaction, $data['items'] ?? []); // absent or null on store = no items

            $this->audit->record($actor, 'transaction.created', $transaction, [
                'enterprise_id' => $enterprise->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'payment_status' => $paymentStatus->value,
                'corrects_transaction_id' => $corrects?->id,
                'preserved_settlement_ids' => $corrects?->settlements->modelKeys() ?? [],
                'items_count' => count($data['items'] ?? []),
            ]);

            return $transaction;
        });
    }

    /**
     * Stores the submitted item set. A correction is a new record, so its items
     * fully replace the original's (the original keeps its own set as history).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(FinancialTransaction $transaction, array $items): void
    {
        foreach (array_values($items) as $position => $row) {
            $quantity = $this->decimal($row['quantity'], 3);
            $unitPrice = $this->decimal($row['unit_price'], 2);
            $total = isset($row['total']) && $row['total'] !== ''
                ? $this->decimal($row['total'], 2)
                : bcadd(bcmul($quantity, $unitPrice, 6), '0.005', 2); // round half up

            $item = new TransactionItem;
            $item->transaction_id = $transaction->id;
            $item->position = $position;
            $item->description = trim((string) $row['description']);
            $item->quantity = $quantity;
            $item->unit_price = $unitPrice;
            $item->total = $total;
            $item->save();
        }
    }

    /**
     * Normalises a validated numeric value to a fixed-scale decimal string (half up), without floats.
     */
    private function decimal(mixed $value, int $scale): string
    {
        $value = trim((string) $value);
        if (preg_match('/[eE]/', $value)) {
            $value = sprintf('%.'.($scale + 2).'F', (float) $value);
        }

        return bcadd($value, '0.'.str_repeat('0', $scale).'5', $scale);
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
