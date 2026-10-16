<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReferralStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['referral_id', 'from_status', 'to_status', 'note', 'actor_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('سجل الحالات لا يُعدّل.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
