<?php

namespace App\Models;

use App\Enums\AdvisoryRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TODO: BUSINESS DECISION REQUIRED
 * Exact mandatory fields for advisory/technical-assistance records are not confirmed.
 * Missing decision: which inputs besides the enterprise and activity date are mandatory.
 * Options: (1) require only activity_date and the enterprise — this is what the application does now;
 * (2) also require notes, a responsible person, and a target date;
 * (3) replace this field list after Beacon confirms the advisory form.
 * Effect: option 1 lets staff record a visit immediately and keeps notes, responsible person, and target date optional.
 * Options 2 and 3 would reject records that are valid today.
 * Safe before the decision: store every field the contract already names, and require only activity_date plus the enterprise.
 */
class AdvisoryActivity extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'enterprise_id',
        'activity_date',
        'notes',
        'responsible_user_id',
        'target_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'target_date' => 'date',
            'status' => AdvisoryRecordStatus::class,
        ];
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUpAction::class);
    }
}
