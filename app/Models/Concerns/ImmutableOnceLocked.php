<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Records that are published/finalized must never be rewritten (sections 21, 26).
 * The using model defines isLocked() based on its ORIGINAL (persisted) state.
 */
trait ImmutableOnceLocked
{
    protected static function bootImmutableOnceLocked(): void
    {
        static::updating(function ($model): void {
            if ($model->wasLockedBeforeChange()) {
                throw new LogicException('سجل منشور أو نهائي ولا يمكن تعديله.');
            }
        });

        static::deleting(function ($model): void {
            if ($model->wasLockedBeforeChange()) {
                throw new LogicException('سجل منشور أو نهائي ولا يمكن حذفه.');
            }
        });
    }

    abstract public function wasLockedBeforeChange(): bool;
}
