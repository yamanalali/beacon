<?php

namespace App\Http\Controllers;

use App\Actions\Enterprises\AssignAdvisorAction;
use App\Actions\Enterprises\AttachSmeUserAction;
use App\Actions\Enterprises\CreateEnterpriseAction;
use App\Actions\Enterprises\UpdateEnterpriseProfileAction;
use App\Enums\UserRole;
use App\Http\Requests\Enterprises\AssignEnterpriseUserRequest;
use App\Http\Requests\Enterprises\StoreEnterpriseRequest;
use App\Http\Requests\Enterprises\UpdateEnterpriseProfileRequest;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EnterpriseController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user?->role === UserRole::SmeUser) {
            $enterprise = Enterprise::query()
                ->whereHas('members', fn ($query) => $query->whereKey($user->id))
                ->first();

            if ($enterprise !== null) {
                return redirect()->route('enterprises.show', $enterprise);
            }

            return redirect('/');
        }

        $this->authorize('viewAny', Enterprise::class);

        $search = $request->string('search')->trim()->toString();

        $enterprises = Enterprise::query()
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
            ]);

        return Inertia::render('SMEs/Index', [
            'enterprises' => $enterprises,
            'filters' => [
                'search' => $search,
            ],
        ]);
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

    public function show(Request $request, Enterprise $enterprise): Response
    {
        $this->authorize('view', $enterprise);

        $user = $request->user();
        $isStaff = $user->role === UserRole::BeaconAdmin || $user->role === UserRole::BeaconAdvisor;
        $isAdmin = $user->role === UserRole::BeaconAdmin && $user->isActive();

        $payload = [
            'enterprise' => $this->profilePayload($enterprise),
            'incomplete_fields' => $enterprise->incompleteFieldNames(),
            'members' => null,
            'assignments' => null,
            'advisor_options' => [],
            'member_options' => [],
            'can_assign' => $isAdmin,
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

        return Inertia::render('SMEs/Edit', [
            'enterprise' => $this->profilePayload($enterprise),
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

        return redirect()->route('enterprises.show', $enterprise);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(Enterprise $enterprise): array
    {
        return [
            'public_id' => $enterprise->public_id,
            'business_name' => $enterprise->business_name,
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
            'registration_status' => $enterprise->registration_status,
            'initial_digital_readiness_note' => $enterprise->initial_digital_readiness_note,
            'initial_financial_readiness_note' => $enterprise->initial_financial_readiness_note,
            'programme_stage' => $enterprise->programme_stage,
            'progress_summary' => $enterprise->progress_summary,
        ];
    }
}
