<?php

namespace App\Ai\Models\Concerns;

use Illuminate\Support\Str;

/** ULID public identifier used for route binding (never expose numeric ids). */
trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (blank($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
