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
            'file.required' => 'اختر ملفاً لرفعه.',
            'file.uploaded' => 'تعذّر رفع الملف. حاول مرة أخرى.',
            'file.max' => 'حجم الملف كبير. الحد الأقصى '.intdiv((int) config('documents.max_kilobytes'), 1024).' ميغابايت.',
            'file.mimes' => 'نوع الملف غير مسموح. المسموح: صور (JPG, PNG, WEBP) أو PDF.',
            'file.mimetypes' => 'نوع الملف غير مسموح. المسموح: صور (JPG, PNG, WEBP) أو PDF.',
            'title.max' => 'العنوان طويل جداً.',
        ];
    }
}
