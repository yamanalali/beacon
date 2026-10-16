<?php

namespace App\Reminders;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\User;
use App\Notifications\ComplianceDeadlineReminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds incomplete compliance records due within the configured lead window
 * and notifies authorised recipients once per record/due date (rules §43).
 * Recipients are derived from enterprise access, never from role alone, so
 * MFI users, technical admins and unrelated users are never notified.
 */
class ComplianceDeadlineReminderService
{
    public const TYPE = 'compliance_deadline';

    /** @return int number of notifications sent */
    public function run(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $until = $today->addDays(max(0, (int) config('reminders.compliance.lead_days', 7)));
        $sent = 0;

        EnterpriseComplianceRecord::query()
            ->with('version')
            ->whereNotNull('due_date')
            ->where('status', '!=', 'completed')
            ->whereBetween('due_date', [$today->toDateString(), $until->toDateString()])
            ->orderBy('id')
            ->chunkById(200, function (Collection $records) use (&$sent) {
                // The compliance model (owned by §20–31) has no enterprise relation; load explicitly.
                $enterprises = Enterprise::query()->whereIn('id', $records->pluck('enterprise_id'))->get()->keyBy('id');
                foreach ($records as $record) {
                    $enterprise = $enterprises->get($record->enterprise_id);
                    if ($enterprise === null) {
                        continue;
                    }
                    foreach ($this->recipients($enterprise) as $user) {
                        if ($this->claim($record, $user)) {
                            $user->notify(ComplianceDeadlineReminder::fromRecord($record, $enterprise));
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }

    /** @return Collection<int, User> */
    private function recipients(Enterprise $enterprise): Collection
    {
        $users = $enterprise->members()->get()
            ->filter(fn (User $u) => $u->isActive() && $u->role === UserRole::SmeUser);

        if (config('reminders.compliance.notify_assigned_advisors')) {
            $users = $users->merge($enterprise->advisors()->get()
                ->filter(fn (User $u) => $u->isActive() && $u->role === UserRole::BeaconAdvisor));
        }

        // Final guard: recipient must be allowed to view the enterprise.
        return $users->unique('id')->filter(fn (User $u) => $u->can('view', $enterprise))->values();
    }

    private function claim(EnterpriseComplianceRecord $record, User $user): bool
    {
        try {
            DB::table('reminder_dispatches')->insert([
                'reminder_type' => self::TYPE,
                'enterprise_compliance_record_id' => $record->id,
                'user_id' => $user->id,
                'due_date' => $record->due_date->toDateString(),
                'created_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
