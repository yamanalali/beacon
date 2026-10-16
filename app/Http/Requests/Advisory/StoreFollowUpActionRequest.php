<?php

namespace App\Http\Requests\Advisory;

use App\Enums\UserRole;
use App\Models\Enterprise;
use Illuminate\Foundation\Http\FormRequest;

class StoreFollowUpActionRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'advisory_activity_id' => ['nullable', 'integer'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'وصف الإجراء مطلوب.',
        ];
    }
}
