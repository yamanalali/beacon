<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\AccountSecurity;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => ui('validation.email_required'),
            'email.email' => ui('validation.email_invalid'),
            'password.required' => ui('validation.password_required'),
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = mb_strtolower(trim($this->string('email')->toString()));
        $canonical = AccountSecurity::canonicalEmail($email);
        $credentials = ['email' => $canonical ?? '', 'password' => $this->input('password')];
        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages(['email' => ui('validation.login_failed')]);
        }
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isActive()) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => ui('validation.account_suspended'),
            ]);
        }

        AccountSecurity::bind($this, $user);

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => ui('validation.login_throttle', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
