import { Link, usePage } from '@inertiajs/react';
import { useT } from '../../Hooks/useT';
import { useOnlineStatus } from '../../hooks/useOnlineStatus';
import { useOffline, type OfflineState } from '../../lib/offline';

export function OfflinePreparationStatus({ status }: { status: OfflineState }) {
    const t = useT();
    const progress = status.preparation;
    if (status.preparing) return <span className="d-flex flex-column gap-1" data-testid="offline-preparing">
        <span>{t("offline.preparing", undefined, "جارٍ حفظ الواجهة والصفحات تلقائياً للاستخدام دون إنترنت…")}</span>
        {progress?.total ? <><span className="small">{t("offline.preparing_progress", { saved: progress.saved, processed: progress.processed, total: progress.total }, `حُفظت ${progress.saved} صفحة · فحص ${progress.processed} من ${progress.total}`)}</span><progress aria-label={t("offline.preparing_progress_aria", undefined, "تقدّم الحفظ للاستخدام دون إنترنت")} max={progress.total} value={Math.min(progress.processed, progress.total)} style={{ width: '100%', minWidth: 160 }} /></> : null}
    </span>;
    if (progress?.status === 'interrupted') return <span data-testid="offline-preparation-interrupted">{t("offline.preparation_interrupted", undefined, "لم يكتمل حفظ كل الصفحات. الصفحات المحفوظة متاحة، وسيُستكمل التجهيز تلقائياً عند عودة الاتصال.")}</span>;
    if (status.ready && progress?.status === 'complete') return <span data-testid="offline-prepared">{t("offline.prepared", undefined, "الواجهة والصفحات المحفوظة جاهزة للاستخدام دون إنترنت.")}</span>;
    return null;
}

type ConnectionTone = 'green' | 'orange' | 'red';

function connectionTone(online: boolean, status: OfflineState, cached: boolean, hasError: boolean): ConnectionTone {
    if (hasError || status.error || (!online && !status.ready)) {
        return 'red';
    }
    if (!online || cached || status.queue.length > 0 || status.preparing || status.busy || status.preparation?.status === 'interrupted') {
        return 'orange';
    }
    return 'green';
}

function connectionLabel(tone: ConnectionTone, online: boolean, status: OfflineState, cached: boolean, error: string | null, t: ReturnType<typeof useT>): string {
    if (error) {
        return error;
    }
    if (status.error) {
        return status.error;
    }
    if (!online) {
        return status.ready
            ? t("connection.offline_ready", undefined, 'تعمل دون اتصال. اضغط لعرض الصفحات والطلبات المحفوظة.')
            : t("connection.offline_not_ready", undefined, 'لا يوجد اتصال. جهّز التطبيق وأنت متصل أولاً.');
    }
    if (status.queue.length) {
        return t("connection.queue_pending", { count: status.queue.length }, `${status.queue.length} طلب بانتظار المزامنة. اضغط للتفاصيل.`);
    }
    if (status.preparing) {
        return t("connection.preparing", undefined, 'جارٍ تجهيز العمل دون اتصال.');
    }
    if (cached) {
        return t("connection.cached_view", undefined, 'تعرض نسخة محفوظة دون اتصال.');
    }
    if (tone === 'green') {
        return t("connection.online_ready", undefined, 'متصل. العمل دون اتصال جاهز.');
    }
    return t("connection.offline_sync", undefined, 'العمل دون اتصال والمزامنة');
}

/** Compact online/offline indicator — green / orange / red circle linking to /offline. */
export function ConnectionStatus() {
    const t = useT();
    const online = useOnlineStatus();
    const status = useOffline();
    const { offline, errors } = usePage().props;
    const error = typeof errors?.offline === 'string' ? errors.offline : null;
    const tone = connectionTone(online, status, Boolean(offline?.cached), Boolean(error));
    const label = connectionLabel(tone, online, status, Boolean(offline?.cached), error, t);

    return (
        <Link
            href="/offline"
            className={`app-connection-dot app-connection-dot--${tone} d-print-none`}
            aria-label={label}
            title={label}
            data-testid="connection-status-dot"
            data-tone={tone}
        >
            <span className="visually-hidden">{label}</span>
        </Link>
    );
}
