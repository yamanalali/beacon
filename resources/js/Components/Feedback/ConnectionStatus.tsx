import { Link, usePage } from '@inertiajs/react';
import { useOnlineStatus } from '../../hooks/useOnlineStatus';
import { syncOffline, useOffline } from '../../lib/offline';
export function ConnectionStatus() {
    const online = useOnlineStatus();
    const status = useOffline();
    const { offline, errors } = usePage().props;
    return <div className={`alert ${!online || offline?.cached ? 'alert-warning' : 'alert-light'} d-flex flex-wrap align-items-center gap-2 mb-0 rounded-0 app-connection-status d-print-none`} role="status" aria-live="polite">
        {errors.offline ? <span className="text-danger">{errors.offline}</span> : null}
        <i className={`bi ${online ? 'bi-cloud-check' : 'bi-wifi-off'}`} aria-hidden="true" />
        <span>{!online ? status.ready ? 'تعمل دون اتصال. تُحفظ الطلبات على الجهاز وتُرسل تلقائياً عند عودة الاتصال.' : 'لا يوجد اتصال. جهّز التطبيق وأنت متصل أولاً.' : status.queue.length ? `${status.queue.length} طلب بانتظار المزامنة.` : 'العمل دون اتصال والمزامنة'}</span>
        {offline?.cached_at ? <span className="small">نسخة محفوظة: {new Date(Number(offline.cached_at)).toLocaleString()} · لا تشمل الطلبات غير المرسلة.</span> : null}
        <Link href="/offline" className="btn btn-sm btn-outline-secondary ms-auto">الصفحات والطلبات المحفوظة</Link>
        {online && status.queue.length ? <button type="button" className="btn btn-sm btn-primary" disabled={status.busy} onClick={() => void syncOffline()}>{status.busy ? 'جارٍ الإرسال…' : 'مزامنة الآن'}</button> : null}
    </div>;
}
