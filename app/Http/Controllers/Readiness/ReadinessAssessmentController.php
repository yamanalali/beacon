<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Readiness\SnapshotAssessmentScore;
use App\Http\Controllers\Controller;
use App\Models\ComplianceRequirement;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessAssessmentResult;
use App\Models\ReadinessAssessmentSnapshot;
use App\Models\ReadinessRuleSet;
use App\Support\ReadinessAccess;
use App\Support\ReadinessScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Section 24: compare enterprise records against requirements; identify missing info, unmet conditions,
 * required documents and actions; advisors assign action / responsible person / target date.
 * Section 26: each assessment keeps the rule set version it was created with; finalized assessments are frozen.
 */
class ReadinessAssessmentController extends Controller
{
    public function __construct(private RecordAuditLog $audit, private ReadinessScorer $scorer, private SnapshotAssessmentScore $snapshots) {}

    public function index(Request $request, Enterprise $enterprise): Response
    {
        abort_unless(ReadinessAccess::canViewEnterprise($request->user(), $enterprise), 403);

        return Inertia::render('Readiness/Enterprise', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'assessments' => ReadinessAssessment::query()->where('enterprise_id', $enterprise->id)->with(['ruleSet:id,version_number,name_ar', 'currentSnapshot'])
                ->orderByDesc('id')->get()->map(fn (ReadinessAssessment $a) => [
                    'public_id' => $a->public_id,
                    'status' => $a->status,
                    'score' => $a->currentSnapshot?->score ?? $a->score,
                    'mandatory_conditions_met' => $a->mandatory_conditions_met,
                    'rule_set' => $a->ruleSet->name_ar.' (نسخة '.$a->ruleSet->version_number.')',
                    'created_at' => $a->created_at?->toDateString(),
                    'finalized_at' => $a->finalized_at?->toDateString(),
                ]),
            'canAssess' => ReadinessAccess::canManageEnterprise($request->user(), $enterprise),
            'hasPublishedRules' => ReadinessRuleSet::current() !== null,
        ]);
    }

    public function store(Request $request, Enterprise $enterprise): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $enterprise), 403);
        $ruleSet = ReadinessRuleSet::current();
        if ($ruleSet === null) {
            throw ValidationException::withMessages(['rule_set' => 'لا توجد قواعد جاهزية معتمدة من بيكون بعد.']);
        }

        $assessment = DB::transaction(function () use ($enterprise, $ruleSet, $actor) {
            $assessment = ReadinessAssessment::query()->create([
                'enterprise_id' => $enterprise->id,
                'readiness_rule_set_id' => $ruleSet->id,
                'status' => ReadinessAssessment::STATUS_DRAFT,
                'assessed_by' => $actor->id,
            ]);

            foreach ($ruleSet->criteria as $criterion) {
                [$outcome, $versionId] = $this->prefill($enterprise, $criterion->compliance_requirement_id);
                $assessment->results()->create([
                    'readiness_criterion_id' => $criterion->id,
                    'compliance_requirement_version_id' => $versionId,
                    'outcome' => $outcome,
                ]);
            }

            $this->audit->record($actor, 'readiness.assessment_created', $assessment, [
                'enterprise' => $enterprise->public_id, 'rule_set_version' => $ruleSet->version_number,
            ]);

            return $assessment;
        });

        return redirect()->route('readiness-assessments.show', $assessment);
    }

    public function show(Request $request, ReadinessAssessment $assessment): Response
    {
        $enterprise = $assessment->enterprise;
        abort_unless(ReadinessAccess::canViewEnterprise($request->user(), $enterprise), 403);
        $assessment->load(['ruleSet', 'results.criterion', 'results.complianceVersion', 'results.responsibleUser:id,name']);
        // Finalized: show the frozen current snapshot (legacy finalized rows get one from their stored summary).
        // Drafts are still being edited, so they show a live preview and are not frozen yet.
        $snapshot = null;
        if ($assessment->isFinalized()) {
            $snapshot = $this->snapshots->initial($assessment, null, $assessment->summary);
            $evaluation = $snapshot->evaluation;
        } else {
            $evaluation = $this->scorer->evaluate($assessment);
        }

        $people = $enterprise->members()->get(['users.id', 'users.name'])->merge($enterprise->advisors()->get(['users.id', 'users.name']))
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->unique('id')->values();

        return Inertia::render('Readiness/Assessment', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'assessment' => [
                'public_id' => $assessment->public_id,
                'status' => $assessment->status,
                'rule_set' => ['version_number' => $assessment->ruleSet->version_number, 'name_ar' => $assessment->ruleSet->name_ar],
                'finalized_at' => $assessment->finalized_at?->toDateTimeString(),
            ],
            'evaluation' => $evaluation,
            // Full frozen result for finalized assessments; drafts expose the live result in the same shape.
            'result' => ReadinessAssessmentSnapshot::presentResult($evaluation),
            'snapshot' => $snapshot?->load('computedBy:id,name')->toDisplayWithResult(),
            'snapshotHistory' => $assessment->isFinalized()
                ? $assessment->snapshots()->with('computedBy:id,name')->get()->map->toDisplay()->reverse()->values()
                : [],
            'canRecalculate' => $assessment->isFinalized() && ReadinessAccess::canManageEnterprise($request->user(), $enterprise),
            'results' => $assessment->results->map(fn (ReadinessAssessmentResult $r) => [
                'id' => $r->id,
                'criterion' => ReadinessAdminController::criterionData($r->criterion),
                'compliance_version_number' => $r->complianceVersion?->version_number,
                'outcome' => $r->outcome,
                'outcome_label' => ReadinessAssessmentResult::OUTCOMES[$r->outcome] ?? $r->outcome,
                'note' => $r->note,
                'action_needed' => $r->action_needed,
                'responsible_user_id' => $r->responsible_user_id,
                'responsible_name' => $r->responsibleUser?->name,
                'target_date' => $r->target_date?->toDateString(),
            ]),
            'outcomes' => ReadinessAssessmentResult::OUTCOMES,
            'people' => $people,
            'canEdit' => ! $assessment->isFinalized() && ReadinessAccess::canManageEnterprise($request->user(), $enterprise),
            'disclaimer' => 'نتيجة الجاهزية أداة لدعم العمل الاستشاري وتحضير الإحالة، وليست موافقة على التمويل.',
        ]);
    }

    public function updateResult(Request $request, ReadinessAssessment $assessment, ReadinessAssessmentResult $result): RedirectResponse
    {
        $actor = $request->user();
        $enterprise = $assessment->enterprise;
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $enterprise), 403);
        abort_if((int) $result->readiness_assessment_id !== (int) $assessment->id, 404);
        abort_if($assessment->isFinalized(), 422, 'التقييم نهائي ولا يمكن تعديله.');

        $allowedPeople = $enterprise->members()->pluck('users.id')->merge($enterprise->advisors()->pluck('users.id'))->all();
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(ReadinessAssessmentResult::OUTCOMES))],
            'note' => ['nullable', 'string', 'max:5000'],
            'action_needed' => ['nullable', 'string', 'max:2000'],
            'responsible_user_id' => ['nullable', 'integer', Rule::in($allowedPeople)],
            'target_date' => ['nullable', 'date'],
        ]);

        $before = $result->only(array_keys($data));
        $result->update($data);
        $this->audit->record($actor, 'readiness.result_updated', $result, ['assessment' => $assessment->public_id, 'before' => $before, 'after' => $data]);

        return redirect()->route('readiness-assessments.show', $assessment);
    }

    public function finalize(Request $request, ReadinessAssessment $assessment): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $assessment->enterprise), 403);
        abort_if($assessment->isFinalized(), 422, 'التقييم نهائي مسبقاً.');

        $evaluation = $this->scorer->evaluate($assessment);
        $assessment->update([
            'status' => ReadinessAssessment::STATUS_FINALIZED,
            'score' => $evaluation['score'],
            'mandatory_conditions_met' => $evaluation['mandatory_conditions_met'],
            'summary' => $evaluation,
            'finalized_at' => now(),
        ]);
        $this->snapshots->initial($assessment, $actor, $evaluation);
        $this->audit->record($actor, 'readiness.assessment_finalized', $assessment, ['score' => $evaluation['score'], 'rule_set_version' => $assessment->ruleSet->version_number]);

        return redirect()->route('readiness-assessments.show', $assessment);
    }

    /**
     * Explicit recalculation (QA): re-scores with the current rules, adds a new current snapshot and keeps old ones.
     * The assessment row itself stays frozen; display reads the current snapshot.
     * TODO: BUSINESS DECISION REQUIRED - who may recalculate; default: same as managing the assessment (assigned advisor or Beacon admin).
     */
    public function recalculate(Request $request, ReadinessAssessment $assessment): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $assessment->enterprise), 403);
        abort_unless($assessment->isFinalized(), 422, 'إعادة الحساب متاحة للتقييمات النهائية فقط.');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $this->snapshots->initial($assessment, null, $assessment->summary);
        $this->snapshots->recalculate($assessment, $actor, $data['reason'] ?? null);

        return redirect()->route('readiness-assessments.show', $assessment);
    }

    /** @return array{0:string,1:int|null} */
    private function prefill(Enterprise $enterprise, ?int $requirementId): array
    {
        if ($requirementId === null) {
            return ['missing', null];
        }
        $record = EnterpriseComplianceRecord::query()->where('enterprise_id', $enterprise->id)->where('compliance_requirement_id', $requirementId)->first();
        $versionId = ComplianceRequirement::query()->find($requirementId)?->currentVersion()?->id;

        if ($record === null || $record->compliance_requirement_version_id !== $versionId) {
            return ['missing', $versionId];
        }

        return [$record->isCompleted() ? 'met' : 'not_met', $versionId];
    }
}
