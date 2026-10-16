<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachSmeUserAction
{
    public function execute(User $actor, Enterprise $enterprise, User $member): void
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        if ($member->role !== UserRole::SmeUser) {
            throw ValidationException::withMessages([
                'user_id' => 'يمكن ربط مستخدم منشأة فقط.',
            ]);
        }

        $alreadyAttached = DB::table('enterprise_members')->where('user_id', $member->id)->exists();

        if ($alreadyAttached) {
            throw ValidationException::withMessages([
                'user_id' => 'هذا المستخدم مرتبط بمنشأة بالفعل.',
            ]);
        }

        DB::transaction(function () use ($actor, $enterprise, $member): void {
            $enterprise->members()->attach($member->id);

            app(RecordAuditLog::class)->record($actor, 'enterprise.member_attached', $enterprise, [
                'user_id' => $member->id,
            ]);
        });
    }
}
