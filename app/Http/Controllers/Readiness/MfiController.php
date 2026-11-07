<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Readiness\StoreMfiInstitutionRequest;
use App\Http\Requests\Readiness\UpdateMfiInstitutionRequest;
use App\Models\ComplianceRequirement;
use App\Models\Enterprise;
use App\Models\MfiEligibilityCriterion;
use App\Models\MfiInstitution;
use App\Models\User;
use App\Support\MfiMatcher;
use App\Support\ReadinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Sections 27-28: MFI directory/configuration (Beacon admin) and advisory MFI matching (Beacon staff). */
class MfiController extends Controller
{
    public function __construct(private RecordAuditLog $audit) {}

    public function index(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('Mfi/Admin', [
            'institutions' => MfiInstitution::query()->with(['eligibilityCriteria', 'users:id,name,email,mfi_institution_id'])->orderBy('name_ar')->get()
                ->map(fn (MfiInstitution $i) => $this->institutionPayload($i)),
            'criterionTypes' => MfiEligibilityCriterion::TYPES,
            'complianceRequirements' => ComplianceRequirement::query()->orderBy('code')->get(['id', 'code']),
            'mfiUsers' => User::query()->where('role', UserRole::MfiUser->value)->orderBy('name')->get(['id', 'name', 'email']),
            'regionSuggestions' => ['دمشق', 'ريف دمشق', 'درعا'],
        ]);
    }

    public function store(StoreMfiInstitutionRequest $request): RedirectResponse
    {
        $actor = $this->admin($request);
        $data = $this->normalizeInstitutionData($request->validated());
        $institution = MfiInstitution::query()->create($data + ['created_by' => $actor->id, 'is_active' => true]);
        $this->audit->record($actor, 'mfi.institution_created', $institution, $data);

        return redirect()->route('admin.mfis.index');
    }

    public function update(UpdateMfiInstitutionRequest $request, MfiInstitution $institution): RedirectResponse
    {
        $actor = $this->admin($request);
        $data = $this->normalizeInstitutionData($request->validated());
        $before = $institution->only(array_keys($data));
        $institution->update($data);
        $this->audit->record($actor, 'mfi.institution_updated', $institution, ['before' => $before, 'after' => $data]);

        return redirect()->route('admin.mfis.index');
    }

    public function storeCriterion(Request $request, MfiInstitution $institution): RedirectResponse
    {
        $actor = $this->admin($request);
        $data = $request->validate([
            'label_ar' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(MfiEligibilityCriterion::TYPES))],
            'min_score' => ['required_if:type,min_readiness_score', 'nullable', 'numeric', 'min:0', 'max:100'],
            'compliance_requirement_id' => ['required_if:type,compliance_requirement_completed', 'nullable', 'integer', 'exists:compliance_requirements,id'],
        ]);
        $parameters = match ($data['type']) {
            'min_readiness_score' => ['min_score' => (float) $data['min_score']],
            'compliance_requirement_completed' => ['compliance_requirement_id' => (int) $data['compliance_requirement_id']],
            default => null,
        };
        $criterion = $institution->eligibilityCriteria()->create([
            'label_ar' => $data['label_ar'], 'type' => $data['type'], 'parameters' => $parameters, 'created_by' => $actor->id,
        ]);
        $this->audit->record($actor, 'mfi.criterion_added', $criterion, ['institution' => $institution->public_id, 'type' => $data['type'], 'parameters' => $parameters]);

        return redirect()->route('admin.mfis.index');
    }

    public function deactivateCriterion(Request $request, MfiEligibilityCriterion $criterion): RedirectResponse
    {
        $actor = $this->admin($request);
        $criterion->update(['is_active' => false]);
        $this->audit->record($actor, 'mfi.criterion_deactivated', $criterion, ['label' => $criterion->label_ar]);

        return redirect()->route('admin.mfis.index');
    }

    /** Links an MFI_USER account to its institution (scopes which referrals that user can see). */
    public function attachUser(Request $request, MfiInstitution $institution): RedirectResponse
    {
        $actor = $this->admin($request);
        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::MfiUser->value)]]);
        $user = User::query()->findOrFail($data['user_id']);
        $before = $user->mfi_institution_id;
        $user->forceFill(['mfi_institution_id' => $institution->id])->save();
        $this->audit->record($actor, 'mfi.user_linked', $user, ['institution' => $institution->public_id, 'previous_institution_id' => $before]);

        return redirect()->route('admin.mfis.index');
    }

    public function matching(Request $request, Enterprise $enterprise, MfiMatcher $matcher): Response
    {
        abort_unless(ReadinessAccess::canManageEnterprise($request->user(), $enterprise), 403);

        return Inertia::render('Mfi/Matching', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'matches' => MfiInstitution::query()->where('is_active', true)->orderBy('name_ar')->get()
                ->map(function (MfiInstitution $i) use ($matcher, $enterprise) {
                    $results = $matcher->match($enterprise, $i);

                    return [
                        'public_id' => $i->public_id,
                        'id' => $i->id,
                        'name_ar' => $i->name_ar,
                        'name_en' => $i->name_en,
                        'code' => $i->code,
                        'region_ar' => $i->region_ar,
                        'focus_ar' => $i->focus_ar,
                        'loan_size_min' => $i->loan_size_min,
                        'loan_size_max' => $i->loan_size_max,
                        'loan_currency' => $i->loan_currency,
                        'contact_name' => $i->contact_name,
                        'contact_phone' => $i->contact_phone,
                        'results' => $results,
                        'counts' => [
                            'met' => count(array_filter($results, fn ($r) => $r['result'] === 'met')),
                            'not_met' => count(array_filter($results, fn ($r) => $r['result'] === 'not_met')),
                            'needs_review' => count(array_filter($results, fn ($r) => $r['result'] === 'needs_review')),
                        ],
                    ];
                }),
            'disclaimer' => ui('disclaimer.mfi_matching'),
        ]);
    }

    /** @return array<string, mixed> */
    private function institutionPayload(MfiInstitution $i): array
    {
        return [
            'public_id' => $i->public_id,
            'name_ar' => $i->name_ar,
            'code' => $i->code,
            'name_en' => $i->name_en,
            'contact_name' => $i->contact_name,
            'contact_phone' => $i->contact_phone,
            'contact_email' => $i->contact_email,
            'region_ar' => $i->region_ar,
            'address_ar' => $i->address_ar,
            'website' => $i->website,
            'focus_ar' => $i->focus_ar,
            'loan_size_min' => $i->loan_size_min,
            'loan_size_max' => $i->loan_size_max,
            'loan_currency' => $i->loan_currency,
            'notes' => $i->notes,
            'is_active' => $i->is_active,
            'users' => $i->users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]),
            'criteria' => $i->eligibilityCriteria->map(fn (MfiEligibilityCriterion $c) => [
                'id' => $c->id,
                'label_ar' => $c->label_ar,
                'type' => $c->type,
                'type_label' => MfiEligibilityCriterion::TYPES[$c->type] ?? $c->type,
                'parameters' => $c->parameters,
                'is_active' => $c->is_active,
            ]),
        ];
    }

    /** @param  array<string, mixed>  $data
     *  @return array<string, mixed> */
    private function normalizeInstitutionData(array $data): array
    {
        foreach (['code', 'name_en', 'contact_name', 'contact_phone', 'contact_email', 'region_ar', 'address_ar', 'website', 'focus_ar', 'notes', 'loan_currency'] as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $trimmed = trim($data[$key]);
                $data[$key] = $trimmed === '' ? null : $trimmed;
            }
        }
        if (isset($data['loan_currency']) && is_string($data['loan_currency'])) {
            $data['loan_currency'] = strtoupper($data['loan_currency']);
        }
        foreach (['loan_size_min', 'loan_size_max'] as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === '' || $data[$key] === null)) {
                $data[$key] = null;
            }
        }

        return $data;
    }

    private function admin(Request $request)
    {
        abort_unless(ReadinessAccess::isAdmin($request->user()), 403);

        return $request->user();
    }
}
