/** Matches Laravel's paginator `links` array (LengthAwarePaginator::toArray()). */
export type PaginationLink = { url: string | null; label: string; active: boolean };

import { Link } from "@inertiajs/react";

function clean(label: string): string {
    if (label.includes("Previous") || label.includes("pagination.previous")) return "السابق";
    if (label.includes("Next") || label.includes("pagination.next")) return "التالي";
    return label.replace(/&[a-z]+;/g, "").trim();
}

export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;
    return (
        <nav aria-label="التنقل بين الصفحات" className="mt-3">
            <ul className="pagination flex-wrap justify-content-center mb-0">
                {links.map((l, i) => (
                    <li key={i} className={`page-item ${l.active ? "active" : ""} ${l.url ? "" : "disabled"}`}>
                        {l.url ? (
                            <Link href={l.url} preserveScroll preserveState className="page-link" style={{ minWidth: 44, minHeight: 44, display: "flex", alignItems: "center", justifyContent: "center" }} aria-current={l.active ? "page" : undefined}>
                                {clean(l.label)}
                            </Link>
                        ) : (
                            <span className="page-link" style={{ minHeight: 44, display: "flex", alignItems: "center" }}>{clean(l.label)}</span>
                        )}
                    </li>
                ))}
            </ul>
        </nav>
    );
}