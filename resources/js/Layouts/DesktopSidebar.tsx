import { Link, usePage } from "@inertiajs/react";
import { Brand } from "../Components/Common/Brand";
import { homeItem } from "./navigation";
import { groupNavigation, homeLink, iconFor, navigationOrFallback } from "./navigationModel";

export function DesktopSidebar() {
    const { url, props } = usePage();
    const dir = props.dir ?? "rtl";
    // Links come from the server (`auth.navigation`); no role logic in the UI.
    const items = navigationOrFallback(props.auth.navigation, url, props.auth.can);
    const groups = groupNavigation(items);
    const home = homeLink(items, url);
    const homeActive = home.active;
    return (
        <aside className="app-sidebar d-none d-lg-flex flex-column" dir={dir}>
            <Link href="/" className="app-brand" aria-label="بيكون، الرئيسية">
                <Brand />
            </Link>
            <nav aria-label="التنقل الرئيسي" className="app-sidebar__nav">
                <ul className="app-nav-list">
                    <li>
                        <Link
                            href={home.href}
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
                        key={group.key}
                        className="app-nav-group"
                        aria-label={group.label ?? undefined}
                    >
                        {group.label ? (
                            <p className="app-nav-group__label">{group.label}</p>
                        ) : null}
                        <ul className="app-nav-list">
                            {group.items.map((item) => (
                                <li key={item.key}>
                                    <Link
                                        href={item.href}
                                        className={`app-side-link${item.active ? " is-active" : ""}`}
                                        aria-current={item.active ? "page" : undefined}
                                    >
                                        <i className={`bi ${iconFor(item)}`} aria-hidden="true" />
                                        <span>{item.label}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </nav>
        </aside>
    );
}
