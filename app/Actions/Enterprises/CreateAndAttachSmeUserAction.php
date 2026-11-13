<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAndAttachSmeUserAction
{
    public function __construct(private RecordAuditLog $audit) {}

    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public function execute(User $actor, Enterprise $enterprise, array $validated): User
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($validated['email'])])->exists()) {
            throw ValidationException::withMessages([
                'email' => ui('validation.email_already_used'),
            ]);
        }

        return DB::transaction(function () use ($actor, $enterprise, $validated): User {
            $user = new User;
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->forceFill([
                'role' => UserRole::SmeUser,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->save();

            $enterprise->members()->attach($user->id);

            $this->audit->record($actor, 'user.created', $user, [
                'role' => UserRole::SmeUser->value,
                'via' => 'enterprise_show',
                'enterprise_id' => $enterprise->id,
            ]);
            $this->audit->record($actor, 'enterprise.member_attached', $enterprise, [
                'user_id' => $user->id,
                'created' => true,
            ]);

            return $user;
        });
    }
}
