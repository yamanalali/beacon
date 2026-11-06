<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnterpriseComplianceRecord extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_AWAITING_REVIEW = 'awaiting_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_NEEDS_RESUBMISSION = 'needs_resubmission';

    public const STATUSES = [
        self::STATUS_NOT_STARTED => 'لم يبدأ',
        self::STATUS_IN_PROGRESS => 'قيد التنفيذ',
        self::STATUS_AWAITING_REVIEW => 'بانتظار المراجعة',
        self::STATUS_COMPLETED => 'مكتمل',
        self::STATUS_REJECTED => 'مرفوض',
        self::STATUS_NEEDS_RESUBMISSION => 'مطلوب وثيقة أخرى',
    ];

    /** Statuses where the SME may upload a new document for a document-required item. */
    public const UPLOADABLE_STATUSES = [
        self::STATUS_NOT_STARTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REJECTED,
        self::STATUS_NEEDS_RESUBMISSION,
    ];

    protected $fillable = [
        'enterprise_id', 'compliance_requirement_id', 'compliance_requirement_version_id',
        'status', 'due_date', 'notes', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirementVersion::class, 'compliance_requirement_version_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ComplianceSubmission::class)->orderByDesc('submitted_at')->orderByDesc('id');
    }

    public function latestSubmission(): ?ComplianceSubmission
    {
        return $this->submissions()->first();
    }

    /** Only records reviewed against the currently effective published version. */
    public function scopeForCurrentVersion(Builder $query): void
    {
        $query->where('compliance_requirement_version_id', self::currentVersionQuery());
    }

    public function scopeNeedingVersionReview(Builder $query): void
    {
        $query->whereHas('requirement', fn ($q) => $q->where('is_active', true))
            ->where('compliance_requirement_version_id', '!=', self::currentVersionQuery());
    }

    private static function currentVersionQuery(): Builder
    {
        return ComplianceRequirementVersion::query()->select('id')
            ->whereColumn('compliance_requirement_versions.compliance_requirement_id', 'enterprise_compliance_records.compliance_requirement_id')
            ->where('status', ComplianceRequirementVersion::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now()->toDateString()))
            ->orderByDesc('version_number')->limit(1);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class, 'compliance_requirement_id');
    }

    public function needsVersionReview(): bool
    {
        $current = $this->requirement?->currentVersion();

        return $current !== null && $current->id !== $this->compliance_requirement_version_id;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED && ! $this->needsVersionReview();
    }

    public function allowsDocumentUpload(): bool
    {
        return in_array($this->status, self::UPLOADABLE_STATUSES, true);
    }
}
