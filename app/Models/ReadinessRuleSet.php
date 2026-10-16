<?php

namespace App\Models;

use App\Models\Concerns\ImmutableOnceLocked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ReadinessRuleSet extends Model
{
    use ImmutableOnceLocked;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_RETIRED = 'retired';

    public const STATUS_LABELS = [
        'draft' => 'مسودة',
        'published' => 'معتمدة',
        'retired' => 'مؤرشفة',
    ];

    protected $fillable = ['version_number', 'name_ar', 'status', 'change_summary', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'version_number' => 'integer'];
    }

    /** A published set may only move to "retired"; its criteria never change. */
    public function wasLockedBeforeChange(): bool
    {
        $original = $this->getOriginal('status');

        if ($original === self::STATUS_PUBLISHED) {
            return ! ($this->status === self::STATUS_RETIRED && array_keys($this->getDirty()) === ['status']);
        }

        return $original === self::STATUS_RETIRED;
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ReadinessCriterion::class)->orderBy('id');
    }

    public function pillars(): HasMany
    {
        return $this->hasMany(ReadinessPillar::class)->orderBy('sort_order')->orderBy('id');
    }

    public function bands(): HasMany
    {
        return $this->hasMany(ReadinessScoreBand::class)->orderBy('sort_order')->orderBy('min');
    }

    /**
     * Child rows (pillars, bands) may only be written while their set (and the set they come from) is a draft.
     */
    public static function assertEditable(?int ...$ruleSetIds): void
    {
        foreach (array_unique(array_filter($ruleSetIds)) as $id) {
            $set = self::query()->find($id);
            if ($set !== null && $set->status !== self::STATUS_DRAFT) {
                throw new LogicException('لا يمكن تعديل محاور أو نطاقات مجموعة قواعد منشورة.');
            }
        }
    }

    public static function current(): ?self
    {
        return self::query()->where('status', self::STATUS_PUBLISHED)->orderByDesc('version_number')->first();
    }
}
