<?php

namespace App\Dashboard;

use App\Ai\Models\AiEscalation;
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
            ->where('status', '!=', 'completed');

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

        $alerts = [];
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

        $readinessStatus = $assessment?->score !== null ? (string) $assessment->score : null;

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
    private function period(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = FinancialTransaction::query()->posted()
            ->where('enterprise_id', $enterprise->id)
            ->where('currency', $this->currency())
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
