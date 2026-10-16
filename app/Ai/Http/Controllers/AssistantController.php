<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Models\AiEscalation;
use App\Ai\Services\KnowledgeAssistantService;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Enterprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssistantController extends Controller
{
    public function ask(Request $request, KnowledgeAssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:2000'],
            'enterprise' => ['nullable', 'string'],
        ]);
        $enterprise = $this->resolveEnterprise($request, $data['enterprise'] ?? null);

        return response()->json($assistant->ask($request->user(), $data['question'], $enterprise));
    }

    public function escalate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:2000'],
            'enterprise' => ['nullable', 'string'],
        ]);
        $enterprise = $this->resolveEnterprise($request, $data['enterprise'] ?? null);

        $escalation = AiEscalation::query()->create([
            'user_id' => $request->user()->id,
            'enterprise_id' => $enterprise?->id,
            'question' => $data['question'],
            // Minimum permitted context only (rules §35/§36).
            'context' => [
                'role' => $request->user()->role->value,
                'enterprise_public_id' => $enterprise?->public_id,
            ],
            'status' => 'open',
        ]);

        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'action' => 'ai.escalation.created',
            'target_type' => AiEscalation::class,
            'target_id' => $escalation->id,
            'context' => ['enterprise_id' => $enterprise?->id],
        ]);

        return response()->json([
            'id' => $escalation->id,
            'status' => $escalation->status,
            'message' => 'تم إرسال سؤالك إلى فريق بيكون.',
        ], 201);
    }

    public function escalations(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = AiEscalation::query()->latest();

        match ($user->role) {
            UserRole::BeaconAdmin => null,
            UserRole::BeaconAdvisor => $q->where(fn ($w) => $w->where('user_id', $user->id)
                ->orWhereHas('enterprise.advisors', fn ($a) => $a->whereKey($user->id))),
            default => $q->where('user_id', $user->id),
        };

        return response()->json($q->paginate(20)->through(fn (AiEscalation $e) => [
            'id' => $e->id, 'question' => $e->question, 'status' => $e->status, 'created_at' => $e->created_at,
        ]));
    }

    public function showEscalation(AiEscalation $escalation): JsonResponse
    {
        Gate::authorize('view', $escalation);

        return response()->json([
            'id' => $escalation->id, 'question' => $escalation->question,
            'status' => $escalation->status, 'context' => $escalation->context, 'created_at' => $escalation->created_at,
        ]);
    }

    /** Enterprise context is optional but must be one the user may view. */
    private function resolveEnterprise(Request $request, ?string $publicId): ?Enterprise
    {
        if ($publicId === null) {
            return null;
        }
        $enterprise = Enterprise::query()->where('public_id', $publicId)->first();
        abort_if($enterprise === null || ! $request->user()->can('view', $enterprise), 403);

        return $enterprise;
    }
}
