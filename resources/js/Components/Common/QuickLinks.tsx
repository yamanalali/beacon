import { Link } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";

export function QuickLinks({
    items,
}: {
    items: { href: string; label: string; description: string; icon: string }[];
}) {
    const t = useT();
    return (
        <nav className="app-quick-links" aria-label={t("common.quick_links", undefined, "الوصول السريع")}>
            {items.map((item) => (
                <Link
                    key={item.href}
                    href={item.href}
                    className="app-quick-link"
                >
                    <span className="app-quick-link__icon">
                        <i className={`bi ${item.icon}`} aria-hidden="true" />
                    </span>
                    <span>
                        <strong>{item.label}</strong>
                        <small>{item.description}</small>
                    </span>
                    <i className="bi bi-arrow-up-left" aria-hidden="true" />
                </Link>
            ))}
        </nav>
    );
}
