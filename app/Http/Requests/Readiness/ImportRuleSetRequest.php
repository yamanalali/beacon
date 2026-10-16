<?php

namespace App\Http\Requests\Readiness;

use App\Support\ReadinessRuleSetValidator;
use Illuminate\Validation\Validator;

/** Accepts the export JSON either as an uploaded file ("file") or inline ("definition"). Always creates a draft. */
class ImportRuleSetRequest extends ReadinessAdminRequest
{
    private ?array $parsed = null;

    public function rules(): array
    {
        return [
            'file' => ['required_without:definition', 'file', 'max:1024'],
            'definition' => ['required_without:file', 'array'],
            'name_ar' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function definition(): array
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }
        if ($this->hasFile('file')) {
            $decoded = json_decode((string) file_get_contents($this->file('file')->getRealPath()), true);

            return $this->parsed = is_array($decoded) ? $decoded : [];
        }

        return $this->parsed = (array) $this->input('definition', []);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $def = $this->definition();
            if (($def['format'] ?? null) !== ReadinessRuleSetValidator::FORMAT) {
                $validator->errors()->add('definition', 'الملف ليس تعريف قواعد جاهزية من بيكون.');

                return;
            }
            $errors = ReadinessRuleSetValidator::errors($def);
            ReadinessRuleSetValidator::complianceIds($def, $errors);
            foreach ($errors as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add('definition.'.$key, $message);
                }
            }
        }];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'file.required_without' => 'يرجى إرفاق ملف JSON.',
            'definition.required_without' => 'يرجى إرفاق ملف JSON.',
        ];
    }
}
