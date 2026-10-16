import { Link } from "@inertiajs/react";
import { UserMenu } from "./UserMenu";

export function DesktopTopbar({ title }: { title: string }) {
    return (
        <header className="app-topbar d-none d-lg-flex">
            <nav aria-label="مسار الصفحة" className="app-breadcrumb">
                <Link href="/" aria-label="الرئيسية">
                    <i className="bi bi-house-door" aria-hidden="true" />
                </Link>
                <i className="bi bi-chevron-left" aria-hidden="true" />
                <span aria-current="page">{title}</span>
            </nav>
            <div className="app-topbar__actions">
                <span className="app-workspace-label">
                    <i className="bi bi-grid-1x2" aria-hidden="true" /> مساحة
                    عمل بيكون
                </span>
                <UserMenu />
            </div>
        </header>
    );
}
