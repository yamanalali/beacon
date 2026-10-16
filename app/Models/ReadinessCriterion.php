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

    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_NUMERIC_BANDS = 'numeric_bands'; // config: {unit, bands:[{min,max,points}]}
    public const TYPE_CHOICE = 'choice'; // config: {options:[{value,label_ar,points}]}
    public const TYPE_TARGET_RATIO = 'target_ratio'; // config: {target, direction: higher|lower, cap}
    public const TYPE_MANUAL_SCALE = 'manual_scale'; // config: {min, max, labels_ar?}

    public const TYPES = [
        self::TYPE_BOOLEAN, self::TYPE_NUMERIC_BANDS, self::TYPE_CHOICE, self::TYPE_TARGET_RATIO, self::TYPE_MANUAL_SCALE,
    ];

    protected $fillable = [
        'readiness_rule_set_id', 'code', 'label_ar', 'description_ar', 'weight', 'is_mandatory',
        'missing_data_treatment', 'compliance_requirement_id',
        'readiness_pillar_id', 'type', 'config', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'is_mandatory' => 'boolean', 'config' => 'array', 'sort_order' => 'integer'];
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

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ReadinessPillar::class, 'readiness_pillar_id');
    }

    public function complianceRequirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class);
    }
}
