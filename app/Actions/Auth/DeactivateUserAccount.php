<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeactivateUserAccount
{
    public function __construct(private RecordAuditLog $audit) {}

    public function __invoke(User $actor, User $target): void
    {
        if (! $actor->can('deactivate', $target)) {
            abort(403);
        }

        $target->forceFill([
            'is_active' => false,
        ])->save();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $target->getKey())->delete();
        }

        $this->audit->record($actor, 'user.deactivated', $target, [
            'is_active' => false,
        ]);
    }
}
