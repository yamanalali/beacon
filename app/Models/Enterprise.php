<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Enterprise extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'business_name',
        'sector',
        'location',
        'founding_date',
        'employee_count',
        'female_employee_count',
        'male_employee_count',
        'owner_name',
        'contact_name',
        'contact_phone',
        'contact_email',
        'registration_status',
        'initial_digital_readiness_note',
        'initial_financial_readiness_note',
        'programme_stage',
        'progress_summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'founding_date' => 'date',
            'employee_count' => 'integer',
            'female_employee_count' => 'integer',
            'male_employee_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Enterprise $enterprise): void {
            if ($enterprise->public_id === null || $enterprise->public_id === '') {
                $enterprise->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enterprise_members')->withTimestamps();
    }

    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'advisor_assignments')->withTimestamps();
    }

    /**
     * Fields staff can complete later. Onboarding does not require them.
     *
     * @return list<string>
     */
    public function incompleteFieldNames(): array
    {
        $missing = [];

        foreach (['sector', 'founding_date', 'employee_count', 'owner_name', 'registration_status'] as $field) {
            if ($this->{$field} === null || $this->{$field} === '') {
                $missing[] = $field;
            }
        }

        if ($this->contact_name === null || $this->contact_name === '') {
            $missing[] = 'contact';
        }

        return $missing;
    }
}
