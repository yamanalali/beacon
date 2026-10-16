<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignAdvisorAction
{
    public function execute(User $actor, Enterprise $enterprise, User $advisor): void
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        if ($advisor->role !== UserRole::BeaconAdvisor) {
            throw ValidationException::withMessages([
                'user_id' => 'يمكن تعيين مستشار بيكون فقط.',
            ]);
        }

        if ($enterprise->advisors()->whereKey($advisor->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'هذا المستشار معيّن لهذه المنشأة بالفعل.',
            ]);
        }

        DB::transaction(function () use ($actor, $enterprise, $advisor): void {
            $enterprise->advisors()->attach($advisor->id);

            app(RecordAuditLog::class)->record($actor, 'enterprise.advisor_assigned', $enterprise, [
                'user_id' => $advisor->id,
            ]);
        });
    }
}
