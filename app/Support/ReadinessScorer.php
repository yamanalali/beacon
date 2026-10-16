<?php

namespace App\Support;

use App\Models\ReadinessAssessment;

/**
 * Explains an assessment from its own (frozen) rule set. The score is advisory and is NOT a financing approval (section 25).
 *
 * TODO: BUSINESS DECISION REQUIRED - Beacon must approve the scoring method. The current method is a transparent
 * weighted share: (sum of weights of met criteria) / (sum of weights of criteria counted) x 100, where each criterion's
 * missing-data treatment (chosen by Beacon per criterion) decides whether missing data is counted as unmet, excluded,
 * or sent for review. No score bands/interpretation are applied until Beacon provides them.
 */
class ReadinessScorer
{
    /**
     * @return array{score: float|null, mandatory_conditions_met: bool|null, earned_weight: float, counted_weight: float,
     *     unmet_mandatory: list<string>, needs_review: list<string>, missing: list<string>, unmet: list<string>}
     */
    public function evaluate(ReadinessAssessment $assessment): array
    {
        $assessment->loadMissing('results.criterion');

        $earned = 0.0;
        $counted = 0.0;
        $unmetMandatory = [];
        $needsReview = [];
        $missing = [];
        $unmet = [];

        foreach ($assessment->results as $result) {
            $criterion = $result->criterion;
            $weight = (float) $criterion->weight;
            $outcome = $result->outcome;

            if ($outcome === 'missing') {
                $missing[] = $criterion->code;
                $outcome = match ($criterion->missing_data_treatment) {
                    'counts_as_unmet' => 'not_met',
                    'excluded' => 'excluded',
                    default => 'needs_review',
                };
            }

            switch ($outcome) {
                case 'met':
                    $earned += $weight;
                    $counted += $weight;
                    break;
                case 'not_met':
                    $counted += $weight;
                    $unmet[] = $criterion->code;
                    if ($criterion->is_mandatory) {
                        $unmetMandatory[] = $criterion->code;
                    }
                    break;
                case 'needs_review':
                    $needsReview[] = $criterion->code;
                    break;
            }
        }

        $mandatoryMet = $unmetMandatory !== [] ? false : null;
        if ($unmetMandatory === []) {
            $reviewMandatory = $assessment->results->filter(
                fn ($r) => $r->criterion->is_mandatory && in_array($r->criterion->code, $needsReview, true)
            );
            $mandatoryMet = $reviewMandatory->isEmpty() ? true : null;
        }

        return [
            'score' => $counted > 0 ? round($earned / $counted * 100, 2) : null,
            'mandatory_conditions_met' => $mandatoryMet,
            'earned_weight' => $earned,
            'counted_weight' => $counted,
            'unmet_mandatory' => $unmetMandatory,
            'needs_review' => $needsReview,
            'missing' => $missing,
            'unmet' => $unmet,
        ];
    }
}
