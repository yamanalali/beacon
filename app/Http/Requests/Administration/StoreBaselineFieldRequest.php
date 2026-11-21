<?php

namespace App\Http\Requests\Administration;

use App\Enums\BaselineProfileSection;
use App\Enums\UserRole;
use App\Models\BaselineProfileField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBaselineFieldRequest extends FormRequest
{
    /** Input kinds the profile page already knows how to display. */
    public const INPUT_KINDS = ['text', 'boolean', 'multi_choice'];

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['key', 'label_ar'] as $name) {
            if (is_string($this->input($name))) {
                $merge[$name] = trim($this->input($name));
            }
        }

        if (is_string($this->input('excel_column'))) {
            $column = strtoupper(trim($this->input('excel_column')));
            $merge['excel_column'] = $column === '' ? null : $column;
        }

        $this->merge($merge);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->isActive()
            && $user->role === UserRole::BeaconAdmin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:baseline_profile_fields,key'],
            'label_ar' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', Rule::enum(BaselineProfileSection::class)],
            'input_kind' => ['required', 'string', Rule::in(self::INPUT_KINDS)],
            'excel_column' => ['nullable', 'string', 'max:8', 'regex:/^[A-Z]{1,3}$/', function ($attribute, $value, $fail): void {
                if ($value !== null && BaselineProfileField::query()->where('excel_column', $value)->exists()) {
                    $fail(ui('validation.baseline_excel_column_taken'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.required' => ui('validation.baseline_key_required'),
            'key.regex' => ui('validation.baseline_key_format'),
            'key.unique' => ui('validation.baseline_key_taken'),
            'label_ar.required' => ui('validation.baseline_label_required'),
            'excel_column.regex' => ui('validation.baseline_excel_column_format'),
        ];
    }
}
