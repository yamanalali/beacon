<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use App\Models\Concerns\ImmutableOnceLocked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadinessAssessment extends Model
{
    use HasPublicUlid, ImmutableOnceLocked;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'public_id', 'enterprise_id', 'readiness_rule_set_id', 'status', 'score',
        'mandatory_conditions_met', 'summary', 'assessed_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'mandatory_conditions_met' => 'boolean',
            'summary' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function wasLockedBeforeChange(): bool
    {
        return $this->getOriginal('status') === self::STATUS_FINALIZED;
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(ReadinessRuleSet::class, 'readiness_rule_set_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReadinessAssessmentResult::class)->orderBy('id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}
