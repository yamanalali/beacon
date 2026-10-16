<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasPublicUlid;

    // Business stages from section 31. Labels may be refined by Beacon.
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SHARED = 'shared';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_INFO_REQUESTED = 'info_requested';
    public const STATUS_DECIDED = 'decided';
    public const STATUS_OUTCOME_RECORDED = 'outcome_recorded';

    public const STATUS_LABELS = [
        'draft' => 'قيد التحضير',
        'shared' => 'تمت المشاركة مع المؤسسة',
        'under_review' => 'قيد المراجعة لدى المؤسسة',
        'info_requested' => 'طُلبت معلومات إضافية',
        'decided' => 'صدر القرار',
        'outcome_recorded' => 'سُجّلت نتيجة التمويل',
    ];

    /** Allowed MFI-side transitions. */
    public const MFI_TRANSITIONS = [
        'shared' => ['under_review', 'info_requested', 'decided'],
        'under_review' => ['info_requested', 'decided'],
        'info_requested' => ['under_review', 'decided'],
    ];

    protected $fillable = [
        'public_id', 'enterprise_id', 'mfi_institution_id', 'readiness_assessment_id', 'status',
        'purpose_ar', 'disclosed_items', 'info_request_note', 'decision_note', 'outcome_note',
        'created_by', 'shared_at',
    ];

    protected function casts(): array
    {
        return ['disclosed_items' => 'array', 'shared_at' => 'datetime'];
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(MfiInstitution::class, 'mfi_institution_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ReadinessAssessment::class, 'readiness_assessment_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ReferralConsent::class)->orderBy('id');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ReferralStatusChange::class)->orderBy('id');
    }

    public function activeConsent(): ?ReferralConsent
    {
        return $this->consents()->where('status', ReferralConsent::STATUS_GRANTED)->latest('id')->first();
    }

    /** Consent must be granted and cover every disclosed item (section 29). */
    public function hasValidConsent(): bool
    {
        $consent = $this->activeConsent();

        return $consent !== null
            && (int) $consent->mfi_institution_id === (int) $this->mfi_institution_id
            && array_diff($this->disclosed_items ?? [], $consent->scope ?? []) === [];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
