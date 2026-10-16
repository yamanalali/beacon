<?php

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessAssessmentSnapshot;
use App\Models\User;
use App\Support\ReadinessScorer;
use Illuminate\Support\Facades\DB;

/**
 * Freezes ReadinessScorer::evaluate() output. The whole evaluation array is stored as JSON, so scorer extensions
 * (pillars, band, improvements...) are captured without changes here. Never edits old snapshots' data.
 *
 * TODO: BUSINESS DECISION REQUIRED - automatic recalculation on rule publish is NOT done; recalculation is manual only.
 * TODO: BUSINESS DECISION REQUIRED - recalculation re-scores against the assessment's own rule set as it is now;
 * moving an assessment to a newer rule set version is not supported.
 */
class SnapshotAssessmentScore
{
    public function __construct(private ReadinessScorer $scorer, private RecordAuditLog $audit) {}

    public function current(ReadinessAssessment $assessment): ?ReadinessAssessmentSnapshot
    {
        return ReadinessAssessmentSnapshot::query()->where('readiness_assessment_id', $assessment->id)
            ->where('is_current', true)->latest('id')->first();
    }

    /** Initial snapshot; returns the existing current one if present. $evaluation lets callers freeze an already-computed result. */
    public function initial(ReadinessAssessment $assessment, ?User $actor, ?array $evaluation = null): ReadinessAssessmentSnapshot
    {
        return $this->current($assessment)
            ?? $this->store($assessment, $actor, ReadinessAssessmentSnapshot::REASON_INITIAL, null, $evaluation);
    }

    public function recalculate(ReadinessAssessment $assessment, User $actor, ?string $note): ReadinessAssessmentSnapshot
    {
        return $this->store($assessment, $actor, ReadinessAssessmentSnapshot::REASON_RECALCULATION, $note);
    }

    private function store(ReadinessAssessment $assessment, ?User $actor, string $reason, ?string $note, ?array $evaluation = null): ReadinessAssessmentSnapshot
    {
        return DB::transaction(function () use ($assessment, $actor, $reason, $note, $evaluation) {
            $assessment->unsetRelation('results');
            $assessment->load(['ruleSet', 'results.criterion']);
            $evaluation ??= $this->scorer->evaluate($assessment);
            $config = [
                'rule_set' => $assessment->ruleSet->only(['id', 'version_number', 'name_ar', 'status', 'published_at']),
                'criteria' => $assessment->results->map(fn ($r) => $r->criterion->attributesToArray())->values()->all(),
            ];
            $previous = $this->current($assessment);
            $previous?->update(['is_current' => false]);

            $snapshot = ReadinessAssessmentSnapshot::query()->create([
                'readiness_assessment_id' => $assessment->id,
                'enterprise_id' => $assessment->enterprise_id,
                'readiness_rule_set_id' => $assessment->readiness_rule_set_id,
                'rule_set_version' => $assessment->ruleSet->version_number,
                'config_hash' => hash('sha256', json_encode($config)),
                'score' => $evaluation['score'] ?? null,
                'mandatory_conditions_met' => $evaluation['mandatory_conditions_met'] ?? null,
                'band' => is_array($evaluation['band'] ?? null) ? ($evaluation['band']['name'] ?? $evaluation['band']['label'] ?? null) : ($evaluation['band'] ?? null),
                'evaluation' => $evaluation,
                'config' => $config,
                'outcomes' => $assessment->results->map(fn ($r) => ['criterion_id' => $r->readiness_criterion_id, 'code' => $r->criterion->code, 'outcome' => $r->outcome])->values()->all(),
                'reason' => $reason,
                'reason_note' => $note,
                'is_current' => true,
                'computed_by' => $actor?->id,
                'computed_at' => now(),
                'previous_snapshot_id' => $previous?->id,
            ]);

            if ($actor !== null) {
                $this->audit->record($actor, 'readiness.score_snapshot_'.$reason, $assessment, [
                    'snapshot_id' => $snapshot->id, 'score' => $snapshot->score,
                    'previous_snapshot_id' => $previous?->id, 'previous_score' => $previous?->score, 'note' => $note,
                ]);
            }

            return $snapshot;
        });
    }
}
