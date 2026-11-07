import { useT } from "../../Hooks/useT";

/** The four states §47 requires the UI to distinguish. */
export type SyncState = "local_draft" | "pending_sync" | "synced" | "conflict";

export function SyncStatusBadge({ state }: { state: SyncState }) {
    const t = useT();
    const map: Record<SyncState, { key: string; icon: string; cls: string; fb: string }> = {
        local_draft: { key: "sync.local_draft", icon: "bi-phone", cls: "text-bg-secondary", fb: "مسودة على الجهاز" },
        pending_sync: { key: "sync.pending", icon: "bi-cloud-arrow-up", cls: "text-bg-warning", fb: "بانتظار المزامنة" },
        synced: { key: "sync.synced", icon: "bi-cloud-check", cls: "text-bg-success", fb: "تم الحفظ على الخادم" },
        conflict: { key: "sync.conflict", icon: "bi-exclamation-octagon", cls: "text-bg-danger", fb: "تعارض يحتاج مراجعة" },
    };
    const s = map[state];
    return (
        <span className={`badge ${s.cls} d-inline-flex align-items-center gap-1`} role="status">
            <i className={`bi ${s.icon}`} aria-hidden="true" />
            {t(s.key, undefined, s.fb)}
        </span>
    );
}
