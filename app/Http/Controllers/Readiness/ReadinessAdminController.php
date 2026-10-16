<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\ComplianceRequirement;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessRuleSet;
use App\Support\ReadinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sections 25-26: versioned readiness rule sets (criterion, weight, mandatory, missing-data treatment, version).
 * TODO: BUSINESS DECISION REQUIRED - Beacon must provide scoring criteria, weights, mandatory criteria,
 * treatment of missing data and score interpretation/bands. No criteria are seeded.
 */
class ReadinessAdminController extends Controller
{
    public function __construct(private RecordAuditLog $audit) {}

    public function index(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('Readiness/Admin', [
            'ruleSets' => ReadinessRuleSet::query()->with('criteria.complianceRequirement:id,code')->orderByDesc('version_number')->get()
                ->map(fn (ReadinessRuleSet $s) => [
                    'id' => $s->id,
                    'version_number' => $s->version_number,
                    'name_ar' => $s->name_ar,
                    'status' => $s->status,
                    'status_label' => ReadinessRuleSet::STATUS_LABELS[$s->status],
                    'change_summary' => $s->change_summary,
                    'published_at' => $s->published_at?->toDateTimeString(),
                    'criteria' => $s->criteria->map(fn (ReadinessCriterion $c) => self::criterionData($c))->all(),
                ]),
            'treatments' => ReadinessCriterion::MISSING_DATA_TREATMENTS,
            'complianceRequirements' => ComplianceRequirement::query()->orderBy('code')->get(['id', 'code']),
        ]);
    }

    /** Creates a draft rule set; optionally copies criteria from the latest published set as a starting point. */
    public function store(Request $request): RedirectResponse
    {
        $actor = $this->admin($request);
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
            'copy_from_current' => ['boolean'],
        ]);
        abort_if(ReadinessRuleSet::query()->where('status', ReadinessRuleSet::STATUS_DRAFT)->exists(), 422, 'توجد مسودة قواعد غير منشورة.');

        DB::transaction(function () use ($data, $actor) {
            $set = ReadinessRuleSet::query()->create([
                'version_number' => ((int) ReadinessRuleSet::query()->max('version_number')) + 1,
                'name_ar' => $data['name_ar'],
                'change_summary' => $data['change_summary'] ?? null,
                'status' => ReadinessRuleSet::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);
            if (($data['copy_from_current'] ?? false) && ($current = ReadinessRuleSet::current())) {
                foreach ($current->criteria as $c) {
                    $set->criteria()->create($c->only(['code', 'label_ar', 'description_ar', 'weight', 'is_mandatory', 'missing_data_treatment', 'compliance_requirement_id']));
                }
            }
            $this->audit->record($actor, 'readiness.rule_set_created', $set, ['version_number' => $set->version_number]);
        });

        return redirect()->route('admin.readiness.index');
    }

    public function storeCriterion(Request $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        $actor = $this->admin($request);
        abort_if($ruleSet->status !== ReadinessRuleSet::STATUS_DRAFT, 422, 'لا يمكن تعديل نسخة قواعد منشورة.');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('readiness_criteria')->where('readiness_rule_set_id', $ruleSet->id)],
            'label_ar' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'weight' => ['required', 'numeric', 'min:0', 'max:1000'],
            'is_mandatory' => ['required', 'boolean'],
            'missing_data_treatment' => ['required', Rule::in(array_keys(ReadinessCriterion::MISSING_DATA_TREATMENTS))],
            'compliance_requirement_id' => ['nullable', 'integer', 'exists:compliance_requirements,id'],
        ]);

        $criterion = $ruleSet->criteria()->create($data);
        $this->audit->record($actor, 'readiness.criterion_added', $criterion, ['rule_set_version' => $ruleSet->version_number] + $data);

        return redirect()->route('admin.readiness.index');
    }

    public function destroyCriterion(Request $request, ReadinessCriterion $criterion): RedirectResponse
    {
        $actor = $this->admin($request);
        abort_if($criterion->ruleSet->status !== ReadinessRuleSet::STATUS_DRAFT, 422, 'لا يمكن تعديل نسخة قواعد منشورة.');
        $this->audit->record($actor, 'readiness.criterion_removed', $criterion->ruleSet, ['criterion' => $criterion->only(['code', 'label_ar', 'weight'])]);
        $criterion->delete();

        return redirect()->route('admin.readiness.index');
    }

    public function publish(Request $request, ReadinessRuleSet $ruleSet): RedirectResponse
    {
        $actor = $this->admin($request);
        abort_if($ruleSet->status !== ReadinessRuleSet::STATUS_DRAFT, 422, 'النسخة منشورة مسبقاً.');
        abort_if($ruleSet->criteria()->count() === 0, 422, 'لا يمكن نشر قواعد بدون معايير.');

        DB::transaction(function () use ($ruleSet, $actor) {
            ReadinessRuleSet::query()->where('status', ReadinessRuleSet::STATUS_PUBLISHED)->get()
                ->each(fn (ReadinessRuleSet $old) => $old->update(['status' => ReadinessRuleSet::STATUS_RETIRED]));
            $ruleSet->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_by' => $actor->id, 'published_at' => now()]);
            $this->audit->record($actor, 'readiness.rule_set_published', $ruleSet, ['version_number' => $ruleSet->version_number, 'change_summary' => $ruleSet->change_summary]);
        });

        return redirect()->route('admin.readiness.index');
    }

    private function admin(Request $request)
    {
        abort_unless(ReadinessAccess::isAdmin($request->user()), 403);

        return $request->user();
    }

    public static function criterionData(ReadinessCriterion $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'label_ar' => $c->label_ar,
            'description_ar' => $c->description_ar,
            'weight' => (float) $c->weight,
            'is_mandatory' => $c->is_mandatory,
            'missing_data_treatment' => $c->missing_data_treatment,
            'missing_data_treatment_label' => ReadinessCriterion::MISSING_DATA_TREATMENTS[$c->missing_data_treatment] ?? $c->missing_data_treatment,
            'compliance_requirement_code' => $c->complianceRequirement?->code,
        ];
    }
}
