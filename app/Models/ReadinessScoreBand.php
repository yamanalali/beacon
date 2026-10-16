<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named, coloured result range (e.g. ready / close / needs support).
 * Boundaries are entered by Beacon; color_token refers to a #13 design token.
 */
class ReadinessScoreBand extends Model
{
    protected $fillable = ['readiness_rule_set_id', 'label_ar', 'min', 'max', 'color_token', 'sort_order'];

    protected function casts(): array
    {
        return ['min' => 'decimal:2', 'max' => 'decimal:2', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        $guard = fn (ReadinessScoreBand $band) => ReadinessRuleSet::assertEditable($band->readiness_rule_set_id, $band->getOriginal('readiness_rule_set_id'));

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(ReadinessRuleSet::class, 'readiness_rule_set_id');
    }
}
