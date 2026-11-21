<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreEnterpriseAction
{
    public function execute(User $actor, Enterprise $enterprise): void
    {
        if (! $enterprise->isArchived()) {
            throw ValidationException::withMessages([
                'enterprise' => 'هذه المنشأة ليست مؤرشفة.',
            ]);
        }

        DB::transaction(function () use ($actor, $enterprise): void {
            $enterprise->forceFill([
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ])->save();

            app(RecordAuditLog::class)->record($actor, 'enterprise.restored', $enterprise, []);
        });
    }
}
