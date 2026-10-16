/** The four states §47 requires the UI to distinguish. */
export type SyncState = "local_draft" | "pending_sync" | "synced" | "conflict";

const MAP: Record<SyncState, { label: string; icon: string; cls: string }> = {
    local_draft: { label: "مسودة على الجهاز", icon: "bi-phone", cls: "text-bg-secondary" },
    pending_sync: { label: "بانتظار المزامنة", icon: "bi-cloud-arrow-up", cls: "text-bg-warning" },
    synced: { label: "تم الحفظ على الخادم", icon: "bi-cloud-check", cls: "text-bg-success" },
    conflict: { label: "تعارض يحتاج مراجعة", icon: "bi-exclamation-octagon", cls: "text-bg-danger" },
};

export function SyncStatusBadge({ state }: { state: SyncState }) {
    const s = MAP[state];
    return (
        <span className={`badge ${s.cls} d-inline-flex align-items-center gap-1`} role="status">
            <i className={`bi ${s.icon}`} aria-hidden="true" />
            {s.label}
        </span>
    );
}