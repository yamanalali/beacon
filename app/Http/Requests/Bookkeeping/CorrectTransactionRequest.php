<?php

namespace App\Http\Requests\Bookkeeping;

class CorrectTransactionRequest extends StoreTransactionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'correction_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'correction_reason.required' => 'اكتب سبب التصحيح.',
            'correction_reason.min' => 'اكتب سبب التصحيح بشكل أوضح.',
        ];
    }
}
