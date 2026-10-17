<?php

namespace App\Http\Requests\Bookkeeping;

use App\Enums\PaymentStatus;
use Illuminate\Validation\Rule;

class CorrectTransactionRequest extends StoreTransactionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            // Settlement history determines payment status during correction.
            'payment_status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'paid_amount' => ['prohibited'],
            'client_draft_id' => ['prohibited'],
            'correction_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
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
