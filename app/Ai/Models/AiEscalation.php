<?php

namespace App\Ai\Models;

use App\Models\Enterprise;
use App\Models\User;
use App\Ai\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escalation of an unsupported assistant question to Beacon staff (rules §35).
 *
 * TODO: BUSINESS DECISION REQUIRED — who receives escalations, the status set,
 * whether the user gets an in-app reply, and the response workflow. Only the
 * initial "open" status is implemented; no routing/assignment or reply flow.
 */
class AiEscalation extends Model
{
    use HasPublicId;

    protected $fillable = ['user_id', 'enterprise_id', 'question', 'context', 'status'];

    protected $hidden = ['id', 'user_id', 'enterprise_id'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }
}
