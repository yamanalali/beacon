<?php

namespace App\Http\Requests\Readiness;

use App\Support\ReadinessExampleTemplate;
use App\Support\ReadinessRuleSetValidator;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use Throwable;

/** Accepts export JSON, the Excel template, or an inline definition. Always creates a draft. */
class ImportRuleSetRequest extends ReadinessAdminRequest
{
    private ?array $parsed = null;

    public function rules(): array
    {
        return [
            'file' => ['required_without:definition', 'file', 'max:2048'],
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
            return $this->parsed = $this->definitionFromUpload();
        }

        return $this->parsed = (array) $this->input('definition', []);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                $def = $this->definition();
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('file', $e->getMessage());

                return;
            } catch (Throwable) {
                $validator->errors()->add('file', ui('validation.import_file_read_failed'));

                return;
            }
            if (($def['format'] ?? null) !== ReadinessRuleSetValidator::FORMAT) {
                $validator->errors()->add('definition', ui('validation.import_not_beacon_rules'));

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
            'file.required_without' => ui('validation.import_file_or_definition'),
            'definition.required_without' => ui('validation.import_file_or_definition'),
        ];
    }

    private function definitionFromUpload(): array
    {
        $file = $this->file('file');
        $path = $file->getRealPath();
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if (in_array($ext, ['xlsx', 'xlsm'], true) || str_contains($mime, 'spreadsheetml')) {
            return ReadinessExampleTemplate::definitionFromExcel($path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
