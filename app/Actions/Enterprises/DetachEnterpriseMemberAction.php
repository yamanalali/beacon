<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DetachEnterpriseMemberAction
{
    public function execute(User $actor, Enterprise $enterprise, User $member): void
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        if (! $enterprise->members()->whereKey($member->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'هذا المستخدم غير مرتبط بهذه المنشأة.',
            ]);
        }

        if ($enterprise->members()->count() <= 1) {
            throw ValidationException::withMessages([
                'user_id' => 'لا يمكن إزالة آخر عضو مرتبط بالمنشأة.',
            ]);
        }

        DB::transaction(function () use ($actor, $enterprise, $member): void {
            $enterprise->members()->detach($member->id);

            app(RecordAuditLog::class)->record($actor, 'enterprise.member_detached', $enterprise, [
                'user_id' => $member->id,
            ]);
        });
    }
}
