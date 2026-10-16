<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TODO: BUSINESS DECISION REQUIRED - confirm the administrative fields needed for an MFI/institution record (section 27).
 */
class MfiInstitution extends Model
{
    use HasPublicUlid;

    protected $fillable = ['public_id', 'name_ar', 'notes', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function eligibilityCriteria(): HasMany
    {
        return $this->hasMany(MfiEligibilityCriterion::class)->orderBy('id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
