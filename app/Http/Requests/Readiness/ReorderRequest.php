<?php

namespace App\Http\Requests\Readiness;

class ReorderRequest extends ReadinessAdminRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
