<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\AccountSecurity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeactivateUserAccount
{
    public function __construct(private RecordAuditLog $audit) {}

    public function __invoke(User $actor, User $target): void
    {
        if (! $actor->can('deactivate', $target)) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $target): void {
            [$actor, $target, $activeAdmins] = AccountSecurity::administrationPair($actor, $target);
            if ($target->role === UserRole::BeaconAdmin && $target->isActive() && $activeAdmins <= 1) {
                throw ValidationException::withMessages(['user' => 'لا يمكن إيقاف آخر مدير نشط.']);
            }
            $target->forceFill(['is_active' => false])->save();
            AccountSecurity::revokeDatabaseSessions($target);
            $this->audit->record($actor, 'user.deactivated', $target, ['is_active' => false]);
        });
    }
}
