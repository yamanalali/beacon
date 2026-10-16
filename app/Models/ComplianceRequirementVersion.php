<?php

namespace App\Models;

use App\Models\Concerns\ImmutableOnceLocked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceRequirementVersion extends Model
{
    use ImmutableOnceLocked;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'compliance_requirement_id', 'version_number', 'title_ar', 'description_ar', 'guidance_ar',
        'requires_document', 'deadline_note_ar', 'effective_from', 'status', 'change_summary',
        'created_by', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_document' => 'boolean',
            'effective_from' => 'date',
            'published_at' => 'datetime',
            'version_number' => 'integer',
        ];
    }

    public function wasLockedBeforeChange(): bool
    {
        return $this->getOriginal('status') === self::STATUS_PUBLISHED;
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class, 'compliance_requirement_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isGuidancePending(): bool
    {
        return $this->guidance_ar === null || trim($this->guidance_ar) === '';
    }
}
