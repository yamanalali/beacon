<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralConsent extends Model
{
    public const STATUS_GRANTED = 'granted';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'referral_id', 'enterprise_id', 'mfi_institution_id', 'purpose_ar', 'consent_version',
        'consent_text_ar', 'scope', 'status', 'granted_by', 'granted_at', 'withdrawn_by', 'withdrawn_at',
    ];

    protected function casts(): array
    {
        return ['scope' => 'array', 'granted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
