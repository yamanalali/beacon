<?php

namespace App\Http\Requests\Readiness;

class ScoreBandRequest extends ReadinessAdminRequest
{
    public function rules(): array
    {
        $req = $this->route('band') === null ? 'required' : 'sometimes';

        return [
            'label_ar' => [$req, 'string', 'max:255'],
            'min' => [$req, 'numeric', 'min:0', 'max:100'],
            'max' => [$req, 'numeric', 'min:0', 'max:100'],
            'color_token' => [$req, 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'readiness_rule_set_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $band = $this->route('band');
            $min = $this->input('min', $band?->min);
            $max = $this->input('max', $band?->max);
            if (is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
                $validator->errors()->add('min', 'الحد الأدنى يجب ألا يتجاوز الحد الأعلى.');
            }
        }];
    }

    public function messages(): array
    {
        return parent::messages() + ['color_token.regex' => 'رمز اللون يجب أن يكون من ألوان نظام التصميم.'];
    }
}
