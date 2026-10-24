<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\AccountSecurity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes a user's role with before/after audit and session revocation.
 *
 * TODO: BUSINESS DECISION REQUIRED
 * Confirm what happens to existing enterprise memberships/advisor
 * assignments when a role changes. Currently they are kept, but access is
 * always re-evaluated against the NEW role by the policies.
 */
class ChangeUserRole
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function __invoke(User $actor, User $target, UserRole $role): void
    {
        abort_unless($actor->can('updateRole', $target), 403);

        if ($target->role === $role) {
            return;
        }

        DB::transaction(function () use ($actor, $target, $role): void {
            [$actor, $target, $activeAdmins] = AccountSecurity::administrationPair($actor, $target);
            if ($target->role === UserRole::BeaconAdmin && $target->isActive() && $activeAdmins <= 1) {
                throw ValidationException::withMessages(['role' => 'لا يمكن تغيير دور آخر مدير نشط في النظام.']);
            }

            $before = $target->role;
            $target->forceFill(['role' => $role])->save();

            AccountSecurity::revokeDatabaseSessions($target);

            $this->audit->record($actor, 'user.role_changed', $target, [
                'before' => ['role' => $before->value],
                'after' => ['role' => $role->value],
            ]);
        });
    }
}
