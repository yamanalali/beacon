<?php

namespace App\Http\Requests\Readiness;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMfiInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'code', 'name_en', 'contact_name', 'contact_phone', 'contact_email',
            'region_ar', 'address_ar', 'website', 'focus_ar', 'notes',
            'loan_size_min', 'loan_size_max', 'loan_currency',
        ];
        $merged = [];
        foreach ($nullable as $key) {
            if (! $this->exists($key)) {
                continue;
            }
            $value = $this->input($key);
            $merged[$key] = is_string($value) && trim($value) === '' ? null : $value;
        }
        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /** @return array<string, mixed> */
    public static function fieldRules(?int $ignoreId = null): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('mfi_institutions', 'code')->ignore($ignoreId)],
            'name_en' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'region_ar' => ['nullable', 'string', 'max:120'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],
            'focus_ar' => ['nullable', 'string', 'max:5000'],
            'loan_size_min' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'loan_size_max' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'gte:loan_size_min'],
            'loan_currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name_ar.required' => ui('validation.mfi_name_required'),
            'code.unique' => ui('validation.mfi_code_unique'),
            'contact_email.email' => ui('validation.email_invalid'),
            'website.url' => ui('validation.website_url'),
            'loan_size_max.gte' => ui('validation.loan_max_gte_min'),
        ];
    }
}
