<?php

namespace App\Http\Requests\Advisory;

use App\Enums\UserRole;
use App\Models\Enterprise;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdvisoryActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $enterprise = $this->route('enterprise');

        if ($user === null || ! $enterprise instanceof Enterprise) {
            return false;
        }

        return $user->isActive()
            && in_array($user->role, [UserRole::BeaconAdmin, UserRole::BeaconAdvisor], true)
            && $user->can('update', $enterprise);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activity_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_date' => ['nullable', 'date'],
            'follow_up_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activity_date.required' => 'تاريخ النشاط مطلوب.',
            'activity_date.date' => 'تاريخ النشاط غير صالح.',
        ];
    }
}
