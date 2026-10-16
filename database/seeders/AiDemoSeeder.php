<?php

namespace Database\Seeders;

use App\Ai\Models\AiEscalation;
use App\Ai\Models\KnowledgeArticle;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use App\Notifications\ComplianceDeadlineReminder;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo AI data for LOCAL use only (assistant, escalation, knowledge base,
 * notifications). Idempotent; never deletes data.
 *
 *   php artisan db:seed --class=DemoSeeder     # first: demo users + enterprise
 *   php artisan db:seed --class=AiDemoSeeder
 *
 * TODO: BUSINESS DECISION REQUIRED — real knowledge-base content must be written
 * and approved by Beacon. The articles below are clearly marked demo placeholders
 * and state no Beacon rules.
 */
class AiDemoSeeder extends Seeder
{
    public const ARTICLES = [
        ['title' => 'تسجيل المصاريف اليومية (محتوى تجريبي)', 'topic' => 'bookkeeping', 'status' => 'published',
            'body' => 'مقال تجريبي لعرض قاعدة المعرفة: سجّل المصاريف مع الإيصال المرفق، وراجع كل اقتراح قبل حفظه. يُستبدل هذا النص بمحتوى تعتمده بيكون.'],
        ['title' => 'رفع الإيصالات واستخراج البيانات (محتوى تجريبي)', 'topic' => 'platform_use', 'status' => 'published',
            'body' => 'مقال تجريبي: ارفع صورة الإيصال من صفحة المستندات، ثم راجع المبلغ والتاريخ والمورّد المقترحين قبل التأكيد. لا يُحفظ شيء دون تأكيدك.'],
        ['title' => 'متابعة مواعيد الامتثال (محتوى تجريبي)', 'topic' => 'compliance', 'status' => 'published',
            'body' => 'مقال تجريبي: تظهر تذكيرات مواعيد الامتثال في صفحة الإشعارات. المواعيد والمتطلبات الفعلية تحددها بيكون.'],
        ['title' => 'مسودة: التخطيط المالي (محتوى تجريبي)', 'topic' => 'business_planning', 'status' => 'draft',
            'body' => 'مسودة تجريبية غير منشورة، لا يستخدمها المساعد الذكي.'],
    ];

    public const ESCALATION_QUESTION = 'سؤال تجريبي: كيف أصنّف دفعة إيجار المحل؟';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('AiDemoSeeder runs only when APP_ENV=local.');
        }

        $emails = collect(config('demo.accounts', []))->pluck('email', 'role');
        $admin = User::query()->where('email', $emails[UserRole::BeaconAdmin->value] ?? null)->first();
        $sme = User::query()->where('email', $emails[UserRole::SmeUser->value] ?? null)->first();
        if ($admin === null || $sme === null) {
            throw new RuntimeException('Run `php artisan db:seed --class=DemoSeeder` first (demo users are missing).');
        }
        $enterprise = Enterprise::query()->whereHas('members', fn ($q) => $q->whereKey($sme->id))->orderBy('id')->first();

        foreach (self::ARTICLES as $a) {
            $article = KnowledgeArticle::query()->firstOrNew(['title' => $a['title']]);
            if (! $article->exists) {
                $article->fill(['body' => $a['body'], 'topic' => $a['topic']]);
                $article->status = $a['status'];
                $article->published_at = $a['status'] === 'published' ? now() : null;
                $article->created_by = $admin->id;
                $article->save();
            }
        }

        AiEscalation::query()->firstOrCreate(
            ['user_id' => $sme->id, 'question' => self::ESCALATION_QUESTION],
            [
                'enterprise_id' => $enterprise?->id,
                'context' => ['role' => $sme->role->value, 'enterprise_public_id' => $enterprise?->public_id],
                'status' => 'open',
            ],
        );

        if ($enterprise !== null && ! $sme->notifications()->where('type', ComplianceDeadlineReminder::class)->exists()) {
            // notifyNow: written immediately even if a queue is configured (keeps idempotency check valid).
            $sme->notifyNow(new ComplianceDeadlineReminder(
                $enterprise->public_id, $enterprise->business_name, 'متطلب تجريبي', now()->addDays(7)->toDateString(),
            ));
        }
    }
}
