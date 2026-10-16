<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateEnterpriseAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, array $attributes): Enterprise
    {
        abort_unless(
            $actor->isActive() && in_array($actor->role, [UserRole::BeaconAdmin, UserRole::BeaconAdvisor], true),
            403,
        );

        return DB::transaction(function () use ($actor, $attributes): Enterprise {
            $enterprise = new Enterprise;
            $enterprise->business_name = $attributes['business_name'];
            $enterprise->location = $attributes['location'];
            $enterprise->contact_phone = $this->blankToNull($attributes['contact_phone'] ?? null);
            $enterprise->contact_email = $this->blankToNull($attributes['contact_email'] ?? null);
            $enterprise->created_by = $actor->id;
            $enterprise->save();

            if ($actor->role === UserRole::BeaconAdvisor) {
                $enterprise->advisors()->attach($actor->id);
            }

            app(RecordAuditLog::class)->record($actor, 'enterprise.created', $enterprise, [
                'public_id' => $enterprise->public_id,
            ]);

            return $enterprise;
        });
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
