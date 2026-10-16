<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Readiness\CriterionRequest;
use App\Http\Requests\Readiness\ImportRuleSetRequest;
use App\Http\Requests\Readiness\PillarRequest;
use App\Http\Requests\Readiness\PreviewRuleSetRequest;
use App\Http\Requests\Readiness\ReorderRequest;
use App\Http\Requests\Readiness\ScoreBandRequest;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessPillar;
use App\Models\ReadinessRuleSet;
use App\Models\ReadinessScoreBand;
use App\Models\User;
use App\Support\ReadinessAccess;
use App\Support\ReadinessRuleSetValidator;
use App\Support\ReadinessScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scoring matrix v2 admin (BEACON_ADMIN): pillars, typed criteria, score bands, reorder, duplicate, export/import, preview.
 * Only draft rule sets can change; published/retired sets answer 422. Preview never writes.
 * TODO: BUSINESS DECISION REQUIRED - the actual pillars, weights, min scores and bands are entered by Beacon.
 */
class ReadinessMatrixController extends Controller
{
    private const LOCKED = 'لا يمكن تعديل نسخة قواعد منشورة.';

    public function __construct(private RecordAuditLog $audit, private ReadinessScorer $scorer) {}

    // ---- Pillars -------------------------------------------------------------------------------------------------

    public function storePillar(PillarRequest $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        $this->assertDraft($ruleSet);
        $data = $request->validated();
        $data['sort_order'] ??= ((int) $ruleSet->pillars()->max('sort_order')) + 1;
        $pillar = $ruleSet->pillars()->create($data);
        $this->audit->record($request->user(), 'readiness.pillar_added', $ruleSet, ['pillar' => $pillar->only(['code', 'label_ar', 'weight', 'min_score'])]);

        return $this->back();
    }

    public function updatePillar(PillarRequest $request, ReadinessPillar $pillar): RedirectResponse
    {
        $this->assertDraft($pillar->ruleSet);
        $before = $pillar->only(['code', 'label_ar', 'weight', 'min_score', 'sort_order']);
        $pillar->update($request->validated());
        $this->audit->record($request->user(), 'readiness.pillar_updated', $pillar->ruleSet, ['before' => $before, 'after' => $pillar->only(array_keys($before))]);

        return $this->back();
    }

    public function destroyPillar(Request $request, ReadinessPillar $pillar): RedirectResponse
    {
        $actor = $this->admin($request);
        $this->assertDraft($pillar->ruleSet);
        $this->audit->record($actor, 'readiness.pillar_removed', $pillar->ruleSet, ['pillar' => $pillar->only(['code', 'label_ar', 'weight'])]);
        $pillar->delete(); // criteria become pillar-less (implicit pillar)

        return $this->back();
    }

    public function reorderPillars(ReorderRequest $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        return $this->reorder($request, $ruleSet, ReadinessPillar::query()->where('readiness_rule_set_id', $ruleSet->id), 'pillars');
    }

    // ---- Criteria (store stays on ReadinessAdminController@storeCriterion) ----------------------------------------

    public function updateCriterion(CriterionRequest $request, ReadinessCriterion $criterion): RedirectResponse
    {
        $this->assertDraft($criterion->ruleSet);
        $data = $request->validated();
        if (array_key_exists('type', $data) && ! array_key_exists('config', $data)) {
            $data['config'] = null;
        }
        $before = $criterion->only(['code', 'label_ar', 'weight', 'is_mandatory', 'missing_data_treatment', 'readiness_pillar_id', 'type', 'config', 'sort_order']);
        $criterion->update($data);
        $this->audit->record($request->user(), 'readiness.criterion_updated', $criterion, ['before' => $before, 'after' => $criterion->only(array_keys($before))]);

        return $this->back();
    }

    public function reorderCriteria(ReorderRequest $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        return $this->reorder($request, $ruleSet, ReadinessCriterion::query()->where('readiness_rule_set_id', $ruleSet->id), 'criteria');
    }

    // ---- Score bands ---------------------------------------------------------------------------------------------

    public function storeBand(ScoreBandRequest $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        $this->assertDraft($ruleSet);
        $data = $request->validated();
        $data['sort_order'] ??= ((int) $ruleSet->bands()->max('sort_order')) + 1;
        $band = $ruleSet->bands()->create($data);
        $this->audit->record($request->user(), 'readiness.band_added', $ruleSet, ['band' => $band->only(['label_ar', 'min', 'max', 'color_token'])]);

        return $this->back();
    }

    public function updateBand(ScoreBandRequest $request, ReadinessScoreBand $band): RedirectResponse
    {
        $this->assertDraft($band->ruleSet);
        $before = $band->only(['label_ar', 'min', 'max', 'color_token', 'sort_order']);
        $band->update($request->validated());
        $this->audit->record($request->user(), 'readiness.band_updated', $band->ruleSet, ['before' => $before, 'after' => $band->only(array_keys($before))]);

        return $this->back();
    }

    public function destroyBand(Request $request, ReadinessScoreBand $band): RedirectResponse
    {
        $actor = $this->admin($request);
        $this->assertDraft($band->ruleSet);
        $this->audit->record($actor, 'readiness.band_removed', $band->ruleSet, ['band' => $band->only(['label_ar', 'min', 'max'])]);
        $band->delete();

        return $this->back();
    }

    public function reorderBands(ReorderRequest $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        return $this->reorder($request, $ruleSet, ReadinessScoreBand::query()->where('readiness_rule_set_id', $ruleSet->id), 'bands');
    }

    // ---- Duplicate / export / import / preview -------------------------------------------------------------------

    public function duplicate(Request $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        $actor = $this->admin($request);
        $definition = ReadinessRuleSetValidator::definition($ruleSet);
        $definition['name_ar'] = $ruleSet->name_ar.' (نسخة)';
        $errors = [];
        $compliance = ReadinessRuleSetValidator::complianceIds($definition, $errors);
        $copy = $this->createDraft($definition, $compliance, $actor, 'readiness.rule_set_duplicated', ['from_version' => $ruleSet->version_number]);

        return $this->back()->with('success', 'تم إنشاء مسودة جديدة (النسخة '.$copy->version_number.').');
    }

    public function export(Request $request, ReadinessRuleSet $ruleSet): JsonResponse
    {
        $this->admin($request);

        return response()->json(ReadinessRuleSetValidator::definition($ruleSet), 200, [
            'Content-Disposition' => 'attachment; filename="readiness-rules-v'.$ruleSet->version_number.'.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function import(ImportRuleSetRequest $request): RedirectResponse
    {
        $definition = $request->definition();
        if ($request->filled('name_ar')) {
            $definition['name_ar'] = $request->input('name_ar');
        }
        if (! is_string($definition['name_ar'] ?? null) || trim($definition['name_ar']) === '') {
            $definition['name_ar'] = 'قواعد مستوردة';
        }
        $errors = [];
        $compliance = ReadinessRuleSetValidator::complianceIds($definition, $errors);
        $set = $this->createDraft($definition, $compliance, $request->user(), 'readiness.rule_set_imported', ['source_version_number' => $definition['source_version_number'] ?? null]);

        return $this->back()->with('success', 'تم استيراد القواعد كمسودة (النسخة '.$set->version_number.').');
    }

    /** Live preview: evaluates sample answers against the rule set. Runs the publish validation first; writes nothing. */
    public function preview(PreviewRuleSetRequest $request, ReadinessRuleSet $ruleSet): JsonResponse
    {
        $errors = ReadinessRuleSetValidator::errors(ReadinessRuleSetValidator::definition($ruleSet));
        if ($errors !== []) {
            return response()->json(['message' => 'القواعد غير مكتملة أو غير صالحة.', 'errors' => $errors], 422);
        }

        $answers = [];
        foreach ($request->validated('answers') as $answer) {
            $answers[(int) $answer['criterion_id']] = ['outcome' => $answer['outcome'] ?? null, 'value' => $answer['value'] ?? null];
        }

        return response()->json(['evaluation' => $this->scorer->evaluateAnswers($ruleSet, $answers)]);
    }

    // ---- helpers -------------------------------------------------------------------------------------------------

    /** @param array<string, int> $compliance code => id */
    private function createDraft(array $definition, array $compliance, User $actor, string $auditAction, array $auditData): ReadinessRuleSet
    {
        abort_if(ReadinessRuleSet::query()->where('status', ReadinessRuleSet::STATUS_DRAFT)->exists(), 422, 'توجد مسودة قواعد غير منشورة.');

        return DB::transaction(function () use ($definition, $compliance, $actor, $auditAction, $auditData) {
            $set = ReadinessRuleSet::query()->create([
                'version_number' => ((int) ReadinessRuleSet::query()->max('version_number')) + 1,
                'name_ar' => mb_substr((string) $definition['name_ar'], 0, 255),
                'change_summary' => $definition['change_summary'] ?? null,
                'status' => ReadinessRuleSet::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);

            // New ids everywhere: pillars are re-created and criteria re-linked by pillar code.
            $pillarIds = [];
            foreach ($definition['pillars'] ?? [] as $i => $p) {
                $pillarIds[$p['code']] = $set->pillars()->create([
                    'code' => $p['code'], 'label_ar' => $p['label_ar'], 'weight' => $p['weight'],
                    'min_score' => $p['min_score'] ?? null, 'sort_order' => $p['sort_order'] ?? $i,
                ])->id;
            }
            foreach ($definition['criteria'] ?? [] as $i => $c) {
                $set->criteria()->create([
                    'code' => $c['code'], 'label_ar' => $c['label_ar'], 'description_ar' => $c['description_ar'] ?? null,
                    'weight' => $c['weight'], 'is_mandatory' => (bool) ($c['is_mandatory'] ?? false),
                    'missing_data_treatment' => $c['missing_data_treatment'],
                    'type' => $c['type'] ?? ReadinessCriterion::TYPE_BOOLEAN, 'config' => $c['config'] ?? null,
                    'readiness_pillar_id' => isset($c['pillar_code']) ? $pillarIds[$c['pillar_code']] : null,
                    'compliance_requirement_id' => isset($c['compliance_requirement_code']) ? ($compliance[$c['compliance_requirement_code']] ?? null) : null,
                    'sort_order' => $c['sort_order'] ?? $i,
                ]);
            }
            foreach ($definition['bands'] ?? [] as $i => $b) {
                $set->bands()->create([
                    'label_ar' => $b['label_ar'], 'min' => $b['min'], 'max' => $b['max'],
                    'color_token' => $b['color_token'], 'sort_order' => $b['sort_order'] ?? $i,
                ]);
            }
            $this->audit->record($actor, $auditAction, $set, ['version_number' => $set->version_number] + $auditData);

            return $set;
        });
    }

    private function reorder(ReorderRequest $request, ReadinessRuleSet $ruleSet, $query, string $what): RedirectResponse
    {
        $this->assertDraft($ruleSet);
        $ids = array_map('intval', $request->validated('ids'));
        $own = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($own);
        $given = $ids;
        sort($given);
        if ($own !== $given) {
            // QA: ids from another rule set (or a partial list) are rejected.
            throw ValidationException::withMessages(['ids' => 'قائمة الترتيب يجب أن تحتوي كل عناصر هذه القواعد فقط.']);
        }
        DB::transaction(function () use ($query, $ids) {
            foreach ($ids as $position => $id) {
                (clone $query)->whereKey($id)->first()->update(['sort_order' => $position + 1]);
            }
        });
        $this->audit->record($request->user(), 'readiness.'.$what.'_reordered', $ruleSet, ['ids' => $ids]);

        return $this->back();
    }

    private function assertDraft(?ReadinessRuleSet $ruleSet): void
    {
        abort_if($ruleSet === null || $ruleSet->status !== ReadinessRuleSet::STATUS_DRAFT, 422, self::LOCKED);
    }

    private function admin(Request $request): User
    {
        abort_unless(ReadinessAccess::isAdmin($request->user()), 403);

        return $request->user();
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('admin.readiness.index');
    }
}
