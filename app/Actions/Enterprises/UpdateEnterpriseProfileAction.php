<?php

namespace App\Actions\Enterprises;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateEnterpriseProfileAction
{
    /**
     * Profile columns accepted from a validated FormRequest payload.
     *
     * @var list<string>
     */
    private const FIELDS = [
        'business_name',
        'sector',
        'location',
        'founding_date',
        'employee_count',
        'female_employee_count',
        'male_employee_count',
        'owner_name',
        'contact_name',
        'contact_phone',
        'contact_email',
        'registration_status',
        'initial_digital_readiness_note',
        'initial_financial_readiness_note',
        'programme_stage',
        'progress_summary',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, Enterprise $enterprise, array $attributes): Enterprise
    {
        return DB::transaction(function () use ($actor, $enterprise, $attributes): Enterprise {
            $changedKeys = [];

            foreach (self::FIELDS as $field) {
                if (! array_key_exists($field, $attributes)) {
                    continue;
                }

                $next = $this->normalize($field, $attributes[$field]);
                $current = $this->normalize($field, $enterprise->getAttribute($field));

                if ($current === $next) {
                    continue;
                }

                $enterprise->setAttribute($field, $next);
                $changedKeys[] = $field;
            }

            if ($changedKeys !== []) {
                $enterprise->save();

                app(RecordAuditLog::class)->record($actor, 'enterprise.profile_updated', $enterprise, [
                    'changed_keys' => $changedKeys,
                ]);
            }

            return $enterprise;
        });
    }

    private function normalize(string $field, mixed $value): mixed
    {
        if ($value === '') {
            $value = null;
        }

        if (in_array($field, ['employee_count', 'female_employee_count', 'male_employee_count'], true)) {
            return $value === null ? null : (int) $value;
        }

        if ($field === 'founding_date') {
            if ($value === null) {
                return null;
            }

            return (string) (is_object($value) && method_exists($value, 'toDateString')
                ? $value->toDateString()
                : $value);
        }

        return $value;
    }
}
