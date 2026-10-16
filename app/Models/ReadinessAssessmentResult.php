<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReadinessAssessmentResult extends Model
{
    public const OUTCOMES = [
        'met' => 'مستوفى',
        'not_met' => 'غير مستوفى',
        'missing' => 'بيانات ناقصة',
        'needs_review' => 'يحتاج مراجعة',
    ];

    protected $fillable = [
        'readiness_assessment_id', 'readiness_criterion_id', 'compliance_requirement_version_id',
        'outcome', 'value', 'points', 'note', 'action_needed', 'responsible_user_id', 'target_date',
    ];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'value' => 'array', 'points' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::updating(function (ReadinessAssessmentResult $result): void {
            if ($result->assessment()->value('status') === ReadinessAssessment::STATUS_FINALIZED) {
                throw new LogicException('التقييم نهائي ولا يمكن تعديله.');
            }
        });
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ReadinessAssessment::class, 'readiness_assessment_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(ReadinessCriterion::class, 'readiness_criterion_id');
    }

    public function complianceVersion(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirementVersion::class, 'compliance_requirement_version_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
