<?php

namespace App\Models\Concerns;

trait HasTwoFactor
{
    public function initializeHasTwoFactor(): void
    {
        $this->mergeHidden([
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_last_timestep',
        ]);

        $this->mergeCasts([
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_timestep' => 'integer',
        ]);
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
