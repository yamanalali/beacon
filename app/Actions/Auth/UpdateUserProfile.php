<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lets an administrator correct another user's name and email, with before/after audit.
 * Role, permissions and activation are deliberately not touched here.
 */
class UpdateUserProfile
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @param  array{name: string, email: string}  $validated
     */
    public function __invoke(User $actor, User $target, array $validated): User
    {
        abort_unless($actor->can('updateProfile', $target), 403);

        DB::transaction(function () use ($actor, $target, $validated): void {
            $before = ['name' => $target->name, 'email' => $target->email];

            $target->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (! $target->isDirty(['name', 'email'])) {
                return;
            }

            $target->save();

            $this->audit->record($actor, 'user.profile_updated', $target, [
                'before' => $before,
                'after' => ['name' => $target->name, 'email' => $target->email],
            ]);
        });

        return $target;
    }
}
