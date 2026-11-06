<?php

namespace App\Ai\Services;

use App\Ai\Contracts\AiProvider;
use App\Ai\Exceptions\AiUnavailableException;
use App\Ai\Models\KnowledgeArticle;
use App\Ai\Support\AiDataGuard;
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
            'context' => ['supported' => $result['supported'], 'source_public_ids' => array_column($result['sources'], 'id')],
        ]);

        return $result;
    }

    /**
     * Keyword retrieval over currently approved articles, with bilingual aliases
     * for common platform-use questions (record/voice/تسجيل/صوت…).
     * Only id/title/body are sent to the provider — no user or enterprise data.
     *
     * @return list<array{id:int,title:string,body:string}>
     */
    public function retrieve(string $question): array
    {
        $terms = $this->searchTerms($question);

        if ($terms === []) {
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
            ->get(['id', 'public_id', 'title', 'body'])
            ->map(fn ($a) => ['id' => $a->id, 'public_id' => $a->public_id, 'title' => $a->title, 'body' => $a->body])
            ->all();
    }

    /**
     * Expand the user question into searchable tokens (Arabic + English aliases).
     *
     * @return list<string>
     */
    public function searchTerms(string $question): array
    {
        $raw = collect(preg_split('/[\s\p{P}]+/u', mb_strtolower($question)) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->unique()
            ->values();

        // Map common English/Arabic product words onto knowledge vocabulary.
        $aliases = [
            'record' => ['تسجيل', 'معاملة', 'صوت', 'voice', 'recording', 'transaction'],
            'recording' => ['تسجيل', 'صوت', 'voice', 'record'],
            'voice' => ['صوت', 'تسجيل', 'ميكروفون', 'microphone', 'audio', 'speech'],
            'audio' => ['صوت', 'تسجيل', 'voice'],
            'features' => ['منصة', 'تطبيق', 'platform', 'سجّل', 'معاملة'],
            'feature' => ['منصة', 'تطبيق', 'platform'],
            'create' => ['إنشاء', 'إضافة', 'تسجيل', 'حفظ'],
            'transaction' => ['معاملة', 'مصروف', 'دخل', 'تسجيل'],
            'receipt' => ['إيصال', 'فاتورة', 'رفع', 'تصوير'],
            'invoice' => ['فاتورة', 'إيصال', 'تصوير'],
            'photo' => ['صورة', 'تصوير', 'فاتورة', 'كاميرا'],
            'microphone' => ['ميكروفون', 'صوت', 'تسجيل', 'voice'],
            'speech' => ['كلام', 'صوت', 'تعرّف', 'voice'],
            'platform' => ['منصة', 'تطبيق', 'features'],
            'how' => ['كيف'],
            'can' => [],
            'using' => [],
            'here' => [],
            'تسجيل' => ['record', 'recording', 'voice', 'معاملة', 'صوت'],
            'صوت' => ['voice', 'audio', 'record', 'تسجيل', 'ميكروفون'],
            'معاملة' => ['transaction', 'record', 'تسجيل'],
            'فاتورة' => ['invoice', 'receipt', 'تصوير'],
            'إيصال' => ['receipt', 'invoice', 'رفع'],
            'كيف' => ['how'],
        ];

        $expanded = $raw->flatMap(function (string $term) use ($aliases) {
            return array_merge([$term], $aliases[$term] ?? []);
        })->filter(fn ($t) => mb_strlen((string) $t) >= 2)->unique()->take(20)->values()->all();

        return array_values(array_map('strval', $expanded));
    }

    private function answer(string $question, array $articles): array
    {
        if (! AiDataGuard::allows(AiDataGuard::ASSISTANT)) {
            return $this->unsupported('المساعد الذكي غير متاح حالياً. يمكنك متابعة عملك بشكل طبيعي أو إرسال سؤالك لفريق بيكون.');
        }

        try {
            $answer = $this->provider->answerFromKnowledge($question, array_map(fn ($a) => ['id' => $a['id'], 'title' => $a['title'], 'body' => $a['body']], $articles));
        } catch (AiUnavailableException|Throwable $e) {
            report($e);

            return $this->unsupported('المساعد الذكي غير متاح حالياً. يمكنك متابعة عملك بشكل طبيعي أو إرسال سؤالك لفريق بيكون.')
                + ['sources' => []];
        }

        if (! $answer->supported) {
            return $this->unsupported('لا تتوفر لدينا إرشادات معتمدة تجيب عن هذا السؤال.');
        }

        $byId = array_column($articles, null, 'id');
        $sources = [];
        foreach ($answer->sourceIds as $id) {
            if (isset($byId[$id])) {
                $sources[] = ['id' => $byId[$id]['public_id'], 'title' => $byId[$id]['title']];
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
