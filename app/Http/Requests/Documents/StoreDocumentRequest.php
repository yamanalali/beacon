<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Models\Enterprise;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tenant isolation: authorize BEFORE validation so another enterprise gets 403, not validation feedback.
        $enterprise = $this->route('enterprise');

        return $this->user() !== null
            && $enterprise instanceof Enterprise
            && $this->user()->can('upload', [Document::class, $enterprise]);
    }

    /**
     * TODO: BUSINESS DECISION REQUIRED
     * Document types/categories and retention rules are not yet defined.
     *
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
            'transaction' => ['nullable', 'string', 'size:26'],
            'extract' => ['sometimes', 'boolean'],
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
