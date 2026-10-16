<?php

namespace App\Models;

use App\Enums\AdvisoryRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpAction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'enterprise_id',
        'advisory_activity_id',
        'description',
        'responsible_user_id',
        'target_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'status' => AdvisoryRecordStatus::class,
        ];
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function advisoryActivity(): BelongsTo
    {
        return $this->belongsTo(AdvisoryActivity::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
