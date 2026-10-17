<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnterpriseComplianceRecord extends Model
{
    // TODO: BUSINESS DECISION REQUIRED - confirm the list of compliance completion statuses.
    public const STATUSES = [
        'not_started' => 'لم يبدأ',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
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
        return $this->status === 'completed' && ! $this->needsVersionReview();
    }
}
