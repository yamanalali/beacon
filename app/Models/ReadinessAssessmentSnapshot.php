<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Frozen scorer output. Only `is_current` may flip (true -> false) when a newer recalculation supersedes it. */
class ReadinessAssessmentSnapshot extends Model
{
    public const REASON_INITIAL = 'initial';
    public const REASON_RECALCULATION = 'recalculation';

    protected $fillable = [
        'readiness_assessment_id', 'enterprise_id', 'readiness_rule_set_id', 'rule_set_version', 'config_hash',
        'score', 'mandatory_conditions_met', 'band', 'evaluation', 'config', 'outcomes', 'reason', 'reason_note',
        'is_current', 'computed_by', 'computed_at', 'previous_snapshot_id',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'mandatory_conditions_met' => 'boolean',
            'evaluation' => 'array',
            'config' => 'array',
            'outcomes' => 'array',
            'is_current' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $s): void {
            if (array_diff(array_keys($s->getDirty()), ['is_current', 'updated_at']) !== [] || $s->is_current) {
                throw new LogicException('لقطة النتيجة مجمّدة ولا يمكن تعديلها.');
            }
        });
        static::deleting(fn () => throw new LogicException('لقطة النتيجة مجمّدة ولا يمكن حذفها.'));
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ReadinessAssessment::class, 'readiness_assessment_id');
    }

    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    public function toDisplay(): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'band' => $this->band,
            'mandatory_conditions_met' => $this->mandatory_conditions_met,
            'reason' => $this->reason,
            'reason_note' => $this->reason_note,
            'rule_set_version' => $this->rule_set_version,
            'config_hash' => $this->config_hash,
            'is_current' => $this->is_current,
            'computed_at' => $this->computed_at?->toDateTimeString(),
            'computed_by' => $this->computedBy?->name,
        ];
    }
}
