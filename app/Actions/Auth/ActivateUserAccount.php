<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\AccountSecurity;
use Illuminate\Support\Facades\DB;

class ActivateUserAccount
{
    public function __construct(private RecordAuditLog $audit) {}

    public function __invoke(User $actor, User $target): void
    {
        if (! $actor->isActive() || $actor->role !== UserRole::BeaconAdmin || $actor->is($target)) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $target): void {
            [$actor, $target] = AccountSecurity::administrationPair($actor, $target);
            $target->forceFill(['is_active' => true])->save();
            $this->audit->record($actor, 'user.activated', $target, ['is_active' => true]);
        });
    }
}
