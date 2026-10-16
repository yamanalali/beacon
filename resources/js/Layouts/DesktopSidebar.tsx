import { Link, usePage } from "@inertiajs/react";
import { Brand } from "../Components/Common/Brand";
import { SoonButton } from "./SoonButton";
import { homeItem, isHomePath, workspaceGroups } from "./navigation";
import { roleLabels } from "./navigation";

export function DesktopSidebar() {
    const { url, props } = usePage();
    const role = props.auth.user?.role;
    const enterprise = props.enterprise as { public_id: string } | undefined;
    const groups = workspaceGroups(role, enterprise?.public_id);
    const homeActive = isHomePath(url);
    return (
        <aside className="app-sidebar d-none d-lg-flex flex-column">
            <Link href="/" className="app-brand" aria-label="بيكون، الرئيسية">
                <Brand />
            </Link>
            <div className="app-workspace">
                <span className="app-workspace__icon">
                    <i className="bi bi-grid-1x2" aria-hidden="true" />
                </span>
                <span>
                    <strong>مساحة عمل بيكون</strong>
                    <small>{roleLabels[role ?? ""] ?? "المنصة"}</small>
                </span>
            </div>
            <nav aria-label="التنقل الرئيسي" className="app-sidebar__nav">
                <ul className="app-nav-list">
                    <li>
                        <Link
                            href="/"
                            className={`app-side-link${homeActive ? " is-active" : ""}`}
                            aria-current={homeActive ? "page" : undefined}
                        >
                            <i
                                className={`bi ${homeItem.icon}`}
                                aria-hidden="true"
                            />
                            <span>{homeItem.label}</span>
                        </Link>
                    </li>
                </ul>
                {groups.map((group) => (
                    <section
                        key={group.label}
                        className="app-nav-group"
                        aria-label={group.label}
                    >
                        <p className="app-nav-group__label">{group.label}</p>
                        <ul className="app-nav-list">
                            {group.items.map((item) => (
                                <li key={item.key}>
                                    <SoonButton
                                        item={item}
                                        className="app-side-link"
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </nav>
            <div className="app-sidebar__footer">
                <i className="bi bi-intersect" aria-hidden="true" />
                <span>
                    بيكون لإدارة وتطوير الأعمال
                    <small lang="en">YOUR NEXT STEP FORWARD</small>
                </span>
            </div>
        </aside>
    );
}
