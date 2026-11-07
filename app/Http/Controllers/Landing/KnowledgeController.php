<?php

namespace App\Http\Controllers\Landing;

use App\Ai\Models\KnowledgeArticle;
use App\Ai\Support\PaginatedProps;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class KnowledgeController extends Controller
{
    public function index(Request $request): Response
    {
        $q = KnowledgeArticle::query()
            ->currentlyApproved()
            ->orderBy('topic')
            ->orderByDesc('published_at');

        if ($request->filled('topic') && in_array((string) $request->string('topic'), KnowledgeArticle::TOPICS, true)) {
            $q->where('topic', (string) $request->string('topic'));
        }

        if ($request->filled('search')) {
            $like = '%'.addcslashes((string) $request->string('search'), '%_\\').'%';
            $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like));
        }

        return Inertia::render('Landing/Knowledge', [
            'articles' => PaginatedProps::from(
                $q->paginate(12)->withQueryString()->through(fn (KnowledgeArticle $a) => $this->summary($a))
            ),
            'topics' => KnowledgeArticle::TOPICS,
            'filters' => [
                'topic' => $request->query('topic'),
                'search' => $request->query('search'),
            ],
        ]);
    }

    public function show(string $article): Response
    {
        $model = KnowledgeArticle::query()
            ->where('public_id', $article)
            ->currentlyApproved()
            ->first();

        if ($model === null) {
            throw new NotFoundHttpException;
        }

        $related = KnowledgeArticle::query()
            ->currentlyApproved()
            ->where('topic', $model->topic)
            ->where('public_id', '!=', $model->public_id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (KnowledgeArticle $a) => $this->summary($a))
            ->values()
            ->all();

        return Inertia::render('Landing/KnowledgeShow', [
            'article' => [
                'id' => $model->public_id,
                'title' => $model->title,
                'body' => $model->body,
                'topic' => $model->topic,
                'version' => $model->version,
                'effective_from' => $model->effective_from?->toDateString(),
                'effective_until' => $model->effective_until?->toDateString(),
                'published_at' => $model->published_at ? (string) $model->published_at : null,
            ],
            'related' => $related,
        ]);
    }

    /** @return array{id: string, title: string, excerpt: string, topic: string} */
    private function summary(KnowledgeArticle $a): array
    {
        $body = trim((string) $a->body);
        $excerpt = mb_strlen($body) > 180 ? mb_substr($body, 0, 180).'…' : $body;

        return [
            'id' => $a->public_id,
            'title' => $a->title,
            'excerpt' => $excerpt,
            'topic' => $a->topic,
        ];
    }
}
