<?php

namespace App\Http\Requests\Enterprises;

use Illuminate\Foundation\Http\FormRequest;

class AssignEnterpriseUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enterprise = $this->route('enterprise');

        return $enterprise !== null && ($this->user()?->can('assignMembers', $enterprise) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'اختر مستخدماً.',
            'user_id.exists' => 'المستخدم غير موجود.',
        ];
    }
}
