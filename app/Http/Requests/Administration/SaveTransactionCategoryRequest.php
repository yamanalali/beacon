<?php

namespace App\Http\Requests\Administration;

use App\Enums\TransactionType;
use App\Models\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST) and edit (PUT) share these rules; the key is only accepted on create. */
class SaveTransactionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage', TransactionCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'label_ar' => ['required', 'string', 'max:120'],
            'label_en' => ['nullable', 'string', 'max:120'],
            'type_scope' => ['nullable', Rule::enum(TransactionType::class)],
            'is_active' => ['required', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];

        if ($this->isMethod('POST')) {
            $rules['key'] = ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,59}$/', Rule::unique('transaction_categories', 'key')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.required' => ui('validation.category_key_required'),
            'key.regex' => ui('validation.category_key_format'),
            'key.unique' => ui('validation.category_key_taken'),
            'label_ar.required' => ui('validation.category_label_required'),
        ];
    }
}
