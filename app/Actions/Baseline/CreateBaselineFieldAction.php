<?php

namespace App\Actions\Baseline;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\BaselineProfileField;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBaselineFieldAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @param  array{key: string, label_ar: string, section: string, input_kind: string, excel_column?: ?string}  $attributes
     */
    public function execute(User $actor, array $attributes): BaselineProfileField
    {
        abort_unless($actor->isActive() && $actor->role === UserRole::BeaconAdmin, 403);

        return DB::transaction(function () use ($actor, $attributes): BaselineProfileField {
            $field = new BaselineProfileField;
            $field->fill([
                'key' => $attributes['key'],
                'label_ar' => $attributes['label_ar'],
                'section' => $attributes['section'],
                'input_kind' => $attributes['input_kind'],
                'excel_column' => $attributes['excel_column'] ?? null,
                'is_visible' => true,
                'is_child' => false,
                'is_system' => false,
                'sort_order' => ((int) BaselineProfileField::query()->max('sort_order')) + 1,
            ]);
            $field->save();

            $this->audit->record($actor, 'baseline.field.created', $field, [
                'key' => $field->key,
                'section' => $attributes['section'],
                'input_kind' => $field->input_kind,
                'excel_column' => $field->excel_column,
            ]);

            return $field;
        });
    }
}
