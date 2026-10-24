import { Link, usePage } from '@inertiajs/react';
import { useOnlineStatus } from '../../hooks/useOnlineStatus';
import { syncOffline, useOffline, type OfflineState } from '../../lib/offline';
export function OfflinePreparationStatus({ status }: { status: OfflineState }) {
    const progress = status.preparation;
    if (status.preparing) return <span className="d-flex flex-column gap-1" data-testid="offline-preparing">
        <span>جارٍ حفظ الواجهة والصفحات تلقائياً للاستخدام دون إنترنت…</span>
        {progress?.total ? <><span className="small">حُفظت {progress.saved} صفحة · فحص {progress.processed} من {progress.total}</span><progress aria-label="تقدّم الحفظ للاستخدام دون إنترنت" max={progress.total} value={Math.min(progress.processed, progress.total)} style={{ width: '100%', minWidth: 160 }} /></> : null}
    </span>;
    if (progress?.status === 'interrupted') return <span data-testid="offline-preparation-interrupted">لم يكتمل حفظ كل الصفحات. الصفحات المحفوظة متاحة، وسيُستكمل التجهيز تلقائياً عند عودة الاتصال.</span>;
    if (status.ready && progress?.status === 'complete') return <span data-testid="offline-prepared">الواجهة والصفحات المحفوظة جاهزة للاستخدام دون إنترنت.</span>;
    return null;
}
export function ConnectionStatus() {
    const online = useOnlineStatus();
    const status = useOffline();
    const { offline, errors } = usePage().props;
    return <div className={`alert ${!online || offline?.cached ? 'alert-warning' : 'alert-light'} d-flex flex-wrap align-items-center gap-2 mb-0 rounded-0 app-connection-status d-print-none`} role="status" aria-live="polite">
        {errors.offline ? <span className="text-danger">{errors.offline}</span> : null}
        <i className={`bi ${online ? 'bi-cloud-check' : 'bi-wifi-off'}`} aria-hidden="true" />
        <span>{!online ? status.ready ? 'تعمل دون اتصال. تُحفظ الطلبات على الجهاز وتُرسل تلقائياً عند عودة الاتصال.' : 'لا يوجد اتصال. جهّز التطبيق وأنت متصل أولاً.' : status.queue.length ? `${status.queue.length} طلب بانتظار المزامنة.` : status.preparing || status.preparation ? '' : 'العمل دون اتصال والمزامنة'}</span>
        <OfflinePreparationStatus status={status} />
        {offline?.cached_at ? <span className="small">نسخة محفوظة: {new Date(Number(offline.cached_at)).toLocaleString()} · لا تشمل الطلبات غير المرسلة.</span> : null}
        <Link href="/offline" className="btn btn-sm btn-outline-secondary ms-auto">الصفحات والطلبات المحفوظة</Link>
        {online && status.queue.length ? <button type="button" className="btn btn-sm btn-primary" disabled={status.busy} onClick={() => void syncOffline()}>{status.busy ? 'جارٍ الإرسال…' : 'مزامنة الآن'}</button> : null}
    </div>;
}
