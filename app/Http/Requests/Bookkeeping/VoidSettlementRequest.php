<?php

namespace App\Http\Requests\Bookkeeping;

use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;

class VoidSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $this->user() !== null
            && $transaction instanceof FinancialTransaction
            && $this->user()->can('voidSettlement', $transaction);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
