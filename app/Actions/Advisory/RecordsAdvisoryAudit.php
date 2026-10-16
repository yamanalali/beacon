<?php

namespace App\Actions\Advisory;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Enterprise;
use App\Models\User;

trait RecordsAdvisoryAudit
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected function recordAdvisoryAudit(User $actor, string $action, Enterprise $enterprise, array $context): void
    {
        if (! class_exists(RecordAuditLog::class)) {
            return;
        }

        app(RecordAuditLog::class)->record($actor, $action, $enterprise, $context);
    }
}
