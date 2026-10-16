<?php

namespace App\Ai\Services;

use App\Ai\Contracts\AiProvider;
use App\Ai\Exceptions\AiUnavailableException;
use App\Ai\Models\KnowledgeArticle;
use App\Models\AuditLog;
use App\Models\Enterprise;
use App\Models\User;
use Throwable;

/**
 * Advisory assistant (rules §33–§36). Retrieves only currently approved
 * knowledge; if none supports the question it states the limitation and
 * offers escalation instead of answering. Never takes business actions.
 */
class KnowledgeAssistantService
{
    public const AI_LABEL = 'اقتراح ذكي — يرجى مراجعته قبل الاعتماد';

    public function __construct(private readonly AiProvider $provider) {}

    /**
     * @return array<string, mixed>
     */
    public function ask(User $user, string $question, ?Enterprise $enterprise = null): array
    {
        $articles = $this->retrieve($question);

        $result = match (true) {
            $articles === [] => $this->unsupported('لا تتوفر لدينا إرشادات معتمدة تجيب عن هذا السؤال.'),
            default => $this->answer($question, $articles),
        };

        // Audit without storing the question text (data minimisation).
        AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'ai.assistant.asked',
            'target_type' => $enterprise ? Enterprise::class : User::class,
            'target_id' => $enterprise?->id ?? $user->id,
            'context' => ['supported' => $result['supported'], 'source_ids' => array_column($result['sources'], 'id')],
        ]);

        return $result;
    }

    /**
     * Simple keyword retrieval over currently approved articles.
     * Only id/title/body are sent to the provider — no user or enterprise data.
     *
     * @return list<array{id:int,title:string,body:string}>
     */
    public function retrieve(string $question): array
    {
        $terms = collect(preg_split('/[\s\p{P}]+/u', mb_strtolower($question)) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->unique()->take(10)->values();

        if ($terms->isEmpty()) {
            return [];
        }

        return KnowledgeArticle::query()->currentlyApproved()
            ->where(function ($q) use ($terms) {
                foreach ($terms as $t) {
                    $like = '%'.addcslashes($t, '%_\\').'%';
                    $q->orWhere('title', 'like', $like)->orWhere('body', 'like', $like);
                }
            })
            ->orderByDesc('published_at')
            ->limit((int) config('ai.max_context_articles', 3))
            ->get(['id', 'title', 'body'])
            ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'body' => $a->body])
            ->all();
    }

    private function answer(string $question, array $articles): array
    {
        try {
            $answer = $this->provider->answerFromKnowledge($question, $articles);
        } catch (AiUnavailableException|Throwable $e) {
            report($e);

            return $this->unsupported('المساعد الذكي غير متاح حالياً. يمكنك متابعة عملك بشكل طبيعي أو إرسال سؤالك لفريق بيكون.')
                + ['sources' => []];
        }

        if (! $answer->supported) {
            return $this->unsupported('لا تتوفر لدينا إرشادات معتمدة تجيب عن هذا السؤال.');
        }

        $allowed = array_column($articles, 'title', 'id');
        $sources = [];
        foreach ($answer->sourceIds as $id) {
            if (isset($allowed[$id])) {
                $sources[] = ['id' => $id, 'title' => $allowed[$id]];
            }
        }

        return [
            'supported' => true,
            'ai_generated' => true,
            'label' => self::AI_LABEL,
            'answer' => $answer->text,
            'sources' => $sources,
            'can_escalate' => true,
        ];
    }

    private function unsupported(string $message): array
    {
        return [
            'supported' => false,
            'ai_generated' => false,
            'label' => null,
            'answer' => $message,
            'sources' => [],
            'can_escalate' => true,
        ];
    }
}
