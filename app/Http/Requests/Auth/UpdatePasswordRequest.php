<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => ui('validation.current_password_required'),
            'current_password.current_password' => ui('validation.current_password_wrong'),
            'password.required' => ui('validation.new_password_required'),
            'password.confirmed' => ui('validation.password_confirmed'),
            'password.min' => ui('validation.password_min'),
        ];
    }
}
