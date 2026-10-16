<?php

namespace App\Services\Operations;

use App\Models\BackupRun;
use App\Models\User;
use App\Support\DataControl;

/**
 * Section 53: shows, for the technical administrator, whether each mandatory security/data-protection
 * function is configured in THIS environment. It never exposes secret values.
 * Status: ok | warning | pending_decision.
 */
class SecurityChecklist
{
    /** @return list<array{key:string,label_ar:string,status:string,detail_ar:string}> */
    public function items(): array
    {
        $production = app()->environment('production');
        $lastBackup = BackupRun::query()->where('type', BackupRun::TYPE_BACKUP)->where('status', BackupRun::STATUS_SUCCEEDED)->latest('id')->first();
        $lastRestore = BackupRun::query()->where('type', BackupRun::TYPE_RESTORE_TEST)->latest('id')->first();
        $staleHours = (int) config('operations.backup.stale_after_hours');
        $docDisk = config('documents.disk');
        $docDiskPublic = config("filesystems.disks.$docDisk.visibility") === 'public' || $docDisk === 'public';

        return [
            $this->item('debug_off', 'وضع التصحيح مغلق', ! config('app.debug') || ! $production, config('app.debug') ? 'APP_DEBUG مفعّل؛ يجب إغلاقه في الإنتاج.' : 'مغلق.'),
            $this->item('https', 'النقل المشفّر (HTTPS)', (bool) config('operations.force_https') && str_starts_with((string) config('app.url'), 'https://'), 'فعّل BEACON_FORCE_HTTPS واجعل APP_URL يبدأ بـ https.'),
            $this->item('secure_cookie', 'ملفات تعريف الجلسة الآمنة', (bool) config('session.secure') && config('session.http_only', true), 'فعّل SESSION_SECURE_COOKIE.'),
            $this->item('session_encrypt', 'تشفير بيانات الجلسة', (bool) config('session.encrypt'), 'فعّل SESSION_ENCRYPT.'),
            $this->item('session_lifetime', 'مدة الجلسة', true, 'مدة الجلسة الحالية '.config('session.lifetime').' دقيقة.'),
            $this->item('private_documents', 'تخزين المستندات بشكل خاص', ! $docDiskPublic, $docDiskPublic ? 'قرص المستندات عام!' : 'القرص: '.$docDisk.' (خاص).'),
            $this->item('app_key', 'إدارة الأسرار', config('app.key') !== null && config('app.key') !== '', 'الأسرار في متغيرات البيئة فقط، ولا تظهر هنا.'),
            $this->item('backup_key', 'مفتاح منفصل لتشفير النسخ الاحتياطية', config('operations.backup.key') !== null, 'يُفضّل ضبط BEACON_BACKUP_KEY وحفظه خارج مكان النسخ.'),
            $this->item('backup_enabled', 'النسخ الاحتياطي اليومي', (bool) config('operations.backup.enabled'), 'الموعد اليومي: '.config('operations.backup.time')),
            $this->item('backup_recent', 'آخر نسخة احتياطية ناجحة', $lastBackup !== null && $lastBackup->finished_at->gt(now()->subHours($staleHours)), $lastBackup ? 'آخر نسخة: '.$lastBackup->finished_at->toDateTimeString() : 'لا توجد نسخة ناجحة بعد.'),
            $this->item('restore_tested', 'اختبار الاستعادة', $lastRestore?->status === BackupRun::STATUS_SUCCEEDED, $lastRestore ? 'آخر اختبار: '.(BackupRun::STATUS_LABELS[$lastRestore->status] ?? '') : 'لم يُجرَ اختبار استعادة بعد. تمرين الاستعادة في بيئة منفصلة مطلوب قبل القبول النهائي.'),
            ['key' => 'backup_retention', 'label_ar' => 'مدة الاحتفاظ بالنسخ', 'status' => config('operations.backup.retention_days') === null ? 'pending_decision' : 'ok', 'detail_ar' => config('operations.backup.retention_days') === null ? 'بانتظار قرار بيكون.' : config('operations.backup.retention_days').' يوماً.'],
            $this->adminMfaItem(),
            ['key' => 'retention_procedures', 'label_ar' => 'إجراءات الاحتفاظ والتصدير والحذف', 'status' => 'pending_decision', 'detail_ar' => 'موثّقة في docs/operations/data-protection.md وبانتظار اعتماد المدد من بيكون.'],
            ['key' => 'third_party', 'label_ar' => 'مشاركة البيانات مع أطراف ثالثة', 'status' => 'ok', 'detail_ar' => 'المزوّدون المعتمدون: '.collect(DataControl::registry())->where('approved', true)->count().'. لا تُرسل البيانات إلا لمزوّد معتمد باتفاقية معالجة بيانات.'],
        ];
    }

    /**
     * Admin MFA (TOTP, config/mfa.php). Reports only whether it is switched on and how many
     * admins have confirmed enrollment (a count; never secrets). The policy is still open, so the
     * status stays pending_decision. TODO: BUSINESS DECISION REQUIRED - mandatory roles, factor
     * types, grace period, lost-device recovery.
     */
    private function adminMfaItem(): array
    {
        $roles = (array) config('mfa.roles', []);
        $admins = User::query()->whereIn('role', $roles);
        $total = (clone $admins)->count();
        $enrolled = (clone $admins)->whereNotNull('two_factor_secret')->whereNotNull('two_factor_confirmed_at')->count();

        $state = config('mfa.enabled')
            ? "مفعّلة (BEACON_ADMIN_MFA=true). المسجّلون: {$enrolled} من {$total} مسؤول."
            : 'غير مفعّلة (BEACON_ADMIN_MFA=false)، وتسجيل الدخول يعمل بدونها.';

        return [
            'key' => 'admin_mfa',
            'label_ar' => 'المصادقة الثنائية للمسؤولين',
            'status' => 'pending_decision',
            'detail_ar' => $state.' بانتظار قرار السياسة: الأدوار الإلزامية، نوع العامل، فترة السماح، واسترداد الجهاز المفقود.',
            'mfa_enabled' => (bool) config('mfa.enabled'),
            'mfa_enrolled_admins' => $enrolled,
            'mfa_total_admins' => $total,
        ];
    }

    private function item(string $key, string $label, bool $ok, string $detail): array
    {
        return ['key' => $key, 'label_ar' => $label, 'status' => $ok ? 'ok' : 'warning', 'detail_ar' => $detail];
    }
}
