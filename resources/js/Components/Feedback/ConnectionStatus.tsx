import { useOnlineStatus } from "../../hooks/useOnlineStatus";

/** Sticky banner shown only when the browser reports no connection (§47). Text + icon, never icon alone. */
export function ConnectionStatus() {
    const online = useOnlineStatus();
    if (online) return null;

    return (
        <div
            className="alert alert-warning d-flex align-items-center gap-2 mb-0 rounded-0 app-connection-status"
            role="status"
            aria-live="polite"
        >
            <i className="bi bi-wifi-off" aria-hidden="true" />
            <span>
                لا يوجد اتصال بالإنترنت. بعض الإجراءات تحتاج اتصالاً، مثل
                المساعد والترحيل النهائي والإحالة.
            </span>
        </div>
    );
}
