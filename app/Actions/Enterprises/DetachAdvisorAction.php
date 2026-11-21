<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DetachAdvisorAction
{
    public function execute(User $actor, Enterprise $enterprise, User $advisor): void
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        if (! $enterprise->advisors()->whereKey($advisor->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'هذا المستشار غير معيّن لهذه المنشأة.',
            ]);
        }

        DB::transaction(function () use ($actor, $enterprise, $advisor): void {
            $enterprise->advisors()->detach($advisor->id);

            app(RecordAuditLog::class)->record($actor, 'enterprise.advisor_detached', $enterprise, [
                'user_id' => $advisor->id,
            ]);
        });
    }
}
