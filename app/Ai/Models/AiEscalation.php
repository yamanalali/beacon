<?php

namespace App\Ai\Models;

use App\Ai\Models\Concerns\HasPublicId;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escalation of an unsupported assistant question to Beacon staff (rules §35).
 *
 * Interim reply workflow: assigned advisor / Beacon admin may post one staff reply;
 * status moves from open → answered. Full routing policy still pending Beacon.
 */
class AiEscalation extends Model
{
    use HasPublicId;

    public const STATUS_OPEN = 'open';

    public const STATUS_ANSWERED = 'answered';

    public const STATUSES = [
        self::STATUS_OPEN => 'مفتوح',
        self::STATUS_ANSWERED => 'تم الرد',
    ];

    protected $fillable = [
        'user_id', 'enterprise_id', 'question', 'context', 'status',
        'staff_reply', 'replied_by', 'replied_at',
    ];

    protected $hidden = ['id', 'user_id', 'enterprise_id', 'replied_by'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
