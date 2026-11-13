<?php

namespace App\Http\Requests\Enterprises;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreEnterpriseMemberRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', function ($attribute, $value, $fail) {
                if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($value)])->exists()) {
                    $fail(ui('validation.email_already_used'));
                }
            }],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => ui('validation.name_required'),
            'email.required' => ui('validation.email_required'),
            'email.email' => ui('validation.email_invalid'),
            'email.unique' => ui('validation.email_already_used'),
            'password.required' => ui('validation.password_required'),
            'password.confirmed' => ui('validation.password_confirmed'),
        ];
    }
}
