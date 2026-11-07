<?php

namespace App\Models;

use App\Ai\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Public self-registration request. Does not create an active login until Beacon reviews it.
 * TODO: BUSINESS DECISION REQUIRED — exact approval workflow and invitation linkage.
 */
class RegistrationApplication extends Model
{
    use HasPublicId;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'business_name',
        'city',
        'notes',
    ];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
