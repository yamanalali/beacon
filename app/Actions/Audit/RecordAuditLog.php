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
        $context = self::withoutSecrets($context);

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'target_type' => $target->getMorphClass(),
            'target_id' => (string) $target->getKey(),
            'context' => $context,
        ]);
    }

    /**
     * Never store secrets in audit history (recursive, key-based).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function withoutSecrets(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/pass(word)?|secret|token|api[_-]?key|remember|otp|mfa_code/i', $key)) {
                unset($context[$key]);

                continue;
            }

            if (is_array($value)) {
                $context[$key] = self::withoutSecrets($value);
            }
        }

        return $context;
    }
}
