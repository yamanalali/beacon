<?php

namespace App\Support;

use App\Models\EnterpriseComplianceRecord;
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
        $referral->loadMissing('enterprise', 'assessment');
        $items = $referral->disclosed_items ?? [];
        $package = [];

        if (in_array('enterprise_profile', $items, true)) {
            $e = $referral->enterprise;
            $package['enterprise_profile'] = [
                'business_name' => $e->business_name,
                'sector' => $e->sector,
                'location' => $e->location,
                'founding_date' => $e->founding_date?->toDateString(),
                'employee_count' => $e->employee_count,
                'registration_status' => $e->registration_status,
            ];
        }

        if (in_array('readiness_assessment', $items, true) && $referral->assessment !== null) {
            $a = $referral->assessment;
            $package['readiness_assessment'] = [
                'score' => $a->score,
                'mandatory_conditions_met' => $a->mandatory_conditions_met,
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

        return $package;
    }
}
