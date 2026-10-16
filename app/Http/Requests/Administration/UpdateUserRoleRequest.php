<?php

namespace App\Http\Requests\Administration;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('updateRole', $target) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(UserRole::class)]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['role.required' => 'اختر الدور.', 'role.enum' => 'الدور غير صالح.'];
    }
}
