<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scoring axis (financial, legal, operational...) inside a rule set.
 * Weight and min_score are entered by Beacon from the admin screen; no defaults in code.
 */
class ReadinessPillar extends Model
{
    protected $fillable = ['readiness_rule_set_id', 'code', 'label_ar', 'weight', 'min_score', 'sort_order'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'min_score' => 'decimal:2', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        $guard = fn (ReadinessPillar $pillar) => ReadinessRuleSet::assertEditable($pillar->readiness_rule_set_id, $pillar->getOriginal('readiness_rule_set_id'));

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(ReadinessRuleSet::class, 'readiness_rule_set_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ReadinessCriterion::class)->orderBy('sort_order')->orderBy('id');
    }
}
