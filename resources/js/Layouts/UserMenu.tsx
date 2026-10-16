import { Link, usePage } from "@inertiajs/react";

/** Profile menu (no Bootstrap JS needed): account link + logout via Inertia POST. */
export function UserMenu({ compact = false }: { compact?: boolean }) {
    const { auth } = usePage().props;
    const name = auth.user?.name.trim() || "زائر";

    return (
        <details className="app-user-menu position-relative">
            <summary className={compact ? "app-icon-button" : "app-profile"} aria-label={`قائمة الحساب، ${name}`} style={{ listStyle: "none", cursor: "pointer" }}>
                <i className={`bi ${compact ? "bi-person" : "bi-person-circle"}`} aria-hidden="true" />
                {compact ? null : <span className="app-profile__name">{name}</span>}
            </summary>
            <div className="position-absolute bg-white border rounded-3 shadow-sm py-1 mt-1" style={{ insetInlineEnd: 0, minWidth: 200, zIndex: 1050 }}>
                {compact ? <div className="px-3 py-2 small text-body-secondary border-bottom">{name}</div> : null}
                <Link href="/account" className="dropdown-item d-flex align-items-center gap-2 px-3" style={{ minHeight: 44 }}>
                    <i className="bi bi-person-gear" aria-hidden="true" />حسابي
                </Link>
                <Link href="/logout" method="post" as="button" className="dropdown-item d-flex align-items-center gap-2 px-3 text-danger" style={{ minHeight: 44 }}>
                    <i className="bi bi-box-arrow-left" aria-hidden="true" />تسجيل الخروج
                </Link>
            </div>
        </details>
    );
}