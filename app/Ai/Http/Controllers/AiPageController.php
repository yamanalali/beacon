<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Models\AiEscalation;
use App\Ai\Models\KnowledgeArticle;
use App\Ai\Support\AiDataGuard;
use App\Ai\Support\PaginatedProps;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Inertia pages for the assistant, escalations and knowledge base (§33–§37). Public ids only. */
class AiPageController extends Controller
{
    public function assistant(Request $request): Response
    {
        abort_unless($request->user()->isActive(), 403);

        return Inertia::render('Ai/Assistant', [
            'ai_available' => AiDataGuard::available(AiDataGuard::ASSISTANT),
            'can_escalate' => true,
            'question_max_length' => 2000,
        ]);
    }

    public function escalations(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isActive(), 403);
        $q = AiEscalation::query()->latest();
        match ($user->role) {
            UserRole::BeaconAdmin => null,
            UserRole::BeaconAdvisor => $q->where(fn ($w) => $w->where('user_id', $user->id)
                ->orWhereHas('enterprise.advisors', fn ($a) => $a->whereKey($user->id))),
            default => $q->where('user_id', $user->id),
        };
        if ($request->filled('status')) {
            $q->where('status', (string) $request->string('status'));
        }

        return Inertia::render('Ai/Escalations/Index', [
            'escalations' => PaginatedProps::from($q->paginate(20)->withQueryString()->through(fn (AiEscalation $e) => $this->escalationProps($e))),
            'filters' => ['status' => $request->query('status')],
            'can_escalate' => true,
        ]);
    }

    private function escalationProps(AiEscalation $escalation, bool $full = false): array
    {
        return [
            'id' => $escalation->public_id,
            'question' => $escalation->question,
            'status' => $escalation->status,
            'created_at' => $escalation->created_at?->toIso8601String(),
        ] + ($full ? ['enterprise_id' => $escalation->context['enterprise_public_id'] ?? null] : []);
    }

    public function showEscalation(AiEscalation $escalation): Response
    {
        Gate::authorize('view', $escalation);

        return Inertia::render('Ai/Escalations/Show', ['escalation' => $this->escalationProps($escalation, true)]);
    }

    public function published(Request $request): Response
    {
        Gate::authorize('readPublished', KnowledgeArticle::class);
        $q = KnowledgeArticle::query()->currentlyApproved()->orderBy('topic')->orderByDesc('published_at');
        if ($request->filled('topic')) {
            $q->where('topic', (string) $request->string('topic'));
        }
        if ($request->filled('search')) {
            $like = '%'.addcslashes((string) $request->string('search'), '%_\\').'%';
            $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like));
        }

        return Inertia::render('Ai/Knowledge/Published', [
            'articles' => PaginatedProps::from($q->paginate(20)->withQueryString()->through(fn (KnowledgeArticle $a) => $this->article($a))),
            'topics' => KnowledgeArticle::TOPICS,
            'filters' => ['topic' => $request->query('topic'), 'search' => $request->query('search')],
            'can_manage' => $request->user()->can('manage', KnowledgeArticle::class),
            'ai_available' => AiDataGuard::available(AiDataGuard::ASSISTANT),
        ]);
    }

    public function manage(Request $request): Response
    {
        Gate::authorize('manage', KnowledgeArticle::class);
        $q = KnowledgeArticle::query()->latest('updated_at');
        if ($request->filled('status')) {
            $q->where('status', (string) $request->string('status'));
        }
        if ($request->filled('topic')) {
            $q->where('topic', (string) $request->string('topic'));
        }

        return Inertia::render('Ai/Knowledge/Index', [
            'articles' => PaginatedProps::from($q->paginate(20)->withQueryString()->through(fn (KnowledgeArticle $a) => $this->article($a, true))),
            'topics' => KnowledgeArticle::TOPICS,
            'statuses' => KnowledgeArticle::STATUSES,
            'filters' => ['status' => $request->query('status'), 'topic' => $request->query('topic')],
            'can_manage' => true,
        ]);
    }

    private function article(KnowledgeArticle $a, bool $admin = false): array
    {
        return [
            'id' => $a->public_id, 'title' => $a->title, 'body' => $a->body, 'topic' => $a->topic,
            'version' => $a->version,
            'effective_from' => $a->effective_from?->toDateString(),
            'effective_until' => $a->effective_until?->toDateString(),
        ] + ($admin ? ['status' => $a->status, 'published_at' => $a->published_at ? (string) $a->published_at : null, 'updated_at' => $a->updated_at?->toIso8601String()] : []);
    }
}
