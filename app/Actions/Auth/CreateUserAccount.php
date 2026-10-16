<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\User;

/**
 * TODO: BUSINESS DECISION REQUIRED
 * Confirm whether SME and MFI accounts are invited by Beacon, created by administrators,
 * self-registered and later approved, or a combination of these methods.
 * Until Beacon confirms that decision, only an active Beacon administrator may create accounts.
 * There is no public registration.
 */
class CreateUserAccount
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, role: string}  $validated
     */
    public function __invoke(User $actor, array $validated): User
    {
        if (! $actor->isActive() || $actor->role !== UserRole::BeaconAdmin) {
            abort(403);
        }

        $user = new User;
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $user->forceFill([
            'role' => $validated['role'],
            'is_active' => true,
        ]);
        $user->save();

        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        $this->audit->record($actor, 'user.created', $user, [
            'role' => $role,
        ]);

        return $user;
    }
}
