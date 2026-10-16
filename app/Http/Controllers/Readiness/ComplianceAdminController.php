<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use App\Support\ReadinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Sections 20, 21, 23: Beacon admins configure versioned compliance requirements without a code release. */
class ComplianceAdminController extends Controller
{
    public function __construct(private RecordAuditLog $audit) {}

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $requirements = ComplianceRequirement::query()->with(['versions.publisher:id,name', 'replacedBy:id,code'])->orderBy('code')->get()
            ->map(fn (ComplianceRequirement $r) => [
                'public_id' => $r->public_id,
                'code' => $r->code,
                'category' => $r->category,
                'category_label' => ComplianceRequirement::CATEGORIES[$r->category] ?? $r->category,
                'is_active' => $r->is_active,
                'replaced_by' => $r->replacedBy?->code,
                'versions' => $r->versions->map(fn (ComplianceRequirementVersion $v) => $this->versionData($v))->all(),
            ]);

        return Inertia::render('Compliance/Admin', [
            'requirements' => $requirements,
            'categories' => ComplianceRequirement::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->authorizeAdmin($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:compliance_requirements,code'],
            'category' => ['required', Rule::in(array_keys(ComplianceRequirement::CATEGORIES))],
            ...$this->versionRules(),
        ]);

        DB::transaction(function () use ($data, $actor) {
            $requirement = ComplianceRequirement::query()->create([
                'code' => $data['code'], 'category' => $data['category'], 'created_by' => $actor->id,
            ]);
            $version = $requirement->versions()->create($this->versionFields($data) + [
                'version_number' => 1, 'status' => ComplianceRequirementVersion::STATUS_DRAFT, 'created_by' => $actor->id,
            ]);
            $this->audit->record($actor, 'compliance.requirement_created', $requirement, ['version_id' => $version->id, 'code' => $requirement->code]);
        });

        return redirect()->route('admin.compliance.index')->with('success', 'تم إنشاء المتطلب كمسودة.');
    }

    /** New draft version (edit / update guidance / effective dates) - never edits a published version. */
    public function storeVersion(Request $request, ComplianceRequirement $requirement): RedirectResponse
    {
        $actor = $this->authorizeAdmin($request);
        $data = $request->validate([...$this->versionRules(), 'change_summary' => ['required', 'string', 'max:2000']]);

        abort_if($requirement->versions()->where('status', ComplianceRequirementVersion::STATUS_DRAFT)->exists(), 422, 'توجد مسودة غير منشورة لهذا المتطلب.');

        $version = $requirement->versions()->create($this->versionFields($data) + [
            'version_number' => $requirement->nextVersionNumber(),
            'status' => ComplianceRequirementVersion::STATUS_DRAFT,
            'created_by' => $actor->id,
        ]);
        $this->audit->record($actor, 'compliance.version_drafted', $version, ['requirement' => $requirement->code, 'version_number' => $version->version_number]);

        return redirect()->route('admin.compliance.index');
    }

    public function updateVersion(Request $request, ComplianceRequirementVersion $version): RedirectResponse
    {
        $actor = $this->authorizeAdmin($request);
        abort_if($version->status !== ComplianceRequirementVersion::STATUS_DRAFT, 422, 'لا يمكن تعديل نسخة منشورة. أنشئ نسخة جديدة.');
        $data = $request->validate($this->versionRules());

        $before = $version->only(array_keys($this->versionFields($data)));
        $version->update($this->versionFields($data));
        $this->audit->record($actor, 'compliance.version_updated', $version, ['before' => $before, 'after' => $this->versionFields($data)]);

        return redirect()->route('admin.compliance.index');
    }

    public function publish(Request $request, ComplianceRequirementVersion $version): RedirectResponse
    {
        $actor = $this->authorizeAdmin($request);
        abort_if($version->status !== ComplianceRequirementVersion::STATUS_DRAFT, 422, 'النسخة منشورة مسبقاً.');

        $version->update([
            'status' => ComplianceRequirementVersion::STATUS_PUBLISHED,
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
        $this->audit->record($actor, 'compliance.version_published', $version, [
            'requirement' => $version->requirement->code,
            'version_number' => $version->version_number,
            'effective_from' => $version->effective_from?->toDateString(),
            'change_summary' => $version->change_summary,
        ]);

        return redirect()->route('admin.compliance.index')->with('success', 'تم نشر النسخة.');
    }

    public function deactivate(Request $request, ComplianceRequirement $requirement): RedirectResponse
    {
        $actor = $this->authorizeAdmin($request);
        $requirement->update(['is_active' => false]);
        $this->audit->record($actor, 'compliance.requirement_deactivated', $requirement, ['code' => $requirement->code]);

        return redirect()->route('admin.compliance.index');
    }

    public function replace(Request $request, ComplianceRequirement $requirement): RedirectResponse
    {
        $actor = $this->authorizeAdmin($request);
        $data = $request->validate([
            'replacement_public_id' => ['required', 'string', Rule::exists('compliance_requirements', 'public_id')->whereNot('id', $requirement->id)],
        ]);
        $replacement = ComplianceRequirement::query()->where('public_id', $data['replacement_public_id'])->firstOrFail();

        $requirement->update(['is_active' => false, 'replaced_by_id' => $replacement->id]);
        $this->audit->record($actor, 'compliance.requirement_replaced', $requirement, ['code' => $requirement->code, 'replaced_by' => $replacement->code]);

        return redirect()->route('admin.compliance.index');
    }

    private function authorizeAdmin(Request $request)
    {
        abort_unless(ReadinessAccess::isAdmin($request->user()), 403);

        return $request->user();
    }

    private function versionRules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'guidance_ar' => ['nullable', 'string', 'max:10000'],
            'requires_document' => ['boolean'],
            'deadline_note_ar' => ['nullable', 'string', 'max:2000'],
            'effective_from' => ['nullable', 'date'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function versionFields(array $data): array
    {
        return [
            'title_ar' => $data['title_ar'],
            'description_ar' => $data['description_ar'] ?? null,
            'guidance_ar' => $data['guidance_ar'] ?? null,
            'requires_document' => (bool) ($data['requires_document'] ?? false),
            'deadline_note_ar' => $data['deadline_note_ar'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'change_summary' => $data['change_summary'] ?? null,
        ];
    }

    public static function versionData(ComplianceRequirementVersion $v): array
    {
        return [
            'id' => $v->id,
            'version_number' => $v->version_number,
            'title_ar' => $v->title_ar,
            'description_ar' => $v->description_ar,
            'guidance_ar' => $v->guidance_ar,
            'guidance_pending' => $v->isGuidancePending(),
            'requires_document' => $v->requires_document,
            'deadline_note_ar' => $v->deadline_note_ar,
            'effective_from' => $v->effective_from?->toDateString(),
            'status' => $v->status,
            'change_summary' => $v->change_summary,
            'published_by' => $v->publisher?->name,
            'published_at' => $v->published_at?->toDateTimeString(),
        ];
    }
}
