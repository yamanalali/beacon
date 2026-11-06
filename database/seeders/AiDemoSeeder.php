<?php

namespace Database\Seeders;

use App\Ai\Models\AiEscalation;
use App\Ai\Models\KnowledgeArticle;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use App\Notifications\ComplianceDeadlineReminder;
use App\Notifications\EscalationOpenedNotification;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo AI data for LOCAL use only (assistant, escalation, knowledge base,
 * notifications). Idempotent; never hard-deletes. Re-running updates article bodies.
 *
 *   php artisan db:seed --class=DemoSeeder     # first: demo users + enterprise
 *   php artisan db:seed --class=AiDemoSeeder
 *
 * TODO: BUSINESS DECISION REQUIRED — Beacon must approve final knowledge wording.
 * Articles describe in-app platform steps only (not legal/compliance advice).
 */
class AiDemoSeeder extends Seeder
{
    /** Older demo titles replaced by the fuller set below. */
    public const SUPERSEDED_TITLES = [
        'تسجيل المصاريف اليومية (محتوى تجريبي)',
        'كيف تسجّل معاملة من المنصة (محتوى تجريبي)',
        'التسجيل الصوتي للمعاملات (محتوى تجريبي)',
        'رفع الإيصالات واستخراج البيانات (محتوى تجريبي)',
        'متابعة مواعيد الامتثال (محتوى تجريبي)',
        'مسودة: التخطيط المالي (محتوى تجريبي)',
    ];

    public const ARTICLES = [
        [
            'title' => 'تسجيل الدخل والمصروف يومياً',
            'topic' => 'bookkeeping',
            'status' => 'published',
            'body' => <<<'TXT'
الغرض: إبقاء سجل مالي واضح لمنشأتك داخل بيكون.

ما الذي تسجّله؟
- الدخل (مبيعات نقدية أو آجلة، خدمات، إيرادات أخرى)
- المصروف (إيجار، مواد، مواصلات، خدمات، رواتب إن وُجدت)
- المشتريات والمبيعات المرتبطة بالذمم عند الحاجة

لكل عملية احفظ على الأقل:
1) التاريخ
2) المبلغ والعملة
3) التصنيف المناسب
4) حالة الدفع (مدفوع / جزئي / آجل) إن طُلبت في النموذج
5) ملاحظة قصيرة تساعدك لاحقاً على التذكّر

إن وُجد إيصال أو فاتورة، ارفقه مع العملية. أي اقتراح ذكي للمبلغ أو التصنيف يبقى للمراجعة — لا يُحفظ نهائياً دون تأكيدك.

نصيحة عملية: سجّل في نفس اليوم قدر الإمكان، حتى لو كان المبلغ صغيراً. السجل المنتظم يسهّل التقارير والجاهزية لاحقاً.

English keywords: expense, income, bookkeeping, transaction, save, category, receipt, daily ledger, cash sale, credit sale.
TXT,
        ],
        [
            'title' => 'الذمم المدينة والدائنة دون ازدواج في الحساب',
            'topic' => 'bookkeeping',
            'status' => 'published',
            'body' => <<<'TXT'
الذمم المدينة: عندما تبيع بالأجل، سجّل البيع كذمة على العميل. عند استلام الدفعة لاحقاً، سجّل التحصيل مرتبطاً بتلك العملية — لا تسجّل «دخلاً جديداً» بنفس المبلغ مرة أخرى.

الذمم الدائنة: عندما تشتري بالأجل، سجّل الشراء كذمة للمورّد. عند الدفع لاحقاً، سجّل السداد مرتبطاً بالشراء — لا تسجّل «مصروفاً جديداً» بنفس المبلغ مرة أخرى.

الدفع الجزئي: أدخل المبلغ المحصّل أو المدفوع فقط، واترك الباقي معلّقاً حتى يُسدَّد.

إن احتجت تصحيحاً بعد الحفظ، استخدم مسار التصحيح المعتمد في المنصة ولا تمحُ السجل القديم بصمت.

English keywords: receivable, payable, credit sale, credit purchase, partial payment, double counting, settlement, collection.
TXT,
        ],
        [
            'title' => 'قراءة التقارير المالية الأساسية',
            'topic' => 'bookkeeping',
            'status' => 'published',
            'body' => <<<'TXT'
من قائمة التقارير يمكنك فتح تقارير مبنية على سجلاتك المحفوظة، مثل:
- قائمة الدخل (الأرباح والخسائر)
- ملخص التدفق النقدي
- تقرير المبيعات
- تقرير المصروفات
- الذمم المدينة
- الذمم الدائنة
- الأداء الشهري

اختر الفترة المناسبة ثم راجع الأرقام. صدّر PDF أو Excel أو CSV عند الحاجة — التصدير يخضع لنفس صلاحياتك على البيانات.

التقارير تعتمد على ما سجّلته أنت؛ السجلات الناقصة تظهر كنتائج أضعف وليس كخطأ في المنصة.

English keywords: report, income statement, profit loss, cash flow, sales, expenses, receivables, payables, export, PDF, Excel, CSV.
TXT,
        ],
        [
            'title' => 'كيف تسجّل معاملة من المنصة',
            'topic' => 'platform_use',
            'status' => 'published',
            'body' => <<<'TXT'
لتسجيل معاملة مالية بسرعة:
1) من الشاشة الرئيسية أو الشريط السفلي افتح «سجّل معاملة» (أو مسار المعاملات ثم إضافة).
2) اختر أحد المسارات:
   - «صوّر فاتورة»: التقط أو ارفع صورة الإيصال، ثم راجع المبلغ والتاريخ والطرف قبل الحفظ.
   - «سجّل صوت»: قل جملة قصيرة مثل «دفعت خمسين ألف ليرة إيجار المحل اليوم»، ثم راجع المسودة.
   - «إدخال يدوي»: املأ النوع والمبلغ والتاريخ والتصنيف بنفسك.
3) صحّح أي حقل مقترح، ثم احفظ. لا يُحفظ شيء دون تأكيدك.

على الهاتف تبدو الشاشات كتطبيق: رأس الصفحة والتنقل السفلي. على الحاسوب تظهر القائمة الجانبية ونماذج أوسع.

English keywords: how, record, recording, voice, audio, microphone, features, create transaction, quick record, save entry, platform use, add expense.
TXT,
        ],
        [
            'title' => 'التسجيل الصوتي لمسودة معاملة',
            'topic' => 'platform_use',
            'status' => 'published',
            'body' => <<<'TXT'
التسجيل الصوتي يحوّل كلامك إلى مسودة معاملة للمراجعة فقط — وليس حفظاً نهائياً.

الخطوات:
1) افتح «سجّل معاملة» ثم «سجّل صوت».
2) اضغط الميكروفون، تكلّم بجملة واضحة تتضمن المبلغ والغرض إن أمكن، ثم أوقف التسجيل.
3) راجع المسودة (المبلغ، التاريخ، الطرف، التصنيف المقترح) ثم احفظ أو ارفض.

ملاحظات:
- التعرّف على الكلام يعمل في المتصفح (مثل Chrome أو Edge) عند توفره.
- الصوت لا يُحفظ بعد تحويله إلى نص في المسار الحالي.
- إن تعذّر التعرّف، استخدم الإدخال اليدوي أو تصوير الفاتورة.

English keywords: voice record, speak, mic, speech to text, draft, review, confirm, audio note, browser speech.
TXT,
        ],
        [
            'title' => 'رفع الإيصالات ومراجعة الاستخراج',
            'topic' => 'platform_use',
            'status' => 'published',
            'body' => <<<'TXT'
ارفع أو صوّر فاتورة/إيصال من «صوّر فاتورة» أو من المستندات المرتبطة بالمنشأة.

عند توفر الاستخراج الذكي، تقترح المنصة حقولاً مثل المبلغ والتاريخ والطرف. تظهر النتيجة كمراجعة قابلة للتعديل:
- أكّد كما هي، أو
- صحّح الحقول، أو
- ارفض الاقتراح وأدخل يدوياً.

لا تُنشأ معاملة نهائية من الاستخراج دون مراجعتك. المستندات الخاصة تبقى محمية ولا تُفتح بروابط عامة.

English keywords: invoice, receipt, photo, camera, OCR, upload, extract, scan, document, review extraction.
TXT,
        ],
        [
            'title' => 'الملف الرقمي للمنشأة والخطوات الناقصة',
            'topic' => 'platform_use',
            'status' => 'published',
            'body' => <<<'TXT'
ملف المنشأة يجمع اسم النشاط، القطاع، الموقع، بيانات التواصل، والعاملين، وحالة التسجيل/الشكلية، وملاحظات الجاهزية الأولية عند توفرها.

لا يلزم إكمال كل الحقول دفعة واحدة. ابدأ بما يلزم لفتح السجل، ثم أكمل الباقي مع مستشار بيكون أو من حسابك.

في لوحة المنشأة تظهر غالباً:
- الخطوة التالية المقترحة
- النواقص في الملف
- آخر النشاطات المالية عند وجودها

English keywords: profile, enterprise, SME file, onboarding, missing fields, business name, sector, location, contact.
TXT,
        ],
        [
            'title' => 'متابعة مواعيد الامتثال داخل بيكون',
            'topic' => 'compliance',
            'status' => 'published',
            'body' => <<<'TXT'
شاشة الامتثال تعرض المتطلبات التي تضبطها بيكون لمنشأتك: مستندات، تراخيص، مواعيد، وإرشادات معتمدة.

ماذا تفعل؟
1) افتح الامتثال من القائمة.
2) راجع الحالة: مكتمل / يحتاج إجراء / متأخر عند ظهورها.
3) ارفع المستند المطلوب إن وُجد زر الرفع، أو أكمِل الإجراء الظاهر.
4) راقب التذكيرات في الإشعارات قبل الموعد.

المحتوى القانوني والمتطلبات الفعلية مصدرها بيكون — المنصة تنظّم المتابعة ولا تستبدل استشارة قانونية خارج النطاق المعتمد.

English keywords: compliance, deadline, reminder, notification, licence, registration, required document, renewal.
TXT,
        ],
        [
            'title' => 'رفع مستند لمتطلب امتثال',
            'topic' => 'compliance',
            'status' => 'published',
            'body' => <<<'TXT'
عندما يطلب متطلب امتثال مستنداً:
1) افتح المتطلب من صفحة الامتثال.
2) ارفع ملفاً مسموحاً (صورة أو PDF حسب ما تسمح به الشاشة).
3) انتظر المراجعة إن ظهرت حالة «بانتظار المراجعة».
4) صحّح أو أعد الرفع إذا طُلب منك ذلك.

لا تشارك المستند خارج المنصة عبر روابط عامة. التحميل والمعاينة يخضعان لصلاحياتك على المنشأة.

English keywords: compliance upload, document submission, PDF, licence file, pending review, resubmit.
TXT,
        ],
        [
            'title' => 'فهم نتيجة الجاهزية المالية',
            'topic' => 'financial_readiness',
            'status' => 'published',
            'body' => <<<'TXT'
تقييم الجاهزية يقارن سجلات منشأتك بمعايير معتمدة من بيكون (ومتطلبات مؤسسات التمويل عند تفعيلها).

النتيجة توضّح:
- ما استُوفي
- ما ينقص (معلومات، مستندات، شروط)
- إجراءات مقترحة مع مسؤول وتاريخ عند تعيينها من المستشار

مهم: درجة الجاهزية أداة إرشادية للتحضير — وليست موافقة تمويل ولا قرار منح.

English keywords: readiness, score, assessment, missing information, financial readiness, eligibility prep, not approval.
TXT,
        ],
        [
            'title' => 'الاستعداد قبل طلب إحالة تمويل',
            'topic' => 'financial_readiness',
            'status' => 'published',
            'body' => <<<'TXT'
قبل إعداد إحالة إلى مؤسسة تمويل:
1) أكمل الملف الأساسي للمنشأة.
2) حدّث المعاملات والمستندات المهمة.
3) راجع الامتثال والمواعيد المتأخرة.
4) راجع نتيجة الجاهزية والإجراءات المفتوحة.
5) ناقش مع مستشار بيكون المؤسسة المناسبة وحزمة المعلومات.

عند مشاركة الإحالة ستُعرض لك الجهة والمعلومات المشمولة، ويُطلب موافقة صريحة قبل الإطلاق. سحب الموافقة لاحقاً يقيّد المشاركات المستقبلية وفق السياسة المعتمدة، ولا يعني استرجاع ما حُمّل سابقاً بشكل مشروع.

English keywords: referral, MFI, financing, consent, share package, readiness before referral, matching.
TXT,
        ],
        [
            'title' => 'خطوات بسيطة لتخطيط التدفق النقدي',
            'topic' => 'business_planning',
            'status' => 'published',
            'body' => <<<'TXT'
تخطيط بسيط يساعد صاحب المنشأة على تجنّب نقص السيولة:

1) اجمع الدخل المتوقع للأسبوع أو الشهر القادم من سجلاتك ومبيعاتك المعتادة.
2) اجمع المصروفات الثابتة (إيجار، خدمات) والمتغيرة (مواد، مواصلات).
3) خصم المصروفات من الدخل — إن ظهر نقص، خطّط لتأجيل مصروف غير ضروري أو تسريع تحصيل ذمة.
4) راجع تقرير التدفق النقدي في المنصة بعد تحديث السجلات.

هذا توجيه تشغيلي عام داخل بيكون؛ قرارات التمويل أو العقود تبقى خارج نطاق المساعد ما لم يوفَّر محتوى معتمد إضافي.

English keywords: business planning, cash flow plan, weekly plan, liquidity, fixed costs, variable costs, forecast.
TXT,
        ],
        [
            'title' => 'أسئلة تساعد على ترتيب أولويات المنشأة',
            'topic' => 'business_planning',
            'status' => 'published',
            'body' => <<<'TXT'
عند جلسة تخطيط قصيرة مع نفسك أو مع مستشار بيكون، اسأل:
- ما أهم مصدر دخل هذا الشهر؟
- أي مصروف يمكن تأجيله دون إيقاف العمل؟
- هل هناك ذمم متأخرة يمكن تحصيلها؟
- ما النقص في الملف أو الامتثال الذي يمنع خطوة تالية؟
- ما الإجراء الواحد الذي يمكن إنجازه هذا الأسبوع داخل المنصة؟

سجّل الإجابات كملاحظات في الملف أو كإجراءات متابعة مع المستشار عند توفر ذلك.

English keywords: priorities, planning questions, weekly focus, advisor session, action list, SME planning.
TXT,
        ],
        [
            'title' => 'مسودة: إرشادات متقدمة للتخطيط المالي',
            'topic' => 'business_planning',
            'status' => 'draft',
            'body' => 'مسودة غير منشورة — لا يستخدمها المساعد حتى تعتمدها بيكون وتنشرها.',
        ],
    ];

    public const ESCALATION_QUESTION = 'سؤال تجريبي: كيف أصنّف دفعة إيجار المحل؟';

    public function run(): void
    {
        if (! app()->environment(['local', 'staging'])) {
            throw new RuntimeException('AiDemoSeeder runs only when APP_ENV is local or staging.');
        }

        $emails = collect(config('demo.accounts', []))->pluck('email', 'role');
        $admin = User::query()->where('email', $emails[UserRole::BeaconAdmin->value] ?? null)->first();
        $sme = User::query()->where('email', $emails[UserRole::SmeUser->value] ?? null)->first();
        $advisor = User::query()->where('email', $emails[UserRole::BeaconAdvisor->value] ?? null)->first();
        if ($admin === null || $sme === null) {
            throw new RuntimeException('Run `php artisan db:seed --class=DemoSeeder` first (demo users are missing).');
        }
        $enterprise = Enterprise::query()->whereHas('members', fn ($q) => $q->whereKey($sme->id))->orderBy('id')->first();

        foreach (self::SUPERSEDED_TITLES as $oldTitle) {
            KnowledgeArticle::query()
                ->where('title', $oldTitle)
                ->where('status', '!=', 'archived')
                ->each(function (KnowledgeArticle $article) use ($admin): void {
                    $article->status = 'archived';
                    $article->published_at = null;
                    $article->updated_by = $admin->id;
                    $article->save();
                });
        }

        foreach (self::ARTICLES as $a) {
            $article = KnowledgeArticle::query()->firstOrNew(['title' => $a['title']]);
            $article->fill(['body' => $a['body'], 'topic' => $a['topic']]);
            $article->status = $a['status'];
            $article->published_at = $a['status'] === 'published' ? ($article->published_at ?? now()) : null;
            if (! $article->exists) {
                $article->created_by = $admin->id;
            }
            $article->updated_by = $admin->id;
            $article->save();
        }

        $escalation = AiEscalation::query()->firstOrCreate(
            ['user_id' => $sme->id, 'question' => self::ESCALATION_QUESTION],
            [
                'enterprise_id' => $enterprise?->id,
                'context' => ['role' => $sme->role->value, 'enterprise_public_id' => $enterprise?->public_id],
                'status' => 'open',
            ],
        );

        if ($enterprise !== null && ! $sme->notifications()->where('type', ComplianceDeadlineReminder::class)->exists()) {
            $sme->notifyNow(new ComplianceDeadlineReminder(
                $enterprise->public_id, $enterprise->business_name, 'متطلب تجريبي', now()->addDays(7)->toDateString(),
            ));
        }

        if ($advisor !== null) {
            AiEscalation::query()
                ->where('status', AiEscalation::STATUS_OPEN)
                ->whereNotNull('enterprise_id')
                ->with('enterprise')
                ->get()
                ->each(function (AiEscalation $open) use ($advisor): void {
                    if (! $open->enterprise?->advisors()->whereKey($advisor->id)->exists()) {
                        return;
                    }
                    $already = $advisor->notifications()
                        ->where('type', EscalationOpenedNotification::class)
                        ->get()
                        ->contains(fn ($n) => ($n->data['escalation_public_id'] ?? null) === $open->public_id);
                    if (! $already) {
                        $advisor->notifyNow(EscalationOpenedNotification::fromEscalation($open));
                    }
                });
        }
    }
}
