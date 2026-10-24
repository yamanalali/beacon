<?php

namespace App\Support;

use App\Actions\Audit\RecordAuditLog;
use Illuminate\Support\Str;

/** Readable audit evidence; the stored, immutable context is never changed. */
class AuditContextPresenter
{
    private const LABELS = [
        'before' => 'قبل التغيير', 'after' => 'بعد التغيير', 'role' => 'الدور',
        'name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'title' => 'العنوان',
        'permissions' => 'الصلاحيات', 'is_active' => 'الحساب فعال', 'enabled' => 'مفعّل',
        'is_default' => 'افتراضي', 'amount' => 'المبلغ', 'currency' => 'العملة',
        'transaction_date' => 'تاريخ العملية', 'payment_status' => 'حالة الدفع',
        'status' => 'الحالة', 'reason' => 'السبب', 'description' => 'الوصف',
        'enterprise_id' => 'رقم المنشأة', 'financial_transaction_id' => 'رقم العملية المالية',
        'document_id' => 'رقم المستند', 'filters' => 'المرشحات', 'rows' => 'عدد السجلات',
        'create_transactions' => 'إضافة العمليات', 'approve_transactions' => 'اعتماد العمليات',
        'export' => 'تصدير البيانات', 'delete_documents' => 'حذف المستندات',
        'upload_documents' => 'رفع المستندات', 'manage_compliance' => 'إدارة الامتثال',
        'version' => 'الإصدار', 'action' => 'الإجراء', 'from' => 'من', 'to' => 'إلى',
    ];

    private const VALUES = [
        'beacon_admin' => 'مدير بيكون', 'beacon_advisor' => 'مستشار بيكون',
        'technical_admin' => 'المدير التقني', 'sme_user' => 'مستخدم المنشأة',
        'mfi_user' => 'مستخدم مؤسسة التمويل', 'paid' => 'مدفوع',
        'unpaid' => 'غير مدفوع', 'partially_paid' => 'مدفوع جزئياً',
    ];

    /** @return list<array{label: string, value: string}> */
    public static function rows(array $context): array
    {
        return self::flatten(RecordAuditLog::withoutSecrets($context));
    }

    private static function flatten(array $context, string $prefix = ''): array
    {
        $rows = [];
        foreach ($context as $key => $value) {
            $name = is_int($key) ? 'البند '.($key + 1) : (self::LABELS[$key] ?? Str::headline($key));
            $label = $prefix === '' ? $name : $prefix.' / '.$name;
            if (is_array($value) && $value !== []) {
                array_push($rows, ...self::flatten($value, $label));

                continue;
            }
            $text = match (true) {
                $value === null, $value === '', $value === [] => 'غير محدد',
                is_bool($value) => $value ? 'نعم' : 'لا',
                is_string($value) && in_array($key, ['role', 'payment_status'], true) => self::VALUES[$value] ?? $value,
                default => (string) $value,
            };
            $rows[] = ['label' => $label, 'value' => $text];
        }

        return $rows;
    }
}
