<?php

namespace App\Http\Requests\Compliance;

use App\Models\Enterprise;
use App\Support\ReadinessAccess;
use Illuminate\Foundation\Http\FormRequest;

class SubmitComplianceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enterprise = $this->route('enterprise');

        return $enterprise instanceof Enterprise
            && ReadinessAccess::isEnterpriseMember($this->user(), $enterprise);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('documents.max_kilobytes'),
                'mimes:'.implode(',', config('documents.allowed_extensions')),
                'mimetypes:'.implode(',', config('documents.allowed_mime_types')),
            ],
            'title' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => ui('validation.file_required'),
            'file.uploaded' => ui('validation.file_uploaded'),
            'file.max' => ui('validation.file_max', ['max_mb' => intdiv((int) config('documents.max_kilobytes'), 1024)]),
            'file.mimes' => ui('validation.file_mimes'),
            'file.mimetypes' => ui('validation.file_mimes'),
            'title.max' => ui('validation.title_max'),
        ];
    }
}
