<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordAuditLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(?User $actor, string $action, Model $target, array $context = []): AuditLog
    {
        unset($context['password'], $context['password_confirmation'], $context['current_password']);

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'target_type' => $target->getMorphClass(),
            'target_id' => (string) $target->getKey(),
            'context' => $context,
        ]);
    }
}
