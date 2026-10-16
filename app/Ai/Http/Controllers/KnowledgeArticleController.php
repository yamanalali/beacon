<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Models\KnowledgeArticle;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KnowledgeArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage', KnowledgeArticle::class);
        $q = KnowledgeArticle::query()->latest('updated_at');
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json($q->paginate(20));
    }

    public function published(): JsonResponse
    {
        Gate::authorize('readPublished', KnowledgeArticle::class);

        return response()->json(KnowledgeArticle::query()->currentlyApproved()
            ->orderBy('topic')->get(['public_id', 'title', 'body', 'topic', 'version', 'effective_from']));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage', KnowledgeArticle::class);
        $article = new KnowledgeArticle($this->validated($request));
        $article->status = 'draft';
        $article->created_by = $request->user()->id;
        $article->updated_by = $request->user()->id;
        $article->save();
        $this->audit($request, 'ai.knowledge.created', $article);

        return response()->json($article, 201);
    }

    public function update(Request $request, KnowledgeArticle $article): JsonResponse
    {
        Gate::authorize('manage', KnowledgeArticle::class);
        $article->fill($this->validated($request));
        if ($article->isDirty(['title', 'body'])) {
            $article->version++;
            // Edited content must be re-approved before the assistant uses it.
            $article->status = 'draft';
            $article->published_at = null;
        }
        $article->updated_by = $request->user()->id;
        $article->save();
        $this->audit($request, 'ai.knowledge.updated', $article);

        return response()->json($article);
    }

    public function publish(Request $request, KnowledgeArticle $article): JsonResponse
    {
        return $this->transition($request, $article, 'published');
    }

    public function archive(Request $request, KnowledgeArticle $article): JsonResponse
    {
        return $this->transition($request, $article, 'archived');
    }

    private function transition(Request $request, KnowledgeArticle $article, string $status): JsonResponse
    {
        Gate::authorize('manage', KnowledgeArticle::class);
        $article->status = $status;
        $article->published_at = $status === 'published' ? now() : $article->published_at;
        $article->updated_by = $request->user()->id;
        $article->save();
        $this->audit($request, 'ai.knowledge.'.$status, $article);

        return response()->json($article);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'topic' => ['required', Rule::in(KnowledgeArticle::TOPICS)],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
    }

    private function audit(Request $request, string $action, KnowledgeArticle $a): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'target_type' => KnowledgeArticle::class,
            'target_id' => $a->id,
            'context' => ['version' => $a->version, 'status' => $a->status],
        ]);
    }
}
