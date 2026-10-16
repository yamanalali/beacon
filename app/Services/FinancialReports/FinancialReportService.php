<?php

namespace App\Services\FinancialReports;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\TransactionSettlement;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Deterministic financial reports calculated from stored, posted records.
 *
 * Documented rules (see docs in each method):
 * - Only POSTED transactions count. Superseded (corrected) originals and
 *   their settlements are excluded, so corrections never double count.
 * - Settlements are cash movements only; they never add revenue/expense.
 * - Totals are always per original currency. No conversion is performed.
 *
 * TODO: BUSINESS DECISION REQUIRED
 * - Reporting basis (accrual vs cash) for the income statement. Both are
 *   offered and clearly labelled until Beacon decides; default = accrual.
 * - Opening-balance handling (currently: none; cash flow shows movements only).
 * - Treatment of unpaid transactions (accrual basis includes them; cash basis excludes them).
 * - Exact report periods (currently: any user-chosen date range).
 * - Multi-currency consolidation and base currency (currently: none, per currency).
 * - Taxes (not represented) and period closing (not implemented).
 */
class FinancialReportService
{
    public const REPORTS = [
        'income_statement' => 'قائمة الدخل (الأرباح والخسائر)',
        'cash_flow' => 'ملخص التدفق النقدي',
        'sales' => 'تقرير المبيعات',
        'expenses' => 'تقرير المصاريف',
        'receivables' => 'تقرير المبالغ المستحقة لك (الذمم المدينة)',
        'payables' => 'تقرير المبالغ المستحقة عليك (الذمم الدائنة)',
        'monthly' => 'الأداء المالي الشهري',
    ];

    public const BASES = [
        'accrual' => 'حسب تاريخ العملية (يشمل غير المدفوع)',
        'cash' => 'حسب الدفعات الفعلية',
    ];

    public function generate(string $key, Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to, string $basis = 'accrual'): FinancialReport
    {
        if (! array_key_exists($key, self::REPORTS) || ! array_key_exists($basis, self::BASES)) {
            throw new InvalidArgumentException('Unknown report or basis.');
        }

        $period = $from->toDateString().' — '.$to->toDateString();

        $sections = match ($key) {
            'income_statement' => $this->incomeStatement($enterprise, $from, $to, $basis),
            'cash_flow' => $this->cashFlow($enterprise, $from, $to),
            'sales' => $this->byCategory($enterprise, $from, $to, [TransactionType::Sale, TransactionType::Income], 'المبيعات والدخل'),
            'expenses' => $this->byCategory($enterprise, $from, $to, [TransactionType::Purchase, TransactionType::Expense], 'المشتريات والمصاريف'),
            'receivables' => $this->outstanding($enterprise, $to, true),
            'payables' => $this->outstanding($enterprise, $to, false),
            'monthly' => $this->monthly($enterprise, $from, $to),
        };

        $notes = ['المبالغ محسوبة من السجلات المحفوظة فقط، ولكل عملة على حدة دون تحويل.'];
        if ($key === 'income_statement') {
            $notes[] = 'أساس الاحتساب: '.self::BASES[$basis].'.';
        }
        if (in_array($key, ['receivables', 'payables'], true)) {
            $notes[] = 'المبالغ المتبقية حتى تاريخ '.$to->toDateString().'.';
        }

        return new FinancialReport($key, self::REPORTS[$key], $period, $sections, $notes);
    }

    /**
     * Accrual: posted sales+income minus purchases+expenses dated in period.
     * Cash: settlements (received minus paid) dated in period.
     */
    private function incomeStatement(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to, string $basis): array
    {
        $totals = [];

        if ($basis === 'accrual') {
            foreach ($this->transactions($enterprise, $from, $to) as $t) {
                $this->add($totals, $t->currency, $t->type->isInflow() ? 'in' : 'out', $t->amountMinor());
            }
        } else {
            foreach ($this->settlements($enterprise, $from, $to) as $s) {
                $this->add($totals, $s->transaction->currency, $s->transaction->type->isInflow() ? 'in' : 'out', Money::toMinor((string) $s->amount));
            }
        }

        return [$this->section('الملخص', ['العملة', 'الإيرادات', 'المصاريف والمشتريات', 'صافي الربح أو الخسارة'], $this->inOutRows($totals))];
    }

    /** Cash received / paid by settlement date (no opening balance). */
    private function cashFlow(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = [];
        foreach ($this->settlements($enterprise, $from, $to) as $s) {
            $this->add($totals, $s->transaction->currency, $s->transaction->type->isInflow() ? 'in' : 'out', Money::toMinor((string) $s->amount));
        }

        return [$this->section('حركة النقد', ['العملة', 'النقد الداخل', 'النقد الخارج', 'صافي الحركة'], $this->inOutRows($totals))];
    }

    /**
     * @param  list<TransactionType>  $types
     */
    private function byCategory(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to, array $types, string $title): array
    {
        $groups = [];
        foreach ($this->transactions($enterprise, $from, $to) as $t) {
            if (! in_array($t->type, $types, true)) {
                continue;
            }
            $label = config('bookkeeping.categories.'.$t->type->value.'.'.$t->category, $t->category);
            $k = $t->currency.'|'.$t->type->label().'|'.$label;
            $groups[$k] ??= ['count' => 0, 'amount' => 0, 'outstanding' => 0];
            $groups[$k]['count']++;
            $groups[$k]['amount'] += $t->amountMinor();
            $groups[$k]['outstanding'] += $t->outstandingMinor();
        }
        ksort($groups);

        $rows = [];
        foreach ($groups as $k => $g) {
            [$currency, $type, $label] = explode('|', $k);
            $rows[] = [$currency, $type, $label, (string) $g['count'], Money::fromMinor($g['amount']), Money::fromMinor($g['amount'] - $g['outstanding']), Money::fromMinor($g['outstanding'])];
        }

        return [$this->section($title, ['العملة', 'النوع', 'التصنيف', 'عدد العمليات', 'المبلغ', 'المدفوع', 'المتبقي'], $rows)];
    }

    /** Open balances of posted transactions dated up to $asOf. */
    private function outstanding(Enterprise $enterprise, CarbonImmutable $asOf, bool $inflow): array
    {
        $rows = [];
        $totals = [];

        $transactions = FinancialTransaction::query()
            ->posted()
            ->where('enterprise_id', $enterprise->id)
            ->whereIn('type', $inflow ? ['sale', 'income'] : ['purchase', 'expense'])
            ->whereDate('transaction_date', '<=', $asOf->toDateString())
            ->with(['settlements' => fn ($q) => $q->whereDate('settlement_date', '<=', $asOf->toDateString())])
            ->orderBy('transaction_date')
            ->get();

        foreach ($transactions as $t) {
            $left = $t->outstandingMinor();
            if ($left <= 0) {
                continue;
            }
            $rows[] = [$t->transaction_date->toDateString(), $t->counterparty ?? '—', $t->type->label(), $t->currency, Money::fromMinor($t->amountMinor()), Money::fromMinor($left)];
            $totals[$t->currency] = ($totals[$t->currency] ?? 0) + $left;
        }

        ksort($totals);
        $totalRows = array_map(fn ($c, $v) => [$c, Money::fromMinor($v)], array_keys($totals), $totals);

        return [
            $this->section('المجموع حسب العملة', ['العملة', 'المبلغ المتبقي'], $totalRows),
            $this->section('التفاصيل', ['التاريخ', 'الجهة', 'النوع', 'العملة', 'المبلغ الأصلي', 'المتبقي'], $rows),
        ];
    }

    private function monthly(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $data = [];
        foreach ($this->transactions($enterprise, $from, $to) as $t) {
            $k = $t->transaction_date->format('Y-m').'|'.$t->currency;
            $data[$k] ??= ['in' => 0, 'out' => 0, 'cash_in' => 0, 'cash_out' => 0];
            $data[$k][$t->type->isInflow() ? 'in' : 'out'] += $t->amountMinor();
        }
        foreach ($this->settlements($enterprise, $from, $to) as $s) {
            $k = $s->settlement_date->format('Y-m').'|'.$s->transaction->currency;
            $data[$k] ??= ['in' => 0, 'out' => 0, 'cash_in' => 0, 'cash_out' => 0];
            $data[$k][$s->transaction->type->isInflow() ? 'cash_in' : 'cash_out'] += Money::toMinor((string) $s->amount);
        }
        ksort($data);

        $rows = [];
        foreach ($data as $k => $v) {
            [$month, $currency] = explode('|', $k);
            $rows[] = [$month, $currency, Money::fromMinor($v['in']), Money::fromMinor($v['out']), Money::fromMinor($v['in'] - $v['out']), Money::fromMinor($v['cash_in']), Money::fromMinor($v['cash_out'])];
        }

        return [$this->section('حسب الشهر', ['الشهر', 'العملة', 'الإيرادات', 'المصاريف والمشتريات', 'الصافي', 'النقد الداخل', 'النقد الخارج'], $rows)];
    }

    /** @return Collection<int, FinancialTransaction> */
    private function transactions(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return FinancialTransaction::query()
            ->posted()
            ->where('enterprise_id', $enterprise->id)
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->with('settlements')
            ->get();
    }

    /** @return Collection<int, TransactionSettlement> */
    private function settlements(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return TransactionSettlement::query()
            ->whereHas('transaction', fn ($q) => $q->posted()->where('enterprise_id', $enterprise->id))
            ->whereDate('settlement_date', '>=', $from->toDateString())
            ->whereDate('settlement_date', '<=', $to->toDateString())
            ->with('transaction')
            ->get();
    }

    private function add(array &$totals, string $currency, string $side, int $minor): void
    {
        $totals[$currency] ??= ['in' => 0, 'out' => 0];
        $totals[$currency][$side] += $minor;
    }

    private function inOutRows(array $totals): array
    {
        ksort($totals);

        return array_values(array_map(
            fn ($c, $v) => [$c, Money::fromMinor($v['in']), Money::fromMinor($v['out']), Money::fromMinor($v['in'] - $v['out'])],
            array_keys($totals),
            $totals,
        ));
    }

    private function section(string $title, array $columns, array $rows): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => array_values($rows)];
    }
}
