<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MFI-specific eligibility criteria are configuration data (section 27), never hard-coded.
 * The supported criterion TYPES are generic comparison mechanisms; the actual criteria,
 * thresholds and which MFI uses them are entered by Beacon administrators.
 */
class MfiEligibilityCriterion extends Model
{
    protected $table = 'mfi_eligibility_criteria';

    public const TYPES = [
        'min_readiness_score' => 'حد أدنى لنتيجة الجاهزية',
        'mandatory_readiness_conditions_met' => 'استيفاء الشروط الإلزامية للجاهزية',
        'compliance_requirement_completed' => 'إكمال متطلب امتثال محدد',
        'manual_review' => 'شرط يحتاج مراجعة يدوية',
    ];

    protected $fillable = ['mfi_institution_id', 'label_ar', 'type', 'parameters', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'is_active' => 'boolean'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(MfiInstitution::class, 'mfi_institution_id');
    }
}
