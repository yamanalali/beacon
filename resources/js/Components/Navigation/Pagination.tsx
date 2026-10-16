import { Link } from "@inertiajs/react";

export type PaginationLink = { url: string | null; label: string; active: boolean };

function cleanLabel(label: string): string {
    if (label.includes("Previous") || label.includes("&laquo;")) return "السابق";
    if (label.includes("Next") || label.includes("&raquo;")) return "التالي";
    return label;
}

export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="الصفحات" className="mt-3">
            <ul className="pagination flex-wrap mb-0">
                {links.map((link, index) => (
                    <li key={index} className={`page-item${link.active ? " active" : ""}${link.url ? "" : " disabled"}`}>
                        {link.url ? (
                            <Link href={link.url} className="page-link" preserveScroll style={{ minHeight: "44px", minWidth: "44px" }}>
                                {cleanLabel(link.label)}
                            </Link>
                        ) : (
                            <span className="page-link" style={{ minHeight: "44px" }}>
                                {cleanLabel(link.label)}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </nav>
    );
}
