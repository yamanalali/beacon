<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Enterprises\ArchiveEnterpriseAction;
use App\Actions\Enterprises\AssignAdvisorAction;
use App\Actions\Enterprises\AttachSmeUserAction;
use App\Actions\Enterprises\CreateAndAttachSmeUserAction;
use App\Actions\Enterprises\CreateEnterpriseAction;
use App\Actions\Enterprises\DetachAdvisorAction;
use App\Actions\Enterprises\DetachEnterpriseMemberAction;
use App\Actions\Enterprises\RestoreEnterpriseAction;
use App\Actions\Enterprises\UpdateEnterpriseProfileAction;
use App\Enums\EnterpriseProgrammeStage;
use App\Enums\EnterpriseRegistrationStatus;
use App\Enums\UserRole;
use App\Http\Requests\Enterprises\AssignEnterpriseUserRequest;
use App\Http\Requests\Enterprises\StoreEnterpriseMemberRequest;
use App\Http\Requests\Enterprises\StoreEnterpriseRequest;
use App\Http\Requests\Enterprises\UpdateEnterpriseProfileRequest;
use App\Models\Enterprise;
use App\Models\User;
use App\Services\Baseline\BaselineProfilePresenter;
use App\Support\TabularListExporter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnterpriseController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user?->role === UserRole::SmeUser) {
            $enterprise = Enterprise::query()
                ->notArchived()
                ->whereHas('members', fn ($query) => $query->whereKey($user->id))
                ->first();

            if ($enterprise !== null) {
                return redirect()->route('enterprises.show', $enterprise);
            }

            return redirect('/');
        }

        $this->authorize('viewAny', Enterprise::class);

        $search = $request->string('search')->trim()->toString();
        $includeArchived = $request->boolean('include_archived') && $user->role === UserRole::BeaconAdmin;

        $enterprises = Enterprise::query()
            ->when(! $includeArchived, fn ($query) => $query->notArchived())
            ->when($user->role === UserRole::BeaconAdvisor, function ($query) use ($user) {
                $query->whereHas('advisors', fn ($advisors) => $advisors->whereKey($user->id));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('business_name', 'like', '%'.$search.'%');
            })
            ->orderBy('business_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Enterprise $enterprise) => [
                'public_id' => $enterprise->public_id,
                'business_name' => $enterprise->business_name,
                'sector' => $enterprise->sector,
                'location' => $enterprise->location,
                'archived' => $enterprise->isArchived(),
            ]);

        return Inertia::render('SMEs/Index', [
            'enterprises' => $enterprises,
            'filters' => [
                'search' => $search,
                'include_archived' => $includeArchived,
            ],
            'can_export' => $user->can('exportAny', Enterprise::class),
            'can_include_archived' => $user->role === UserRole::BeaconAdmin,
        ]);
    }

    public function export(Request $request, string $format, TabularListExporter $exporter, RecordAuditLog $audit): StreamedResponse
    {
        $this->authorize('exportAny', Enterprise::class);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        $user = $request->user();
        $search = $request->string('search')->trim()->toString();

        $locationLabels = [
            'damascus' => 'دمشق',
            'rural_damascus' => 'ريف دمشق',
            'daraa' => 'درعا',
        ];

        $rows = Enterprise::query()
            ->notArchived()
            ->when($user->role === UserRole::BeaconAdvisor, function ($query) use ($user) {
                $query->whereHas('advisors', fn ($advisors) => $advisors->whereKey($user->id));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('business_name', 'like', '%'.$search.'%');
            })
            ->orderBy('business_name')
            ->limit(10000)
            ->get()
            ->map(fn (Enterprise $enterprise) => [
                $enterprise->public_id,
                $enterprise->business_name,
                $enterprise->sector ?? '',
                $locationLabels[$enterprise->location] ?? ($enterprise->location ?? ''),
            ])
            ->all();

        $headers = ['المعرف', 'اسم المنشأة', 'القطاع', 'الموقع'];

        $audit->record($user, 'enterprises.list.exported', $user, [
            'format' => $format,
            'search' => $search,
            'row_count' => count($rows),
            'scope' => 'enterprise_directory',
        ]);

        $filename = 'enterprises-'.now()->format('Ymd');

        return $format === 'csv'
            ? $exporter->csv($headers, $rows, $filename)
            : $exporter->xlsx($headers, $rows, $filename, 'المنشآت');
    }

    public function create(): Response
    {
        $this->authorize('create', Enterprise::class);

        return Inertia::render('SMEs/Create');
    }

    public function store(StoreEnterpriseRequest $request, CreateEnterpriseAction $action): RedirectResponse
    {
        $enterprise = $action->execute($request->user(), $request->validated());

        return redirect()->route('enterprises.show', $enterprise);
    }

    public function show(Request $request, Enterprise $enterprise, BaselineProfilePresenter $baselinePresenter): Response
    {
        $this->authorize('view', $enterprise);

        $user = $request->user();
        $isStaff = $user->role === UserRole::BeaconAdmin || $user->role === UserRole::BeaconAdvisor;
        $isAdmin = $user->role === UserRole::BeaconAdmin && $user->isActive();

        $enterprise->loadMissing('baselineProfile');

        $payload = [
            'enterprise' => $this->profilePayload($enterprise),
            'incomplete_fields' => $enterprise->incompleteFieldNames(),
            'baseline' => $baselinePresenter->forEnterprise($enterprise),
            'members' => null,
            'assignments' => null,
            'advisor_options' => [],
            'member_options' => [],
            'can_assign' => $isAdmin && ! $enterprise->isArchived(),
            'can_archive' => $user->can('archive', $enterprise),
            'can_restore' => $user->can('restore', $enterprise),
            'can_detach_member' => $user->can('detachMember', $enterprise),
            'can_detach_advisor' => $user->can('detachAdvisor', $enterprise),
        ];

        if ($isStaff) {
            $payload['members'] = $enterprise->members()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ])
                ->all();
        }

        if ($isAdmin) {
            $payload['assignments'] = $enterprise->advisors()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $advisor) => [
                    'id' => $advisor->id,
                    'name' => $advisor->name,
                    'email' => $advisor->email,
                ])
                ->all();

            $payload['advisor_options'] = User::query()
                ->where('role', UserRole::BeaconAdvisor)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $advisor) => [
                    'id' => $advisor->id,
                    'name' => $advisor->name,
                ])
                ->all();

            $payload['member_options'] = User::query()
                ->where('role', UserRole::SmeUser)
                ->where('is_active', true)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('enterprise_members')
                        ->whereColumn('enterprise_members.user_id', 'users.id');
                })
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                ])
                ->all();
        }

        return Inertia::render('SMEs/Show', $payload);
    }

    public function edit(Enterprise $enterprise): Response
    {
        $this->authorize('update', $enterprise);

        $enterprise->loadMissing('baselineProfile');

        $payload = $this->profilePayload($enterprise);
        // Edit form stores stable codes only; unknown legacy free-text is cleared for re-selection.
        $payload['registration_status'] = EnterpriseRegistrationStatus::tryFromMixed($enterprise->registration_status)?->value ?? '';
        $payload['programme_stage'] = EnterpriseProgrammeStage::tryFromMixed($enterprise->programme_stage)?->value ?? '';

        return Inertia::render('SMEs/Edit', [
            'enterprise' => $payload,
            'incomplete_fields' => $enterprise->incompleteFieldNames(),
            'baseline' => [
                'has_profile' => $enterprise->baselineProfile !== null,
                'external_mse_id' => $enterprise->baselineProfile?->external_mse_id,
                'imported_at' => $enterprise->baselineProfile?->imported_at?->toDateTimeString(),
            ],
            'options' => [
                'registration_status' => array_map(
                    fn (EnterpriseRegistrationStatus $status) => [
                        'value' => $status->value,
                        'label' => $status->label(),
                        'hint' => $status->hint(),
                    ],
                    EnterpriseRegistrationStatus::cases(),
                ),
                'programme_stage' => array_map(
                    fn (EnterpriseProgrammeStage $stage) => [
                        'value' => $stage->value,
                        'label' => $stage->label(),
                        'hint' => $stage->hint(),
                    ],
                    EnterpriseProgrammeStage::cases(),
                ),
            ],
        ]);
    }

    public function update(
        UpdateEnterpriseProfileRequest $request,
        Enterprise $enterprise,
        UpdateEnterpriseProfileAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $enterprise, $request->validated());

        return redirect()->route('enterprises.show', $enterprise);
    }

    public function storeAdvisor(
        AssignEnterpriseUserRequest $request,
        Enterprise $enterprise,
        AssignAdvisorAction $action,
    ): RedirectResponse {
        $advisor = User::query()->findOrFail($request->validated('user_id'));
        $action->execute($request->user(), $enterprise, $advisor);

        return redirect()->route('enterprises.show', $enterprise);
    }

    public function storeMember(
        AssignEnterpriseUserRequest $request,
        Enterprise $enterprise,
        AttachSmeUserAction $action,
    ): RedirectResponse {
        $member = User::query()->findOrFail($request->validated('user_id'));
        $action->execute($request->user(), $enterprise, $member);

        return redirect()
            ->route('enterprises.show', $enterprise)
            ->with('success', 'تم ربط المستخدم بالمنشأة.');
    }

    public function storeCreatedMember(
        StoreEnterpriseMemberRequest $request,
        Enterprise $enterprise,
        CreateAndAttachSmeUserAction $action,
    ): RedirectResponse {
        $user = $action->execute($request->user(), $enterprise, $request->validated());

        return redirect()
            ->route('enterprises.show', ['enterprise' => $enterprise, 'tab' => 'team'])
            ->with('success', 'تم إنشاء مستخدم المنشأة وربطه بنجاح.')
            ->with('created_member', [
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    public function archive(Request $request, Enterprise $enterprise, ArchiveEnterpriseAction $action): RedirectResponse
    {
        $this->authorize('archive', $enterprise);
        $action->execute(
            $request->user(),
            $enterprise,
            $request->validate(['archive_reason' => ['nullable', 'string', 'max:255']])['archive_reason'] ?? null,
        );

        return redirect()->route('enterprises.show', $enterprise)->with('success', ui('flash.enterprise_archived'));
    }

    public function restore(Request $request, Enterprise $enterprise, RestoreEnterpriseAction $action): RedirectResponse
    {
        $this->authorize('restore', $enterprise);
        $action->execute($request->user(), $enterprise);

        return redirect()->route('enterprises.show', $enterprise)->with('success', ui('flash.enterprise_restored'));
    }

    public function destroyMember(
        Request $request,
        Enterprise $enterprise,
        User $user,
        DetachEnterpriseMemberAction $action,
    ): RedirectResponse {
        $this->authorize('detachMember', $enterprise);
        $action->execute($request->user(), $enterprise, $user);

        return redirect()
            ->route('enterprises.show', ['enterprise' => $enterprise, 'tab' => 'team'])
            ->with('success', ui('flash.enterprise_member_detached'));
    }

    public function destroyAdvisor(
        Request $request,
        Enterprise $enterprise,
        User $user,
        DetachAdvisorAction $action,
    ): RedirectResponse {
        $this->authorize('detachAdvisor', $enterprise);
        $action->execute($request->user(), $enterprise, $user);

        return redirect()
            ->route('enterprises.show', ['enterprise' => $enterprise, 'tab' => 'team'])
            ->with('success', ui('flash.enterprise_advisor_detached'));
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(Enterprise $enterprise): array
    {
        return [
            'public_id' => $enterprise->public_id,
            'business_name' => $enterprise->business_name,
            'archived' => $enterprise->isArchived(),
            'archived_at' => $enterprise->archived_at?->toDateTimeString(),
            'archive_reason' => $enterprise->archive_reason,
            'sector' => $enterprise->sector,
            'location' => $enterprise->location,
            'founding_date' => $enterprise->founding_date?->toDateString(),
            'employee_count' => $enterprise->employee_count,
            'female_employee_count' => $enterprise->female_employee_count,
            'male_employee_count' => $enterprise->male_employee_count,
            'owner_name' => $enterprise->owner_name,
            'contact_name' => $enterprise->contact_name,
            'contact_phone' => $enterprise->contact_phone,
            'contact_email' => $enterprise->contact_email,
            'address_line' => $enterprise->address_line,
            'registration_status' => $enterprise->registration_status,
            'registration_status_label' => EnterpriseRegistrationStatus::tryFromMixed($enterprise->registration_status)?->label()
                ?? $enterprise->registration_status,
            'initial_digital_readiness_note' => $enterprise->initial_digital_readiness_note,
            'initial_financial_readiness_note' => $enterprise->initial_financial_readiness_note,
            'programme_stage' => $enterprise->programme_stage,
            'programme_stage_label' => EnterpriseProgrammeStage::tryFromMixed($enterprise->programme_stage)?->label()
                ?? $enterprise->programme_stage,
            'progress_summary' => $enterprise->progress_summary,
        ];
    }
}
