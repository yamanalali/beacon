<?php

namespace App\Http\Requests\Readiness;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMfiInstitutionRequest extends FormRequest
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
        $id = $this->route('institution')?->id;

        return StoreMfiInstitutionRequest::fieldRules($id) + [
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new StoreMfiInstitutionRequest)->messages();
    }
}
