<?php

namespace App\Http\Requests\Administration;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('email'))) {
            $merge['email'] = mb_strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('name'))) {
            $merge['name'] = trim($this->input('name'));
        }

        $this->merge($merge);
    }

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('updateProfile', $target) === true;
    }

    /**
     * Only name and email are accepted; role, permissions and activation have their own endpoints.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
                function ($attribute, $value, $fail) use ($target): void {
                    $taken = User::query()
                        ->whereKeyNot($target->id)
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $value)])
                        ->exists();

                    if ($taken) {
                        $fail(ui('validation.email_already_used'));
                    }
                },
            ],
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
        ];
    }
}
