<?php

namespace App\Http\Requests\Administration;

use App\Enums\BaselineProfileSection;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBaselineFieldVisibilityRequest extends FormRequest
{
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
            'section' => ['nullable', 'string', Rule::enum(BaselineProfileSection::class)],
            'section_visible' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array'],
            'fields.*.id' => ['required', 'integer', 'exists:baseline_profile_fields,id'],
            'fields.*.is_visible' => ['required', 'boolean'],
        ];
    }
}
