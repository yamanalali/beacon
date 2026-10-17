<?php

namespace App\Support;

use App\Models\Document;
use App\Models\EnterpriseComplianceRecord;
use App\Models\FinancialTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Lang;
use App\Models\Referral;

/**
 * Builds ONLY the items the SME consented to disclose.
 * TODO: BUSINESS DECISION REQUIRED - exact data included in each referral package (section 30).
 */
class ReferralPackageBuilder
{
    /** @return array<string, mixed> */
    public function build(Referral $referral): array
    {
        $referral->loadMissing('enterprise', 'assessment.currentSnapshot');
        $items = $referral->disclosed_items ?? [];
        $package = [];

        if (in_array('enterprise_profile', $items, true)) {
            $e = $referral->enterprise;
            $package['enterprise_profile'] = [
                'business_name' => $e->business_name,
                'sector' => $e->sector,
                'sector_label' => $this->label('sectors', $e->sector),
                'location' => $e->location,
                'founding_date' => $e->founding_date?->toDateString(),
                'employee_count' => $e->employee_count,
                'registration_status' => $e->registration_status,
                'registration_status_label' => $this->label('registration_statuses', $e->registration_status),
            ];
        }

        if (in_array('readiness_assessment', $items, true) && $referral->assessment !== null) {
            $a = $referral->assessment;
            $package['readiness_assessment'] = [
                // Frozen current snapshot (row value only for legacy rows without one).
                'score' => $a->frozenScore(),
                'mandatory_conditions_met' => $a->frozenMandatoryConditionsMet(),
                'band' => $a->frozenBand(),
                'finalized_at' => $a->finalized_at?->toDateTimeString(),
                'rule_set_version' => $a->ruleSet?->version_number,
                'disclaimer' => 'نتيجة الجاهزية أداة استشارية وليست موافقة على التمويل.',
            ];
        }

        if (in_array('compliance_status', $items, true)) {
            $package['compliance_status'] = EnterpriseComplianceRecord::query()
                ->where('enterprise_id', $referral->enterprise_id)
                ->with('version')
                ->get()
                ->map(fn ($r) => [
                    'requirement' => $r->version?->title_ar,
                    'version' => $r->version?->version_number,
                    'status' => EnterpriseComplianceRecord::STATUSES[$r->status] ?? $r->status,
                ])->all();
        }

        if (in_array('financial_summary', $items, true)) {
            $package['financial_summary'] = $this->financialSummary($referral->enterprise_id);
        }

        if (in_array('documents', $items, true)) {
            $package['documents'] = Document::query()->active()
                ->where('enterprise_id', $referral->enterprise_id)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->get(['title', 'original_name', 'mime_type', 'created_at'])
                ->map(fn (Document $d) => [
                    'name' => $d->title ?: $d->original_name,
                    'type' => Lang::has('referrals.document_types.'.$d->mime_type, 'ar')
                        ? __('referrals.document_types.'.$d->mime_type, [], 'ar')
                        : __('referrals.document_types.other', [], 'ar'),
                    'date' => $d->created_at?->toDateString(),
                ])->values()->all();
        }

        return $package;
    }

    /** Arabic label for a known code; free text (or unknown code) is returned as entered. */
    private function label(string $group, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $key = "referrals.$group.".strtolower(trim($value));

        return Lang::has($key, 'ar') ? (string) __($key, [], 'ar') : $value;
    }

    /**
     * Posted transactions of this enterprise only, totals per currency (no conversion; manager decision).
     *
     * @return array{period: array{from: string, to: string}, currencies: list<array{currency: string, income_total: string, expense_total: string, net: string, transaction_count: int}>}
     */
    private function financialSummary(int $enterpriseId): array
    {
        // TODO: BUSINESS DECISION REQUIRED - summary period. Default: last 12 months up to today.
        $to = CarbonImmutable::today();
        $from = $to->subMonthsNoOverflow(12)->addDay();

        $totals = [];
        FinancialTransaction::query()->posted()
            ->where('enterprise_id', $enterpriseId)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('currency')
            ->get(['type', 'amount', 'currency'])
            ->each(function (FinancialTransaction $t) use (&$totals): void {
                $c = (string) $t->currency;
                $totals[$c] ??= ['currency' => $c, 'income_total' => '0.00', 'expense_total' => '0.00', 'net' => '0.00', 'transaction_count' => 0];
                $amount = (string) $t->getAttributes()['amount'];
                $field = $t->type->isInflow() ? 'income_total' : 'expense_total';
                $totals[$c][$field] = bcadd($totals[$c][$field], $amount, 2);
                $totals[$c]['transaction_count']++;
            });

        foreach ($totals as &$row) {
            $row['net'] = bcsub($row['income_total'], $row['expense_total'], 2);
        }
        unset($row);
        ksort($totals);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currencies' => array_values($totals),
        ];
    }
}
