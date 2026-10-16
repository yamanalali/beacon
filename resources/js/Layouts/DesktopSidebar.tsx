import { Link, usePage } from "@inertiajs/react";
import { SoonButton } from "./SoonButton";
import { desktopGroups, desktopSingleItems, homeItem, isHomePath } from "./navigation";

export function DesktopSidebar() {
    const { url } = usePage();
    const homeActive = isHomePath(url);

    return (
        <aside className="app-sidebar d-none d-lg-flex flex-column">
            <div className="app-brand">
                <span className="app-brand__mark">بيكون</span>
            </div>
            <nav aria-label="التنقل الرئيسي">
                <ul className="app-nav-list">
                    <li>
                        <Link
                            href="/"
                            className={homeActive ? "app-side-link is-active" : "app-side-link"}
                            aria-current={homeActive ? "page" : undefined}
                        >
                            <i className={`bi ${homeItem.icon}`} aria-hidden="true" />
                            <span>{homeItem.label}</span>
                        </Link>
                    </li>
                </ul>

                {desktopGroups.map((group) => (
                    <section key={group.label} className="app-nav-group" aria-label={group.label}>
                        <p className="app-nav-group__label">{group.label}</p>
                        <ul className="app-nav-list">
                            {group.items.map((item) => (
                                <li key={item.key}>
                                    <SoonButton item={item} className="app-side-link" />
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}

                <ul className="app-nav-list app-nav-group">
                    {desktopSingleItems.map((item) => (
                        <li key={item.key}>
                            <SoonButton item={item} className="app-side-link" />
                        </li>
                    ))}
                </ul>
            </nav>
        </aside>
    );
}
