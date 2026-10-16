<?php

namespace App\Support;

use App\Models\ReadinessAssessment;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessRuleSet;

/**
 * Explains an assessment from its own (frozen) rule set. The score is advisory and is NOT a financing approval (section 25).
 *
 * Scoring matrix v2:
 *  - every criterion yields points 0..1 according to its type (boolean, numeric_bands, choice, target_ratio, manual_scale);
 *  - pillar score = weighted average of its criteria points x 100 (weights = criterion weight, counted criteria only);
 *  - overall score = weighted average of pillar scores (weights = pillar weight, pillars with a score only).
 * Each criterion's missing-data treatment still decides whether missing data is counted as unmet, excluded, or sent for review.
 *
 * Legacy rule sets (no pillars, boolean criteria) produce exactly the v1 score: earned weight / counted weight x 100.
 *
 * Decision (manager, BEACON-DECISIONS.md): weights, min scores and bands come only from admin data; nothing is hardcoded.
 * Decision: default pillar weight - a pillar without an admin weight (0/null, incl. the implicit pillar of criteria with no
 *   pillar) gets an equal share: weight 1 when no pillar has a weight, otherwise the average of the admin-set pillar weights.
 */
class ReadinessScorer
{
    public const IMPLICIT_PILLAR = '_default';

    /**
     * Unchanged name and v1 keys (score, mandatory_conditions_met, earned_weight, counted_weight, unmet_mandatory,
     * needs_review, missing, unmet). v2 adds band, blocking, pillars and improvements. The full array is snapshotted.
     *
     * @return array<string, mixed>
     */
    public function evaluate(ReadinessAssessment $assessment): array
    {
        $assessment->loadMissing('results.criterion.pillar', 'ruleSet.bands');

        $items = $assessment->results->map(fn ($r) => [
            'criterion' => $r->criterion,
            'outcome' => $r->outcome,
            'value' => $r->value,
        ])->all();

        return $this->evaluateItems($items, $assessment->ruleSet?->bands?->all() ?? []);
    }

    /**
     * Pure evaluation of a rule set against answers (used by the admin preview). Writes nothing.
     * Unanswered criteria count as "missing".
     *
     * @param  array<int|string, array{outcome?: string|null, value?: mixed}>  $answers keyed by criterion id
     * @return array<string, mixed>
     */
    public function evaluateAnswers(ReadinessRuleSet $ruleSet, array $answers): array
    {
        $ruleSet->loadMissing('criteria.pillar', 'bands');

        $items = [];
        foreach ($ruleSet->criteria->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $criterion) {
            $answer = $answers[$criterion->id] ?? null;
            $value = $answer['value'] ?? null;
            $outcome = $answer['outcome'] ?? null;
            if ($outcome === null) {
                $outcome = $value === null ? 'missing' : null;
            }
            $items[] = ['criterion' => $criterion, 'outcome' => $outcome, 'value' => $value];
        }

        return $this->evaluateItems($items, $ruleSet->bands->all());
    }

    /** Points 0..1 for a value under a criterion type/config; null when the value cannot be scored. */
    public static function points(string $type, ?array $config, mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $config ??= [];

        switch ($type) {
            case ReadinessCriterion::TYPE_BOOLEAN:
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1.0 : 0.0;

            case ReadinessCriterion::TYPE_NUMERIC_BANDS:
                if (! is_numeric($value)) {
                    return null;
                }
                $v = (float) $value;
                $bands = $config['bands'] ?? [];
                $maxPoints = max(array_map(fn ($b) => (float) ($b['points'] ?? 0), $bands ?: [['points' => 0]]));
                if ($maxPoints <= 0) {
                    return 0.0;
                }
                foreach ($bands as $band) {
                    $min = $band['min'] ?? null;
                    $max = $band['max'] ?? null;
                    if (($min === null || $v >= (float) $min) && ($max === null || $v <= (float) $max)) {
                        return self::clamp((float) $band['points'] / $maxPoints);
                    }
                }

                return 0.0;

            case ReadinessCriterion::TYPE_CHOICE:
                $options = $config['options'] ?? [];
                $maxPoints = max(array_map(fn ($o) => (float) ($o['points'] ?? 0), $options ?: [['points' => 0]]));
                foreach ($options as $option) {
                    if ((string) $option['value'] === (string) $value) {
                        return $maxPoints > 0 ? self::clamp((float) $option['points'] / $maxPoints) : 0.0;
                    }
                }

                return 0.0;

            case ReadinessCriterion::TYPE_TARGET_RATIO:
                if (! is_numeric($value)) {
                    return null;
                }
                $v = (float) $value;
                $target = (float) ($config['target'] ?? 0);
                $cap = (float) ($config['cap'] ?? 1);
                if (($config['direction'] ?? 'higher') === 'lower') {
                    $ratio = $v <= 0 ? $cap : $target / $v;
                } else {
                    $ratio = $target <= 0 ? $cap : $v / $target;
                }

                return self::clamp(min($ratio, $cap));

            case ReadinessCriterion::TYPE_MANUAL_SCALE:
                if (! is_numeric($value)) {
                    return null;
                }
                $min = (float) ($config['min'] ?? 0);
                $max = (float) ($config['max'] ?? 0);

                return $max > $min ? self::clamp(((float) $value - $min) / ($max - $min)) : null;
        }

        return null;
    }

    /**
     * @param  list<array{criterion: ReadinessCriterion, outcome: string|null, value: mixed}>  $items
     * @param  list<\App\Models\ReadinessScoreBand>  $bands
     */
    private function evaluateItems(array $items, array $bands): array
    {
        $earned = 0.0;
        $counted = 0.0;
        $unmetMandatory = [];
        $needsReview = [];
        $missing = [];
        $unmet = [];
        $reviewMandatory = false;

        /** @var array<string, array{meta: array, rows: list<array>, earned: float, counted: float}> $pillars */
        $pillars = [];

        foreach ($items as $item) {
            /** @var ReadinessCriterion $criterion */
            $criterion = $item['criterion'];
            $weight = (float) $criterion->weight;
            $type = $criterion->type ?: ReadinessCriterion::TYPE_BOOLEAN;
            $value = $item['value'];
            $outcome = $item['outcome'];
            $points = null;

            // A scored value (v2) wins over the stored outcome; boolean without value keeps the v1 outcome.
            if ($value !== null && $value !== '' && $outcome !== 'needs_review') {
                $points = self::points($type, $criterion->config, $value);
                if ($points !== null) {
                    $outcome = $points > 0 ? 'met' : 'not_met';
                }
            }
            $outcome ??= 'missing';
            $reported = $outcome;

            if ($outcome === 'missing') {
                $missing[] = $criterion->code;
                $outcome = match ($criterion->missing_data_treatment) {
                    'counts_as_unmet' => 'not_met',
                    'excluded' => 'excluded',
                    default => 'needs_review',
                };
            }

            if ($outcome === 'met') {
                $points ??= 1.0;
            } elseif ($outcome === 'not_met') {
                $points = 0.0;
            } else {
                $points = null;
            }

            if ($points !== null) {
                $earned += $points * $weight;
                $counted += $weight;
                if ($points <= 0) {
                    $unmet[] = $criterion->code;
                    if ($criterion->is_mandatory) {
                        $unmetMandatory[] = $criterion->code;
                    }
                }
            } elseif ($outcome === 'needs_review') {
                $needsReview[] = $criterion->code;
                $reviewMandatory = $reviewMandatory || $criterion->is_mandatory;
            }

            $pillar = $criterion->pillar;
            $key = $pillar?->code ?? self::IMPLICIT_PILLAR;
            $pillars[$key] ??= [
                'meta' => [
                    'code' => $key,
                    'label_ar' => $pillar?->label_ar,
                    'weight' => $pillar ? (float) $pillar->weight : null,
                    'min_score' => $pillar?->min_score !== null ? (float) $pillar->min_score : null,
                    'sort' => $pillar ? [(int) $pillar->sort_order, (int) $pillar->id] : [PHP_INT_MAX, PHP_INT_MAX],
                ],
                'rows' => [], 'earned' => 0.0, 'counted' => 0.0,
            ];
            $pillars[$key]['rows'][] = [
                'code' => $criterion->code,
                'label_ar' => $criterion->label_ar,
                'type' => $type,
                'value' => $value,
                'points' => $points,
                'weight' => $weight,
                'outcome' => $reported,
                'sort' => [(int) $criterion->sort_order, (int) $criterion->id],
            ];
            if ($points !== null) {
                $pillars[$key]['earned'] += $points * $weight;
                $pillars[$key]['counted'] += $weight;
            }
        }

        $mandatoryMet = $unmetMandatory !== [] ? false : ($reviewMandatory ? null : true);

        uasort($pillars, fn ($a, $b) => $a['meta']['sort'] <=> $b['meta']['sort']);

        // Default pillar weight (decision): pillars without an admin weight get an equal share.
        $onlyImplicit = array_keys($pillars) === [self::IMPLICIT_PILLAR];
        $setWeights = array_values(array_filter(array_map(fn ($p) => $p['meta']['weight'], $pillars), fn ($w) => $w !== null && $w > 0));
        $defaultWeight = $setWeights === [] ? 1.0 : array_sum($setWeights) / count($setWeights);
        foreach ($pillars as $key => &$p) {
            if ($p['meta']['weight'] === null || $p['meta']['weight'] <= 0) {
                $p['meta']['weight'] = $defaultWeight;
            }
            $p['score_raw'] = $p['counted'] > 0 ? $p['earned'] / $p['counted'] * 100 : null;
        }
        unset($p);

        $totalPillarWeight = 0.0;
        $weighted = 0.0;
        foreach ($pillars as $p) {
            if ($p['score_raw'] !== null && $p['meta']['weight'] > 0) {
                $totalPillarWeight += $p['meta']['weight'];
                $weighted += $p['score_raw'] * $p['meta']['weight'];
            }
        }

        if ($onlyImplicit || $pillars === []) {
            // Exactly the v1 formula.
            $score = $counted > 0 ? round($earned / $counted * 100, 2) : null;
        } else {
            $score = $totalPillarWeight > 0 ? round($weighted / $totalPillarWeight, 2) : null;
        }

        $blocking = [];
        $pillarOut = [];
        $improvements = [];
        foreach ($pillars as $p) {
            $share = ($p['score_raw'] !== null && $totalPillarWeight > 0 && $p['meta']['weight'] > 0)
                ? $p['meta']['weight'] / $totalPillarWeight : 0.0;
            $isBlocking = $p['meta']['min_score'] !== null && $p['score_raw'] !== null && $p['score_raw'] < $p['meta']['min_score'];
            if ($isBlocking) {
                $blocking[] = $p['meta']['code'];
            }

            usort($p['rows'], fn ($a, $b) => $a['sort'] <=> $b['sort']);
            $criteria = [];
            foreach ($p['rows'] as $row) {
                $unit = ($row['points'] !== null && $p['counted'] > 0) ? $row['weight'] / $p['counted'] * $share * 100 : 0.0;
                if ($row['points'] !== null && $row['points'] < 1 && $unit > 0) {
                    $improvements[] = ['code' => $row['code'], 'label_ar' => $row['label_ar'], 'potential_gain' => round((1 - $row['points']) * $unit, 2)];
                }
                unset($row['sort']);
                $row['contribution'] = $row['points'] !== null ? round($row['points'] * $unit, 2) : 0.0;
                $criteria[] = $row;
            }

            $pillarOut[] = [
                'code' => $p['meta']['code'],
                'label_ar' => $p['meta']['label_ar'],
                'weight' => (float) $p['meta']['weight'],
                'score' => $p['score_raw'] !== null ? round($p['score_raw'], 2) : null,
                'min_score' => $p['meta']['min_score'],
                'blocking' => $isBlocking,
                'contribution' => $p['score_raw'] !== null ? round($p['score_raw'] * $share, 2) : 0.0,
                'criteria' => $criteria,
            ];
        }
        foreach ($unmetMandatory as $code) {
            $blocking[] = $code;
        }
        usort($improvements, fn ($a, $b) => $b['potential_gain'] <=> $a['potential_gain']);

        return [
            'score' => $score,
            'mandatory_conditions_met' => $mandatoryMet,
            'earned_weight' => $earned,
            'counted_weight' => $counted,
            'unmet_mandatory' => $unmetMandatory,
            'needs_review' => $needsReview,
            'missing' => $missing,
            'unmet' => $unmet,
            'band' => self::band($score, $bands),
            'blocking' => $blocking,
            'pillars' => $pillarOut,
            // Same array as "pillars"; the name the snapshot / result page reads.
            'breakdown' => $pillarOut,
            'improvements' => $improvements,
        ];
    }

    /** @param list<\App\Models\ReadinessScoreBand> $bands */
    private static function band(?float $score, array $bands): ?array
    {
        if ($score === null) {
            return null;
        }
        // Highest band first, so a shared boundary (e.g. 70 = max of one band and min of the next) goes to the upper band.
        usort($bands, fn ($a, $b) => (float) $b->min <=> (float) $a->min);
        foreach ($bands as $band) {
            if ($score >= (float) $band->min && $score <= (float) $band->max) {
                // "name" mirrors label_ar so SnapshotAssessmentScore fills its band column without changes there.
                return ['label_ar' => $band->label_ar, 'name' => $band->label_ar, 'color_token' => $band->color_token, 'min' => (float) $band->min, 'max' => (float) $band->max];
            }
        }

        return null;
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }
}
