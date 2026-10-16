import { Link, usePage } from "@inertiajs/react";
import type { NavItem } from "./navigation";

type SoonButtonProps = {
    item: NavItem;
    className: string;
    soonClassName?: string;
};

/**
 * Renders a real navigation link when the module is available (item.href),
 * otherwise a disabled "coming soon" button.
 */
export function SoonButton({ item, className, soonClassName = "app-soon" }: SoonButtonProps) {
    const { url } = usePage();

    if (item.href) {
        const path = url.split("?")[0] ?? "";
        const active = path === item.href || path.includes(`/${item.key}`);

        return (
            <Link
                href={item.href}
                className={active ? `${className} is-active` : className}
                aria-current={active ? "page" : undefined}
            >
                <i className={`bi ${item.icon}`} aria-hidden="true" />
                <span>{item.label}</span>
            </Link>
        );
    }

    return (
        <button
            type="button"
            className={className}
            aria-disabled="true"
            onClick={(event) => {
                event.preventDefault();
            }}
        >
            <i className={`bi ${item.icon}`} aria-hidden="true" />
            <span>{item.label}</span>
            <span className={soonClassName}>قريباً</span>
        </button>
    );
}
