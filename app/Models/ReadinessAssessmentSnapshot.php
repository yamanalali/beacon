<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Frozen scorer output. Only `is_current` may flip (true -> false) when a newer recalculation supersedes it. */
class ReadinessAssessmentSnapshot extends Model
{
    public const REASON_INITIAL = 'initial';
    public const REASON_RECALCULATION = 'recalculation';

    protected $fillable = [
        'readiness_assessment_id', 'enterprise_id', 'readiness_rule_set_id', 'rule_set_version', 'config_hash',
        'score', 'mandatory_conditions_met', 'band', 'evaluation', 'config', 'outcomes', 'reason', 'reason_note',
        'is_current', 'computed_by', 'computed_at', 'previous_snapshot_id',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'mandatory_conditions_met' => 'boolean',
            'evaluation' => 'array',
            'config' => 'array',
            'outcomes' => 'array',
            'is_current' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $s): void {
            if (array_diff(array_keys($s->getDirty()), ['is_current', 'updated_at']) !== [] || $s->is_current) {
                throw new LogicException('لقطة النتيجة مجمّدة ولا يمكن تعديلها.');
            }
        });
        static::deleting(fn () => throw new LogicException('لقطة النتيجة مجمّدة ولا يمكن حذفها.'));
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ReadinessAssessment::class, 'readiness_assessment_id');
    }

    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    public function toDisplay(): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'band' => $this->band,
            'mandatory_conditions_met' => $this->mandatory_conditions_met,
            'reason' => $this->reason,
            'reason_note' => $this->reason_note,
            'rule_set_version' => $this->rule_set_version,
            'config_hash' => $this->config_hash,
            'is_current' => $this->is_current,
            'computed_at' => $this->computed_at?->toDateTimeString(),
            'computed_by' => $this->computedBy?->name,
            'band_detail' => self::bandSummary($this->evaluation['band'] ?? null, $this->band),
        ];
    }

    /** Current-snapshot display plus the full frozen evaluate() result for the result page. */
    public function toDisplayWithResult(): array
    {
        return $this->toDisplay() + ['result' => self::presentResult($this->evaluation)];
    }

    /** Whitelisted band summary; legacy snapshots only have the band name column (or nothing). */
    public static function bandSummary(mixed $band, ?string $fallbackName = null): ?array
    {
        if (is_array($band)) {
            return [
                'name' => $band['name'] ?? $band['label_ar'] ?? $fallbackName,
                'label_ar' => $band['label_ar'] ?? null,
                'color_token' => $band['color_token'] ?? null,
            ];
        }
        $name = is_string($band) ? $band : $fallbackName;

        return $name === null ? null : ['name' => $name, 'label_ar' => null, 'color_token' => null];
    }

    /**
     * Normalised, whitelisted evaluate() output (frozen snapshot or live draft). Keys missing from pre-#74
     * snapshots come back null so the frontend can render them gracefully. No database ids are exposed.
     */
    public static function presentResult(?array $e): ?array
    {
        if ($e === null) {
            return null;
        }
        $list = fn ($v) => is_array($v) ? array_values($v) : null;
        $criterion = fn ($c) => is_array($c) ? array_intersect_key($c, array_flip(['code', 'label_ar', 'type', 'value', 'points', 'weight', 'contribution', 'outcome'])) : null;
        $pillar = fn ($p) => is_array($p) ? array_intersect_key($p, array_flip(['code', 'label_ar', 'weight', 'score', 'min_score', 'blocking', 'contribution']))
            + ['criteria' => array_values(array_filter(array_map($criterion, (array) ($p['criteria'] ?? []))))] : null;
        $pillars = fn ($v) => is_array($v) ? array_values(array_filter(array_map($pillar, $v))) : null;
        $band = $e['band'] ?? null;

        return [
            'score' => $e['score'] ?? null,
            'band' => is_array($band) ? [
                'name' => $band['name'] ?? $band['label_ar'] ?? null,
                'label_ar' => $band['label_ar'] ?? null,
                'color_token' => $band['color_token'] ?? null,
                'min' => $band['min'] ?? null,
                'max' => $band['max'] ?? null,
            ] : null,
            'mandatory_conditions_met' => $e['mandatory_conditions_met'] ?? null,
            'blocking' => $list($e['blocking'] ?? null),
            'pillars' => $pillars($e['pillars'] ?? null),
            'breakdown' => $pillars($e['breakdown'] ?? null),
            'improvements' => is_array($e['improvements'] ?? null) ? array_values(array_map(
                fn ($i) => array_intersect_key((array) $i, array_flip(['code', 'label_ar', 'potential_gain'])), $e['improvements'])) : null,
            'earned_weight' => $e['earned_weight'] ?? null,
            'counted_weight' => $e['counted_weight'] ?? null,
            'unmet_mandatory' => $list($e['unmet_mandatory'] ?? null) ?? [],
            'needs_review' => $list($e['needs_review'] ?? null) ?? [],
            'missing' => $list($e['missing'] ?? null) ?? [],
            'unmet' => $list($e['unmet'] ?? null) ?? [],
        ];
    }
}
