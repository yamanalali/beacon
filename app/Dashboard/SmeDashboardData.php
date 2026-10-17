<?php

namespace App\Dashboard;

use App\Ai\Models\AiEscalation;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\FinancialTransaction;
use App\Models\ReadinessAssessment;
use App\Models\ReceiptExtraction;
use App\Models\Referral;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Server-side props for the SME dashboard (§40). Every query is scoped to the
 * single enterprise the SME user is a member of; nothing is computed in React.
 */
class SmeDashboardData
{
    private const INCOME_TYPES = [TransactionType::Sale, TransactionType::Income];

    private const EXPENSE_TYPES = [TransactionType::Purchase, TransactionType::Expense];

    /** @return array<string, mixed> */
    public function build(User $user, ?CarbonImmutable $today = null): array
    {
        // TODO: BUSINESS DECISION REQUIRED - reporting timezone. The app runs in UTC, so month boundaries
        // (current/previous month, due dates) are UTC dates; Istanbul users (UTC+3) see them 3h off, e.g.
        // between 00:00 and 03:00 local time on the 1st the previous month is still treated as current.
        $today ??= CarbonImmutable::today();
        $enterprise = Enterprise::query()
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->orderBy('id')
            ->first();

        if ($enterprise === null) {
            return $this->empty();
        }

        $recent = FinancialTransaction::query()->posted()
            ->where('enterprise_id', $enterprise->id)
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->limit(5)->get();

        $pendingExtractions = ReceiptExtraction::query()
            ->where('enterprise_id', $enterprise->id)
            ->pendingReview()
            ->count();

        $openCompliance = EnterpriseComplianceRecord::query()->with('version')
            ->where('enterprise_id', $enterprise->id)
            ->whereNotNull('due_date')
            ->forCurrentVersion()->where('status', '!=', 'completed');

        $overdue = (clone $openCompliance)->where('due_date', '<', $today->toDateString())
            ->orderBy('due_date')->get();
        $upcoming = (clone $openCompliance)
            ->whereBetween('due_date', [$today->toDateString(), $today->addDays(max(0, (int) config('reminders.compliance.lead_days', 7)))->toDateString()])
            ->orderBy('due_date')->get();

        // TODO: BUSINESS DECISION REQUIRED - escalation status set (only "open" exists today).
        $openEscalations = AiEscalation::query()
            ->where('enterprise_id', $enterprise->id)
            ->where('status', 'open')
            ->count();

        $assessment = ReadinessAssessment::query()
            ->where('enterprise_id', $enterprise->id)
            ->where('status', ReadinessAssessment::STATUS_FINALIZED)
            ->with('currentSnapshot')
            ->orderByDesc('finalized_at')->orderByDesc('id')
            ->first();

        $referral = Referral::query()->where('enterprise_id', $enterprise->id)
            ->orderByDesc('id')->first();

        $recentRows = $recent->map(fn (FinancialTransaction $t) => [
            'public_id' => $t->public_id,
            'type' => $t->type->value,
            'direction' => in_array($t->type, self::INCOME_TYPES, true) ? 'income' : 'expense',
            'transaction_date' => $t->transaction_date->toDateString(),
            'amount' => (string) $t->amount,
            'currency' => $t->currency,
            'category' => $t->category,
            'counterparty' => $t->counterparty,
            'url' => route('transactions.show', $t),
        ])->values()->all();

        $deadlineRow = fn (EnterpriseComplianceRecord $r) => [
            'id' => $r->id,
            'title' => $r->version?->title_ar ?? '',
            'due_date' => $r->due_date->toDateString(),
            'status' => $r->status,
            'overdue' => $r->due_date->lt($today),
        ];

        $versionReviews = EnterpriseComplianceRecord::query()->where('enterprise_id', $enterprise->id)->needingVersionReview()->count();
        $alerts = [];
        if ($versionReviews > 0) {
            $alerts[] = ['key' => 'compliance_version_review', 'severity' => 'warning', 'count' => $versionReviews,
                'title' => 'متطلبات امتثال محدثة تحتاج مراجعة', 'href' => route('enterprises.compliance.index', $enterprise)];
        }
        if ($overdue->isNotEmpty()) {
            $alerts[] = [
                'key' => 'overdue_compliance',
                'severity' => 'danger',
                'count' => $overdue->count(),
                'title' => 'مواعيد امتثال متأخرة',
                // No overdue query filter on enterprises.compliance.index today.
                'href' => route('enterprises.compliance.index', $enterprise),
            ];
        }
        if ($pendingExtractions > 0) {
            $alerts[] = [
                'key' => 'pending_reviews',
                'severity' => 'warning',
                'count' => $pendingExtractions,
                'title' => 'فواتير بانتظار المراجعة',
                // Same filter as the count above (ReceiptExtraction::pendingReview).
                'href' => route('enterprises.extractions.pending', $enterprise),
            ];
        }
        if ($openEscalations > 0) {
            $alerts[] = [
                'key' => 'open_escalations',
                'severity' => 'info',
                'count' => $openEscalations,
                'title' => 'استفسارات مفتوحة',
                'href' => route('ai.pages.escalations.index', ['status' => 'open']),
            ];
        }

        $frozenScore = $assessment?->frozenScore();
        $readinessStatus = $frozenScore !== null ? (string) $frozenScore : null;

        return [
            // Existing props (kept for the current page UI).
            'recentActivity' => array_map(fn ($r) => [
                'id' => $r['public_id'],
                'title' => $r['category'].($r['counterparty'] ? ' - '.$r['counterparty'] : ''),
                'meta' => $r['amount'].' '.$r['currency'].' · '.$r['transaction_date'],
            ], $recentRows),
            'outstandingActions' => array_map(fn ($a) => [
                'id' => $a['key'], 'title' => $a['title'], 'meta' => (string) $a['count'],
            ], $alerts),
            'upcomingDeadlines' => $overdue->concat($upcoming)->map(fn ($r) => [
                'id' => $r->id, 'title' => $r->version?->title_ar ?? '', 'meta' => $r->due_date->toDateString(),
            ])->values()->all(),
            // TODO: BUSINESS DECISION REQUIRED - readiness bands/interpretation; raw latest finalized score only.
            'readinessStatus' => $readinessStatus,
            'referralStatus' => $referral?->statusLabel(),
            // New structured props.
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'kpis' => [
                // TODO: BUSINESS DECISION REQUIRED - base/reporting currency and conversion. Enterprises have
                // no base currency yet, so totals use config('bookkeeping.default_currency') and only count
                // transactions recorded in that currency; other currencies are listed in other_currencies.
                'currency' => $this->currency(),
                'current_month' => $this->period($enterprise, $today->startOfMonth(), $today->endOfMonth()),
                'previous_month' => $this->period($enterprise, $today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()),
                'other_currencies' => FinancialTransaction::query()->posted()
                    ->where('enterprise_id', $enterprise->id)
                    ->where('currency', '!=', $this->currency())
                    ->distinct()->orderBy('currency')->pluck('currency')->all(),
                // Per-currency totals, no conversion (see byCurrency).
                'by_currency' => $this->byCurrency($enterprise, $today),
                'unposted_count' => $this->unpostedCount($enterprise, $pendingExtractions),
                'transaction_count' => FinancialTransaction::query()->posted()->where('enterprise_id', $enterprise->id)->count(),
                'pending_extractions_count' => $pendingExtractions,
                'readiness_score' => $readinessStatus,
                'overdue_compliance_count' => $overdue->count(),
                'upcoming_compliance_count' => $upcoming->count(),
                'open_escalations_count' => $openEscalations,
            ],
            'recent_transactions' => $recentRows,
            'compliance' => [
                'overdue' => $overdue->map($deadlineRow)->values()->all(),
                'upcoming' => $upcoming->map($deadlineRow)->values()->all(),
            ],
            'alerts' => $alerts,
        ];
    }

    private function currency(): string
    {
        return (string) config('bookkeeping.default_currency', 'SYP');
    }

    /** @return array{from: string, to: string, income: string, expense: string, net: string, count: int} */
    private function period(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to, ?string $currency = null): array
    {
        $rows = FinancialTransaction::query()->posted()
            ->where('enterprise_id', $enterprise->id)
            ->where('currency', $currency ?? $this->currency())
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->get(['type', 'amount']);

        $income = 0;
        $expense = 0;
        foreach ($rows as $row) {
            $minor = Money::toMinor((string) $row->amount);
            if (in_array($row->type, self::INCOME_TYPES, true)) {
                $income += $minor;
            } elseif (in_array($row->type, self::EXPENSE_TYPES, true)) {
                $expense += $minor;
            }
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'income' => Money::fromMinor($income),
            'expense' => Money::fromMinor($expense),
            'net' => Money::fromMinor($income - $expense),
            'count' => $rows->count(),
        ];
    }

    /**
     * Totals per currency for this enterprise's posted transactions only (drafts/superseded excluded):
     *  - current_month: transaction_date from the 1st to the last day of $today's month;
     *  - previous_month: the full calendar month before $today's month (subMonthNoOverflow, 1st..last day).
     * Dates are compared as app-timezone (UTC) dates. One row per currency the enterprise has ever posted in,
     * default currency first, zeros when a currency has no rows in that period.
     * Each currency is summed on its own; amounts are never converted or added across currencies.
     *
     * TODO: BUSINESS DECISION REQUIRED - exchange rate source/date and a reporting currency, if
     * totals across currencies are ever wanted. Until then each currency stays separate.
     *
     * @return array{current_month: list<array{currency: string, income_total: string, expense_total: string, net: string, transaction_count: int}>, previous_month: list<array{currency: string, income_total: string, expense_total: string, net: string, transaction_count: int}>}
     */
    private function byCurrency(Enterprise $enterprise, CarbonImmutable $today): array
    {
        $currencies = FinancialTransaction::query()->posted()
            ->where('enterprise_id', $enterprise->id)
            ->distinct()->orderBy('currency')->pluck('currency')->all();

        // Default currency first so the main card stays stable, then the rest alphabetically.
        $currencies = array_values(array_unique(array_merge([$this->currency()], $currencies)));
        $previous = $today->subMonthNoOverflow();

        // Item fields match the referral financial_summary items so the frontend reuses one type.
        // One grouped query per period (GROUP BY currency, type) instead of two queries per currency.
        $items = function (CarbonImmutable $from, CarbonImmutable $to) use ($enterprise, $currencies) {
            $rows = FinancialTransaction::query()->posted()
                ->where('enterprise_id', $enterprise->id)
                ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('currency', 'type')
                ->selectRaw('currency, type, COUNT(*) as row_count, SUM(amount) as total')
                ->toBase()->get();

            $totals = [];
            foreach ($rows as $row) {
                $t = &$totals[$row->currency];
                $t ??= ['income' => 0, 'expense' => 0, 'count' => 0];
                $type = TransactionType::tryFrom((string) $row->type);
                // SUM() may come back as a float (SQLite); amounts have 2 decimals, so format before converting.
                $minor = Money::toMinor(is_string($row->total) ? $row->total : number_format((float) $row->total, 2, '.', ''));
                if (in_array($type, self::INCOME_TYPES, true)) {
                    $t['income'] += $minor;
                } elseif (in_array($type, self::EXPENSE_TYPES, true)) {
                    $t['expense'] += $minor;
                }
                $t['count'] += (int) $row->row_count;
                unset($t);
            }

            return array_map(function (string $currency) use ($totals) {
                $t = $totals[$currency] ?? ['income' => 0, 'expense' => 0, 'count' => 0];

                return [
                    'currency' => $currency,
                    'income_total' => Money::fromMinor($t['income']),
                    'expense_total' => Money::fromMinor($t['expense']),
                    'net' => Money::fromMinor($t['income'] - $t['expense']),
                    'transaction_count' => $t['count'],
                ];
            }, $currencies);
        };

        return [
            'current_month' => $items($today->startOfMonth(), $today->endOfMonth()),
            'previous_month' => $items($previous->startOfMonth(), $previous->endOfMonth()),
        ];
    }

    /**
     * Items not yet counted in the totals: receipt extractions waiting for review, plus any
     * transaction in a non-final status (neither posted nor superseded by an edit).
     *
     * Note: TransactionStatus currently only has Posted and Superseded, so the transaction part is
     * always 0 today and unposted_count equals pending_extractions_count. The transaction part is
     * kept for future draft/in-review statuses.
     */
    private function unpostedCount(Enterprise $enterprise, int $pendingExtractions): int
    {
        $transactions = FinancialTransaction::query()
            ->where('enterprise_id', $enterprise->id)
            ->whereNotIn('status', [TransactionStatus::Posted->value, TransactionStatus::Superseded->value])
            ->count();

        return $pendingExtractions + $transactions;
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return [
            'recentActivity' => [],
            'outstandingActions' => [],
            'upcomingDeadlines' => [],
            'readinessStatus' => null,
            'referralStatus' => null,
            'enterprise' => null,
            'kpis' => null,
            'recent_transactions' => [],
            'compliance' => ['overdue' => [], 'upcoming' => []],
            'alerts' => [],
        ];
    }
}
