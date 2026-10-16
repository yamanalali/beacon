<?php

namespace App\Ai\Finance;

use App\Enums\TransactionStatus;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use Carbon\CarbonImmutable;

/**
 * Read-only "check these" flags for human review (rules §16). Never modifies
 * records. Deterministic, so it works without any AI provider.
 *
 * - possible_duplicate: posted transactions with identical type, date, amount,
 *   currency and counterparty.
 * - missing_information: posted transactions with neither description nor counterparty.
 *
 * TODO: BUSINESS DECISION REQUIRED — what counts as an "unusual" transaction
 * (thresholds, baseline period, per-category rules) and which fields are
 * mandatory for "missing information". Unusual-transaction flags are NOT
 * implemented until defined.
 */
class TransactionReviewFlagService
{
    /**
     * @return list<array{flag:string,label:string,transactions:list<string>}>
     */
    public function flags(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $txns = FinancialTransaction::query()
            ->where('enterprise_id', $enterprise->id)
            ->where('status', TransactionStatus::Posted->value)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->get(['public_id', 'type', 'transaction_date', 'amount', 'currency', 'counterparty', 'description']);

        $flags = [];

        $txns->groupBy(fn ($t) => implode('|', [
            $t->type->value, $t->transaction_date->toDateString(), $t->amount, $t->currency, mb_strtolower(trim((string) $t->counterparty)),
        ]))->filter(fn ($g) => $g->count() > 1)->each(function ($g) use (&$flags) {
            $flags[] = ['flag' => 'possible_duplicate', 'label' => 'قد تكون عملية مكررة — يرجى التحقق', 'transactions' => $g->pluck('public_id')->values()->all()];
        });

        $missing = $txns->filter(fn ($t) => blank($t->description) && blank($t->counterparty));
        if ($missing->isNotEmpty()) {
            $flags[] = ['flag' => 'missing_information', 'label' => 'عمليات بدون وصف أو جهة — يرجى استكمالها', 'transactions' => $missing->pluck('public_id')->values()->all()];
        }

        return $flags;
    }
}
