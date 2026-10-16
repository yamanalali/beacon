<?php

namespace App\Http\Controllers\Readiness;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use App\Models\MfiInstitution;
use App\Models\ReadinessAssessment;
use App\Models\Referral;
use App\Models\ReferralConsent;
use App\Navigation\PageAccess;
use App\Support\ReadinessAccess;
use App\Support\ReferralPackageBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sections 29-31: referral preparation, explicit consent, sharing only with valid consent, workflow stages.
 * TODO: BUSINESS DECISION REQUIRED - who records the final financing outcome (currently the MFI user of the institution or Beacon staff).
 */
class ReferralController extends Controller
{
    public function __construct(private RecordAuditLog $audit) {}

    public function index(Request $request, Enterprise $enterprise): Response
    {
        $user = $request->user();
        abort_unless(ReadinessAccess::canViewEnterprise($user, $enterprise), 403);
        $canManage = ReadinessAccess::canManageEnterprise($user, $enterprise);

        return Inertia::render('Referrals/Enterprise', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'referrals' => Referral::query()->where('enterprise_id', $enterprise->id)->with('institution:id,name_ar')->orderByDesc('id')->get()
                ->map(fn (Referral $r) => $this->summary($r)),
            'canCreate' => $canManage,
            'institutions' => $canManage ? MfiInstitution::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']) : [],
            'assessments' => $canManage ? ReadinessAssessment::query()->where('enterprise_id', $enterprise->id)
                ->where('status', ReadinessAssessment::STATUS_FINALIZED)->with('currentSnapshot')->orderByDesc('id')->get(['id', 'public_id', 'score', 'finalized_at'])
                ->map(fn (ReadinessAssessment $a) => ['id' => $a->id, 'public_id' => $a->public_id, 'score' => $a->frozenScore(), 'finalized_at' => $a->finalized_at]) : [],
            'disclosableItems' => config('readiness.disclosable_items'),
        ]);
    }

    public function store(Request $request, Enterprise $enterprise): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $enterprise), 403);
        $data = $request->validate([
            'mfi_institution_id' => ['required', 'integer', Rule::exists('mfi_institutions', 'id')->where('is_active', true)],
            'purpose_ar' => ['required', 'string', 'max:2000'],
            'disclosed_items' => ['required', 'array', 'min:1'],
            'disclosed_items.*' => ['string', Rule::in(array_keys(config('readiness.disclosable_items')))],
            'readiness_assessment_id' => ['nullable', 'integer', Rule::exists('readiness_assessments', 'id')
                ->where('enterprise_id', $enterprise->id)->where('status', ReadinessAssessment::STATUS_FINALIZED)],
        ]);
        if (in_array('readiness_assessment', $data['disclosed_items'], true) && empty($data['readiness_assessment_id'])) {
            throw ValidationException::withMessages(['readiness_assessment_id' => 'اختر تقييم جاهزية نهائياً لمشاركته.']);
        }

        $referral = DB::transaction(function () use ($data, $enterprise, $actor) {
            $referral = Referral::query()->create([
                'enterprise_id' => $enterprise->id,
                'mfi_institution_id' => $data['mfi_institution_id'],
                'readiness_assessment_id' => $data['readiness_assessment_id'] ?? null,
                'purpose_ar' => $data['purpose_ar'],
                'disclosed_items' => array_values(array_unique($data['disclosed_items'])),
                'status' => Referral::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);
            $this->logStatus($referral, null, Referral::STATUS_DRAFT, $actor->id, null);
            $this->audit->record($actor, 'referral.created', $referral, ['enterprise' => $enterprise->public_id, 'institution_id' => $referral->mfi_institution_id, 'disclosed_items' => $referral->disclosed_items]);

            return $referral;
        });

        return redirect()->route('referrals.show', $referral);
    }

    public function show(Request $request, Referral $referral, ReferralPackageBuilder $builder): Response
    {
        $user = $request->user();
        abort_unless(ReadinessAccess::canViewReferral($user, $referral), 403);
        $referral->load(['enterprise', 'institution', 'consents', 'statusChanges.actor:id,name']);
        $isMfi = $user->role === UserRole::MfiUser;
        $isMember = ReadinessAccess::isEnterpriseMember($user, $referral->enterprise);
        $canManage = ReadinessAccess::canManageEnterprise($user, $referral->enterprise);
        $active = $referral->activeConsent();

        if ($isMfi) {
            $this->audit->record($user, 'referral.package_viewed', $referral, ['institution_id' => $user->mfi_institution_id]);
        }

        return Inertia::render('Referrals/Show', [
            'referral' => $this->summary($referral) + [
                'purpose_ar' => $referral->purpose_ar,
                'disclosed_items' => collect($referral->disclosed_items)->map(fn ($k) => ['key' => $k, 'label' => config("readiness.disclosable_items.$k", $k)])->values(),
                'info_request_note' => $referral->info_request_note,
                'decision_note' => $referral->decision_note,
                'outcome_note' => $referral->outcome_note,
            ],
            'package' => ($isMfi || $canManage || $isMember) ? $builder->build($referral) : [],
            'consent' => [
                'active' => $active ? [
                    'version' => $active->consent_version, 'granted_at' => $active->granted_at->toDateTimeString(),
                ] : null,
                'history' => $isMfi ? [] : $referral->consents->map(fn (ReferralConsent $c) => [
                    'status' => $c->status, 'version' => $c->consent_version,
                    'granted_at' => $c->granted_at?->toDateTimeString(), 'withdrawn_at' => $c->withdrawn_at?->toDateTimeString(),
                ]),
                'text_ar' => config('readiness.consent.text_ar'),
                'version' => config('readiness.consent.version'),
                'withdrawal_notice' => 'سحب الموافقة يمنع أي وصول أو مشاركة مستقبلية عبر المنصة، لكنه لا يلغي المعلومات التي سبق أن اطّلعت عليها أو نزّلتها المؤسسة المخوّلة بشكل مشروع.',
            ],
            'history' => $isMfi ? [] : $referral->statusChanges->map(fn ($c) => [
                'from' => $c->from_status ? Referral::STATUS_LABELS[$c->from_status] : null,
                'to' => Referral::STATUS_LABELS[$c->to_status], 'note' => $c->note, 'actor' => $c->actor?->name,
                'at' => $c->created_at?->toDateTimeString(),
            ]),
            'can' => [
                'consent' => $isMember && $active === null && $referral->status === Referral::STATUS_DRAFT,
                'withdraw' => $isMember && $active !== null,
                'share' => $canManage && $referral->status === Referral::STATUS_DRAFT && $referral->hasValidConsent(),
                'mfi_transitions' => $isMfi ? (Referral::MFI_TRANSITIONS[$referral->status] ?? []) : [],
                'record_outcome' => ($isMfi || $canManage) && $referral->status === Referral::STATUS_DECIDED,
            ],
            'statusLabels' => Referral::STATUS_LABELS,
        ]);
    }

    /** Section 29/30: SME sees institution + disclosed items, then explicitly consents. */
    public function consent(Request $request, Referral $referral): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::isEnterpriseMember($actor, $referral->enterprise), 403);
        abort_if($referral->status !== Referral::STATUS_DRAFT || $referral->activeConsent() !== null, 422, 'لا يمكن تسجيل موافقة جديدة على هذه الإحالة.');
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'يجب تأكيد الموافقة صراحةً.']);

        $consent = $referral->consents()->create([
            'enterprise_id' => $referral->enterprise_id,
            'mfi_institution_id' => $referral->mfi_institution_id,
            'purpose_ar' => $referral->purpose_ar,
            'consent_version' => config('readiness.consent.version'),
            'consent_text_ar' => config('readiness.consent.text_ar'),
            'scope' => $referral->disclosed_items,
            'status' => ReferralConsent::STATUS_GRANTED,
            'granted_by' => $actor->id,
            'granted_at' => now(),
        ]);
        $this->audit->record($actor, 'consent.granted', $consent, ['referral' => $referral->public_id, 'institution_id' => $referral->mfi_institution_id, 'scope' => $consent->scope, 'version' => $consent->consent_version]);

        return redirect()->route('referrals.show', $referral);
    }

    public function withdrawConsent(Request $request, Referral $referral): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::isEnterpriseMember($actor, $referral->enterprise), 403);
        $consent = $referral->activeConsent();
        abort_if($consent === null, 422, 'لا توجد موافقة فعّالة.');

        $consent->update(['status' => ReferralConsent::STATUS_WITHDRAWN, 'withdrawn_by' => $actor->id, 'withdrawn_at' => now()]);
        $this->audit->record($actor, 'consent.withdrawn', $consent, ['referral' => $referral->public_id, 'institution_id' => $referral->mfi_institution_id]);

        return redirect()->route('referrals.show', $referral);
    }

    public function share(Request $request, Referral $referral): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::canManageEnterprise($actor, $referral->enterprise), 403);
        abort_if($referral->status !== Referral::STATUS_DRAFT, 422, 'الإحالة ليست في مرحلة التحضير.');
        if (! $referral->hasValidConsent()) {
            throw ValidationException::withMessages(['consent' => 'لا يمكن مشاركة الإحالة قبل موافقة المنشأة الصريحة على المعلومات المشاركة.']);
        }

        $this->transition($referral, Referral::STATUS_SHARED, $actor->id, null, ['shared_at' => now()]);
        $this->audit->record($actor, 'referral.shared', $referral, ['institution_id' => $referral->mfi_institution_id]);

        return redirect()->route('referrals.show', $referral);
    }

    /** MFI-side workflow: review, request info, decision. */
    public function updateStatus(Request $request, Referral $referral): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::mfiCanViewReferral($actor, $referral), 403);
        $allowed = Referral::MFI_TRANSITIONS[$referral->status] ?? [];
        $data = $request->validate([
            'status' => ['required', Rule::in($allowed)],
            'note' => ['required_if:status,info_requested,decided', 'nullable', 'string', 'max:5000'],
        ]);

        $extra = match ($data['status']) {
            Referral::STATUS_INFO_REQUESTED => ['info_request_note' => $data['note']],
            Referral::STATUS_DECIDED => ['decision_note' => $data['note']],
            default => [],
        };
        $from = $referral->status;
        $this->transition($referral, $data['status'], $actor->id, $data['note'] ?? null, $extra);
        $this->audit->record($actor, 'referral.status_changed', $referral, ['from' => $from, 'to' => $data['status']]);

        return redirect()->route('referrals.show', $referral);
    }

    public function recordOutcome(Request $request, Referral $referral): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(ReadinessAccess::mfiCanViewReferral($actor, $referral) || ReadinessAccess::canManageEnterprise($actor, $referral->enterprise), 403);
        abort_if($referral->status !== Referral::STATUS_DECIDED, 422, 'يجب صدور القرار قبل تسجيل النتيجة.');
        // TODO: BUSINESS DECISION REQUIRED - structured financing outcome fields (amount, product, etc.) are not defined; free-text note only.
        $data = $request->validate(['outcome_note' => ['required', 'string', 'max:5000']]);

        $this->transition($referral, Referral::STATUS_OUTCOME_RECORDED, $actor->id, $data['outcome_note'], ['outcome_note' => $data['outcome_note']]);
        $this->audit->record($actor, 'referral.outcome_recorded', $referral, ['note' => $data['outcome_note']]);

        return redirect()->route('referrals.show', $referral);
    }

    public function mfiInbox(Request $request): Response
    {
        $user = $request->user();
        abort_unless(Gate::forUser($user)->allows(PageAccess::MFI_REFERRAL_INBOX), 403);

        $referrals = Referral::query()->where('mfi_institution_id', $user->mfi_institution_id)
            ->where('status', '!=', Referral::STATUS_DRAFT)->with(['institution:id,name_ar', 'enterprise:id,business_name'])->orderByDesc('shared_at')->get()
            ->filter(fn (Referral $r) => ReadinessAccess::mfiCanViewReferral($user, $r))
            ->map(fn (Referral $r) => $this->summary($r) + ['enterprise_name' => in_array('enterprise_profile', $r->disclosed_items ?? [], true) ? $r->enterprise->business_name : null])
            ->values();

        return Inertia::render('Referrals/MfiInbox', ['referrals' => $referrals]);
    }

    private function transition(Referral $referral, string $to, int $actorId, ?string $note, array $extra = []): void
    {
        DB::transaction(function () use ($referral, $to, $actorId, $note, $extra) {
            $from = $referral->status;
            $referral->update(['status' => $to] + $extra);
            $this->logStatus($referral, $from, $to, $actorId, $note);
        });
    }

    private function logStatus(Referral $referral, ?string $from, string $to, int $actorId, ?string $note): void
    {
        $referral->statusChanges()->create(['from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'note' => $note]);
    }

    private function summary(Referral $r): array
    {
        return [
            'public_id' => $r->public_id,
            'institution' => $r->institution?->name_ar,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'shared_at' => $r->shared_at?->toDateTimeString(),
            'created_at' => $r->created_at?->toDateString(),
        ];
    }
}
