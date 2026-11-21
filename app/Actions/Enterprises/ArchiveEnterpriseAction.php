<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveEnterpriseAction
{
    public function execute(User $actor, Enterprise $enterprise, ?string $reason = null): void
    {
        if ($enterprise->isArchived()) {
            throw ValidationException::withMessages([
                'enterprise' => 'هذه المنشأة مؤرشفة بالفعل.',
            ]);
        }

        DB::transaction(function () use ($actor, $enterprise, $reason): void {
            $enterprise->forceFill([
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason !== null && $reason !== '' ? mb_substr($reason, 0, 255) : null,
            ])->save();

            app(RecordAuditLog::class)->record($actor, 'enterprise.archived', $enterprise, [
                'reason' => $enterprise->archive_reason,
            ]);
        });
    }
}
