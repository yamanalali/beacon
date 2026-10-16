<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceRequirement extends Model
{
    use HasPublicUlid;

    /** Categories listed in section 20. Content per category is supplied by Beacon. */
    public const CATEGORIES = [
        'business_registration' => 'التسجيل التجاري',
        'licensing' => 'الترخيص',
        'tax_documentation' => 'الوثائق الضريبية',
        'reporting_obligation' => 'التزامات التقارير',
        'required_document' => 'وثيقة مطلوبة',
        'renewal' => 'تجديد',
    ];

    protected $fillable = ['public_id', 'code', 'category', 'is_active', 'replaced_by_id', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ComplianceRequirementVersion::class)->orderBy('version_number');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    /** Latest published version whose effective date has started (or has no effective date). */
    public function currentVersion(): ?ComplianceRequirementVersion
    {
        return $this->versions()
            ->where('status', ComplianceRequirementVersion::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now()->toDateString()))
            ->reorder('version_number', 'desc')
            ->first();
    }

    public function nextVersionNumber(): int
    {
        return ((int) $this->versions()->max('version_number')) + 1;
    }
}
