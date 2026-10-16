<?php

namespace App\Support;

use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\MfiEligibilityCriterion;
use App\Models\MfiInstitution;
use App\Models\ReadinessAssessment;

/**
 * Compares an enterprise against configured MFI eligibility criteria (section 28).
 * Results are advisory/preparatory and never a financing approval.
 */
class MfiMatcher
{
    public const MET = 'met';
    public const NOT_MET = 'not_met';
    public const NEEDS_REVIEW = 'needs_review';

    public const LABELS = [
        'met' => 'الشرط مستوفى',
        'not_met' => 'الشرط غير مستوفى',
        'needs_review' => 'يحتاج مراجعة',
    ];

    /** @return list<array{criterion_id:int,label:string,result:string,result_label:string,reason:string}> */
    public function match(Enterprise $enterprise, MfiInstitution $institution): array
    {
        $assessment = ReadinessAssessment::query()
            ->where('enterprise_id', $enterprise->id)
            ->where('status', ReadinessAssessment::STATUS_FINALIZED)
            ->with('currentSnapshot')
            ->orderByDesc('finalized_at')->orderByDesc('id')
            ->first();

        return $institution->eligibilityCriteria()
            ->where('is_active', true)
            ->get()
            ->map(function (MfiEligibilityCriterion $criterion) use ($enterprise, $assessment): array {
                [$result, $reason] = $this->evaluate($criterion, $enterprise, $assessment);

                return [
                    'criterion_id' => $criterion->id,
                    'label' => $criterion->label_ar,
                    'result' => $result,
                    'result_label' => self::LABELS[$result],
                    'reason' => $reason,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{0:string,1:string} */
    private function evaluate(MfiEligibilityCriterion $criterion, Enterprise $enterprise, ?ReadinessAssessment $assessment): array
    {
        $params = $criterion->parameters ?? [];

        switch ($criterion->type) {
            case 'min_readiness_score':
                $score = $assessment?->frozenScore();
                if ($score === null || ! isset($params['min_score'])) {
                    return [self::NEEDS_REVIEW, 'لا يوجد تقييم جاهزية نهائي بنتيجة محسوبة.'];
                }

                return (float) $score >= (float) $params['min_score']
                    ? [self::MET, 'نتيجة الجاهزية '.$score.' من 100.']
                    : [self::NOT_MET, 'نتيجة الجاهزية '.$score.' أقل من الحد المحدد '.$params['min_score'].'.'];

            case 'mandatory_readiness_conditions_met':
                $mandatory = $assessment?->frozenMandatoryConditionsMet();
                if ($mandatory === null) {
                    return [self::NEEDS_REVIEW, 'حالة الشروط الإلزامية غير محسومة بعد.'];
                }

                return $mandatory
                    ? [self::MET, 'الشروط الإلزامية مستوفاة في آخر تقييم نهائي.']
                    : [self::NOT_MET, 'يوجد شرط إلزامي غير مستوفى في آخر تقييم نهائي.'];

            case 'compliance_requirement_completed':
                $record = EnterpriseComplianceRecord::query()
                    ->where('enterprise_id', $enterprise->id)
                    ->where('compliance_requirement_id', $params['compliance_requirement_id'] ?? 0)
                    ->first();
                if ($record === null) {
                    return [self::NEEDS_REVIEW, 'لا توجد بيانات عن هذا المتطلب.'];
                }

                return $record->isCompleted()
                    ? [self::MET, 'متطلب الامتثال مكتمل.']
                    : [self::NOT_MET, 'متطلب الامتثال غير مكتمل.'];

            default:
                return [self::NEEDS_REVIEW, 'هذا الشرط يحتاج مراجعة يدوية من المستشار.'];
        }
    }
}
