<?php

namespace App\Http\Controllers;

use App\Actions\Advisory\CompleteFollowUpAction;
use App\Actions\Advisory\CreateFollowUpAction;
use App\Actions\Advisory\RecordAdvisoryActivity;
use App\Enums\UserRole;
use App\Http\Requests\Advisory\StoreAdvisoryActivityRequest;
use App\Http\Requests\Advisory\StoreFollowUpActionRequest;
use App\Models\AdvisoryActivity;
use App\Models\Enterprise;
use App\Models\FollowUpAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdvisoryController extends Controller
{
    public function index(Request $request, Enterprise $enterprise, CompleteFollowUpAction $completeFollowUpAction): Response
    {
        Gate::authorize('view', $enterprise);

        $user = $request->user();

        $activities = AdvisoryActivity::query()
            ->where('enterprise_id', $enterprise->id)
            ->with('responsibleUser:id,name')
            ->orderByDesc('activity_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AdvisoryActivity $activity): array => [
                'id' => $activity->id,
                'activity_date' => $activity->activity_date?->toDateString(),
                'notes' => $activity->notes,
                'target_date' => $activity->target_date?->toDateString(),
                'status' => $activity->status->value,
                'status_label' => $activity->status->label(),
                'responsible_name' => $activity->responsibleUser?->name,
            ]);

        $followUps = FollowUpAction::query()
            ->where('enterprise_id', $enterprise->id)
            ->with(['responsibleUser:id,name', 'enterprise'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (FollowUpAction $followUp): array => [
                'id' => $followUp->id,
                'description' => $followUp->description,
                'target_date' => $followUp->target_date?->toDateString(),
                'status' => $followUp->status->value,
                'status_label' => $followUp->status->label(),
                'responsible_name' => $followUp->responsibleUser?->name,
                'can_complete' => $user !== null && $completeFollowUpAction->canComplete($user, $followUp),
            ]);

        return Inertia::render('Advisory/Index', [
            'enterprise' => [
                'public_id' => $enterprise->public_id,
                'business_name' => $enterprise->business_name,
            ],
            'canRecord' => $user !== null
                && $user->isActive()
                && in_array($user->role, [UserRole::BeaconAdmin, UserRole::BeaconAdvisor], true)
                && $user->can('update', $enterprise),
            'activities' => $activities,
            'followUps' => $followUps,
        ]);
    }

    public function store(
        StoreAdvisoryActivityRequest $request,
        Enterprise $enterprise,
        RecordAdvisoryActivity $recordAdvisoryActivity,
    ): RedirectResponse {
        $recordAdvisoryActivity->execute($request->user(), $enterprise, $request->validated());

        return redirect()->route('enterprises.advisory.index', $enterprise);
    }

    public function storeFollowUp(
        StoreFollowUpActionRequest $request,
        Enterprise $enterprise,
        CreateFollowUpAction $createFollowUpAction,
    ): RedirectResponse {
        $createFollowUpAction->execute($request->user(), $enterprise, $request->validated());

        return redirect()->route('enterprises.advisory.index', $enterprise);
    }

    public function complete(
        Request $request,
        FollowUpAction $followUp,
        CompleteFollowUpAction $completeFollowUpAction,
    ): RedirectResponse {
        $followUp->loadMissing('enterprise');
        $actor = $request->user();
        $isResponsible = $actor !== null
            && $followUp->responsible_user_id !== null
            && (int) $followUp->responsible_user_id === (int) $actor->id;

        if (! $isResponsible) {
            Gate::authorize('view', $followUp->enterprise);
        }

        abort_unless($actor !== null && $completeFollowUpAction->canComplete($actor, $followUp), 403);

        $completeFollowUpAction->execute($actor, $followUp);

        return redirect()->route('enterprises.advisory.index', $followUp->enterprise);
    }
}
