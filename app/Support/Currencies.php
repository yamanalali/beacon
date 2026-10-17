<?php

namespace App\Support;

use App\Models\Currency;

class Currencies
{
    public function enabled(): array
    {
        return Currency::query()->where('enabled', true)->orderBy('code')->pluck('name', 'code')->all();
    }

    public function available(?string $historical = null): array
    {
        $all = $this->enabled();
        if ($historical !== null && ! isset($all[$historical])) {
            $all[$historical] = (Currency::query()->find($historical)?->name ?? $historical).' (موقوفة للعمليات الجديدة)';
        }

        return $all;
    }

    public function default(): string
    {
        $explicit = Currency::query()->where('enabled', true)->where('is_default', true)->value('code');
        if ($explicit) {
            return $explicit;
        }
        $enabled = $this->enabled();
        $fallback = (string) (config('bookkeeping.default_currency') ?: 'SYP');

        return isset($enabled[$fallback]) ? $fallback : (array_key_first($enabled) ?? $fallback);
    }
}
