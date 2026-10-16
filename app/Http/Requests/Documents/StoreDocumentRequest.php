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
            'file.required' => 'اختر ملفاً لرفعه.',
            'file.uploaded' => 'تعذّر رفع الملف. حاول مرة أخرى.',
            'file.max' => 'حجم الملف كبير. الحد الأقصى '.intdiv((int) config('documents.max_kilobytes'), 1024).' ميغابايت.',
            'file.mimes' => 'نوع الملف غير مسموح. المسموح: صور (JPG, PNG, WEBP) أو PDF.',
            'file.mimetypes' => 'نوع الملف غير مسموح. المسموح: صور (JPG, PNG, WEBP) أو PDF.',
            'title.max' => 'العنوان طويل جداً.',
        ];
    }
}
