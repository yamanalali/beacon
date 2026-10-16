<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReadinessCriterion extends Model
{
    protected $table = 'readiness_criteria';

    /**
     * Options Beacon can choose from per criterion (section 25 "treatment of missing data").
     * Which option applies to which criterion is a Beacon decision, not a code default.
     */
    public const MISSING_DATA_TREATMENTS = [
        'counts_as_unmet' => 'تُعامل كغير مستوفاة',
        'excluded' => 'تُستبعد من الحساب',
        'needs_review' => 'تحتاج مراجعة',
    ];

    protected $fillable = [
        'readiness_rule_set_id', 'code', 'label_ar', 'description_ar', 'weight', 'is_mandatory',
        'missing_data_treatment', 'compliance_requirement_id',
    ];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'is_mandatory' => 'boolean'];
    }

    protected static function booted(): void
    {
        $guard = function (ReadinessCriterion $criterion): void {
            $set = ReadinessRuleSet::query()->find($criterion->readiness_rule_set_id);
            if ($set !== null && $set->status !== ReadinessRuleSet::STATUS_DRAFT) {
                throw new LogicException('لا يمكن تعديل معايير نسخة قواعد منشورة.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(ReadinessRuleSet::class, 'readiness_rule_set_id');
    }

    public function complianceRequirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class);
    }
}
