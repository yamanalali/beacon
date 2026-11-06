<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MFI directory record used for matching and referral scoping.
 *
 * TODO: BUSINESS DECISION REQUIRED — Beacon must confirm the final administrative
 * fields for an MFI/institution record (product §27). Current columns are
 * directory/contact/coverage metadata only; eligibility stays in criteria.
 */
class MfiInstitution extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'public_id',
        'name_ar',
        'code',
        'name_en',
        'contact_name',
        'contact_phone',
        'contact_email',
        'region_ar',
        'address_ar',
        'website',
        'focus_ar',
        'loan_size_min',
        'loan_size_max',
        'loan_currency',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'loan_size_min' => 'decimal:2',
            'loan_size_max' => 'decimal:2',
        ];
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
