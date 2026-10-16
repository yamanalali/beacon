import { router } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import { EmptyState } from "../Feedback/EmptyState";
import { formatNumber, type NumberFormat } from "./format";
import "../../../css/datatable.css";

export type Column<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    /** Numeric/date/code columns render LTR digits aligned to the end. */
    numeric?: boolean;
    /** Hide in the mobile card view (keep cards short). */
    hideOnMobile?: boolean;
    /** Use as the card title on mobile. */
    primary?: boolean;
    /** Allow sorting by this column. Client mode sorts with sortValue; server mode sends `key`. */
    sortable?: boolean;
    /** Value used for client sort (defaults to `row[key]`). */
    sortValue?: (row: T) => string | number | null | undefined;
    /** Text used by client global search (defaults to `row[key]`). */
    searchValue?: (row: T) => string;
    /** Can the user hide this column? Default true; the primary column is never hideable. */
    hideable?: boolean;
    /** Start hidden (user can show it from the columns menu). */
    defaultHidden?: boolean;
    /** Format `row[key]` with thousands separators when no custom cell output is needed. */
    format?: NumberFormat;
};

export type SortDirection = "asc" | "desc";
export type Density = "comfortable" | "compact";

export type BulkAction<T> = {
    label: string;
    icon?: string;
    tone?: "primary" | "danger" | "secondary";
    onClick: (rows: T[]) => void;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };
type PaginatorMeta = { current_page: number; last_page: number; per_page?: number; total: number; from: number | null; to: number | null; links?: PaginatorLink[] };
/** Laravel `paginate()` JSON, or an API resource collection with `meta`/`links`. */
export type Paginator<T> =
    | ({ data: T[]; links: PaginatorLink[] } & PaginatorMeta)
    | { data: T[]; meta: PaginatorMeta; links: Record<string, string | null> };

export type ServerOptions<T> = {
    /** The full paginator object from the controller. `rows` may be omitted when this is set. */
    paginator?: Paginator<T>;
    /** Or pass meta/links separately. */
    meta?: PaginatorMeta;
    /** Current query state, echoed back by the controller (e.g. `filters`). */
    sort?: string | null;
    direction?: SortDirection | null;
    search?: string | null;
    /** Target URL; defaults to the current path. */
    url?: string;
    /** Extra query params to keep (e.g. active filters). */
    query?: Record<string, string | number | boolean | null | undefined>;
    /** Inertia partial reload props. */
    only?: string[];
    /** Query param names. */
    params?: { sort?: string; direction?: string; search?: string; page?: string };
    /** Debounce for search, ms. */
    debounce?: number;
};

export type DataTableProps<T> = {
    columns: Column<T>[];
    rows?: T[];
    rowKey: (row: T) => string | number;
    caption?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    emptyActionLabel?: string;
    onEmptyAction?: () => void;
    onRowClick?: (row: T) => void;
    /** Accessible name for a clickable row, e.g. "عرض المعاملة 12". */
    rowLabel?: (row: T) => string;
    /** Show global search box. */
    searchable?: boolean;
    searchPlaceholder?: string;
    /** Row checkboxes + bulk action bar. */
    selectable?: boolean;
    bulkActions?: BulkAction<T>[];
    /** Show skeleton rows. */
    loading?: boolean;
    /** Toolbar toggles (default on when the toolbar shows). */
    columnToggle?: boolean;
    densityToggle?: boolean;
    defaultDensity?: Density;
    defaultSort?: { key: string; direction: SortDirection };
    stickyFirstColumn?: boolean;
    /** Max height of the scroll area so the header stays sticky. */
    maxHeight?: string;
    /** Extra toolbar content (export button, filters...). */
    toolbar?: ReactNode;
    /** Server-side mode: sort/search/page go to the controller via Inertia. Client mode is default. */
    server?: ServerOptions<T>;
};

const L = {
    search: "بحث",
    columns: "الأعمدة",
    density: "كثافة العرض",
    compact: "مضغوط",
    comfortable: "مريح",
    selectAll: "تحديد كل الصفوف",
    selectRow: "تحديد الصف",
    selected: (n: number) => `${formatNumber(n, "integer")} محدد`,
    clear: "إلغاء التحديد",
    loading: "جارٍ التحميل",
    prev: "السابق",
    next: "التالي",
    pages: "التنقل بين الصفحات",
    range: (f: number, t: number, total: number) => `${formatNumber(f, "integer")}–${formatNumber(t, "integer")} من ${formatNumber(total, "integer")}`,
    sortAsc: "ترتيب تصاعدي",
    sortDesc: "ترتيب تنازلي",
    noMatch: "لا توجد نتائج مطابقة للبحث",
};

function raw<T>(row: T, key: string): unknown {
    return (row as Record<string, unknown>)?.[key];
}

function normalizeMeta<T>(s?: ServerOptions<T>): PaginatorMeta | undefined {
    if (!s) return undefined;
    if (s.meta) return s.meta;
    const p = s.paginator as (Paginator<T> & Record<string, unknown>) | undefined;
    if (!p) return undefined;
    if ("meta" in p && p.meta) return p.meta as PaginatorMeta;
    return p as unknown as PaginatorMeta;
}

/**
 * Accessible RTL-first data table. Desktop: sticky header, sortable, column toggle, density,
 * selection + bulk actions, search, skeleton, zebra/hover. Mobile: stacked cards (A45).
 * Client mode (default) sorts/searches the given rows; server mode delegates to the controller.
 */
export function DataTable<T>(props: DataTableProps<T>) {
    const {
        columns, rowKey, caption, emptyTitle = "لا توجد بيانات", emptyDescription, emptyActionLabel, onEmptyAction, onRowClick, rowLabel,
        searchable = false, searchPlaceholder = "ابحث…", selectable = false, bulkActions = [], loading = false,
        defaultDensity = "comfortable", defaultSort, stickyFirstColumn = true, maxHeight = "70vh", toolbar, server,
    } = props;
    const sourceRows = props.rows ?? server?.paginator?.data ?? [];
    const isServer = !!server;
    const P = { sort: "sort", direction: "direction", search: "search", page: "page", ...server?.params };
    const meta = normalizeMeta(server);

    const [hidden, setHidden] = useState<Set<string>>(() => new Set(columns.filter((c) => c.defaultHidden).map((c) => c.key)));
    const [density, setDensity] = useState<Density>(defaultDensity);
    const [sort, setSort] = useState<{ key: string; direction: SortDirection } | null>(() =>
        isServer ? (server?.sort ? { key: server.sort, direction: server.direction ?? "asc" } : null) : defaultSort ?? null,
    );
    const [query, setQuery] = useState<string>(isServer ? server?.search ?? "" : "");
    const [selected, setSelected] = useState<Set<string | number>>(new Set());
    const [menuOpen, setMenuOpen] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => () => { if (timer.current) clearTimeout(timer.current); }, []);
    // Drop selections for rows that are no longer present (new page / new search).
    useEffect(() => {
        const keys = new Set(sourceRows.map(rowKey));
        setSelected((s) => { const n = new Set([...s].filter((k) => keys.has(k))); return n.size === s.size ? s : n; });
    }, [sourceRows, rowKey]);

    const visit = (patch: Record<string, string | number | null | undefined>) => {
        if (!server) return;
        const params: Record<string, string | number> = {};
        const merged = {
            ...server.query,
            [P.sort]: sort?.key, [P.direction]: sort?.direction, [P.search]: query || undefined,
            ...patch,
        } as Record<string, unknown>;
        for (const [k, v] of Object.entries(merged)) if (v !== null && v !== undefined && v !== "") params[k] = v as string | number;
        router.get(server.url ?? window.location.pathname, params, {
            preserveState: true, preserveScroll: true, replace: true, ...(server.only ? { only: server.only } : {}),
        });
    };

    const visible = columns.filter((c) => !hidden.has(c.key));
    const primary = columns.find((c) => c.primary) ?? columns[0];

    const rows = useMemo(() => {
        if (isServer) return sourceRows;
        let out = sourceRows;
        const q = query.trim().toLowerCase();
        if (q) {
            out = out.filter((r) =>
                columns.some((c) => String(c.searchValue ? c.searchValue(r) : raw(r, c.key) ?? "").toLowerCase().includes(q)),
            );
        }
        if (sort) {
            const col = columns.find((c) => c.key === sort.key);
            if (col) {
                const val = (r: T) => (col.sortValue ? col.sortValue(r) : (raw(r, col.key) as string | number | null | undefined));
                const dir = sort.direction === "asc" ? 1 : -1;
                out = [...out].sort((a, b) => {
                    const x = val(a), y = val(b);
                    if (x == null && y == null) return 0;
                    if (x == null) return 1;
                    if (y == null) return -1;
                    if (typeof x === "number" && typeof y === "number") return (x - y) * dir;
                    return String(x).localeCompare(String(y), "ar", { numeric: true }) * dir;
                });
            }
        }
        return out;
    }, [isServer, sourceRows, query, sort, columns]);

    const toggleSort = (c: Column<T>) => {
        const next: { key: string; direction: SortDirection } | null =
            sort?.key !== c.key ? { key: c.key, direction: "asc" } : sort.direction === "asc" ? { key: c.key, direction: "desc" } : null;
        setSort(next);
        if (isServer) visit({ [P.sort]: next?.key ?? null, [P.direction]: next?.direction ?? null, [P.page]: null });
    };

    const onSearch = (v: string) => {
        setQuery(v);
        if (!isServer) return;
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(() => visit({ [P.search]: v || null, [P.page]: null }), server?.debounce ?? 350);
    };

    const goPage = (page: number) => visit({ [P.page]: page });

    const allKeys = rows.map(rowKey);
    const allSelected = allKeys.length > 0 && allKeys.every((k) => selected.has(k));
    const someSelected = !allSelected && allKeys.some((k) => selected.has(k));
    const toggleAll = () => setSelected(allSelected ? new Set() : new Set(allKeys));
    const toggleOne = (k: string | number) => setSelected((s) => { const n = new Set(s); n.has(k) ? n.delete(k) : n.add(k); return n; });
    const selectedRows = rows.filter((r) => selected.has(rowKey(r)));

    const content = (c: Column<T>, r: T): ReactNode => {
        const v = c.format ? formatNumber(raw(r, c.key), c.format) : c.cell(r);
        return c.numeric || c.format ? <bdi dir="ltr">{v}</bdi> : v;
    };

    const interactive = (r: T) =>
        onRowClick
            ? {
                  tabIndex: 0,
                  role: "button",
                  "aria-label": rowLabel ? rowLabel(r) : undefined,
                  onClick: () => onRowClick(r),
                  onKeyDown: (e: KeyboardEvent<HTMLElement>) => {
                      if (e.target !== e.currentTarget) return;
                      if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onRowClick(r); }
                  },
                  style: { cursor: "pointer" },
              }
            : {};

    const showColumnToggle = props.columnToggle ?? (searchable || selectable || !!toolbar);
    const showDensity = props.densityToggle ?? showColumnToggle;
    const hasToolbar = searchable || showColumnToggle || showDensity || !!toolbar;
    const sourceEmpty = sourceRows.length === 0 && !query;

    if (!loading && sourceEmpty && !isServer) {
        return <div className="app-dt-empty"><i className="bi bi-inbox" aria-hidden="true" /><EmptyState title={emptyTitle} description={emptyDescription} actionLabel={emptyActionLabel} onAction={onEmptyAction} /></div>;
    }

    const stickyCls = (i: number) => (stickyFirstColumn && i === 0 ? ` app-dt-sticky${selectable ? " app-dt-sticky-offset" : ""}` : "");

    return (
        <div className={`app-dt app-dt-${density}`}>
            {hasToolbar ? (
                <div className="app-dt-toolbar">
                    {searchable ? (
                        <div className="app-dt-search">
                            <i className="bi bi-search" aria-hidden="true" />
                            <input type="search" className="form-control" value={query} onChange={(e) => onSearch(e.target.value)}
                                placeholder={searchPlaceholder} aria-label={L.search} />
                        </div>
                    ) : null}
                    <div className="app-dt-tools">
                        {toolbar}
                        {showDensity ? (
                            <button type="button" className="btn btn-sm btn-light app-dt-tool" aria-pressed={density === "compact"}
                                title={L.density} onClick={() => setDensity(density === "compact" ? "comfortable" : "compact")}>
                                <i className={`bi ${density === "compact" ? "bi-arrows-expand" : "bi-arrows-collapse"}`} aria-hidden="true" />
                                <span className="d-none d-lg-inline ms-1">{density === "compact" ? L.compact : L.comfortable}</span>
                            </button>
                        ) : null}
                        {showColumnToggle ? (
                            <div className="position-relative">
                                <button type="button" className="btn btn-sm btn-light app-dt-tool" aria-expanded={menuOpen} aria-haspopup="true"
                                    onClick={() => setMenuOpen((o) => !o)}>
                                    <i className="bi bi-layout-three-columns" aria-hidden="true" />
                                    <span className="d-none d-lg-inline ms-1">{L.columns}</span>
                                </button>
                                {menuOpen ? (
                                    <div className="app-dt-menu" role="group" aria-label={L.columns}
                                        onKeyDown={(e) => { if (e.key === "Escape") setMenuOpen(false); }}>
                                        {columns.map((c) => {
                                            const locked = c === primary || c.hideable === false;
                                            return (
                                                <label key={c.key} className="form-check app-dt-menu-item">
                                                    <input type="checkbox" className="form-check-input" checked={!hidden.has(c.key)} disabled={locked}
                                                        onChange={() => setHidden((h) => { const n = new Set(h); n.has(c.key) ? n.delete(c.key) : n.add(c.key); return n; })} />
                                                    <span className="form-check-label">{c.header}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                </div>
            ) : null}

            {selectable && selected.size > 0 ? (
                <div className="app-dt-bulk" role="region" aria-live="polite" aria-label={L.selected(selected.size)}>
                    <strong>{L.selected(selected.size)}</strong>
                    <div className="d-flex flex-wrap gap-2">
                        {bulkActions.map((a) => (
                            <button key={a.label} type="button" className={`btn btn-sm btn-${a.tone ?? "primary"}`} onClick={() => a.onClick(selectedRows)}>
                                {a.icon ? <i className={`bi ${a.icon} me-1`} aria-hidden="true" /> : null}{a.label}
                            </button>
                        ))}
                        <button type="button" className="btn btn-sm btn-link" onClick={() => setSelected(new Set())}>{L.clear}</button>
                    </div>
                </div>
            ) : null}

            <div className="app-dt-scroll d-none d-md-block" style={{ maxHeight }}>
                <table className="table app-dt-table align-middle mb-0" aria-busy={loading || undefined}>
                    {caption ? <caption className="visually-hidden">{caption}</caption> : null}
                    <thead>
                        <tr>
                            {selectable ? (
                                <th scope="col" className="app-dt-check app-dt-sticky">
                                    <input type="checkbox" className="form-check-input" aria-label={L.selectAll} checked={allSelected}
                                        ref={(el) => { if (el) el.indeterminate = someSelected; }} onChange={toggleAll} />
                                </th>
                            ) : null}
                            {visible.map((c, i) => {
                                const active = sort?.key === c.key;
                                const ariaSort = active ? (sort!.direction === "asc" ? "ascending" : "descending") : c.sortable ? "none" : undefined;
                                return (
                                    <th key={c.key} scope="col" aria-sort={ariaSort} className={`${c.numeric || c.format ? "text-end" : ""}${stickyCls(i)}`}>
                                        {c.sortable ? (
                                            <button type="button" className={`app-dt-sort${active ? " is-active" : ""}`} onClick={() => toggleSort(c)}
                                                title={active && sort!.direction === "asc" ? L.sortDesc : L.sortAsc}>
                                                {c.header}
                                                <i className={`bi ${active ? (sort!.direction === "asc" ? "bi-sort-up" : "bi-sort-down") : "bi-arrow-down-up"}`} aria-hidden="true" />
                                            </button>
                                        ) : c.header}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {loading
                            ? Array.from({ length: 5 }, (_, i) => (
                                  <tr key={`sk${i}`} className="app-dt-skeleton-row" aria-hidden="true">
                                      {selectable ? <td className="app-dt-check" /> : null}
                                      {visible.map((c) => <td key={c.key}><span className="app-skeleton" /></td>)}
                                  </tr>
                              ))
                            : rows.map((r) => {
                                  const k = rowKey(r);
                                  return (
                                      <tr key={k} {...interactive(r)} className={`${onRowClick ? "app-row-action" : ""}${selected.has(k) ? " is-selected" : ""}`} aria-selected={selectable ? selected.has(k) : undefined}>
                                          {selectable ? (
                                              <td className="app-dt-check app-dt-sticky" onClick={(e) => e.stopPropagation()}>
                                                  <input type="checkbox" className="form-check-input" checked={selected.has(k)} onChange={() => toggleOne(k)}
                                                      aria-label={`${L.selectRow}: ${rowLabel ? rowLabel(r) : String(k)}`} />
                                              </td>
                                          ) : null}
                                          {visible.map((c, i) => (
                                              <td key={c.key} className={`${c.numeric || c.format ? "text-end" : ""}${stickyCls(i)}`}>{content(c, r)}</td>
                                          ))}
                                      </tr>
                                  );
                              })}
                    </tbody>
                </table>
            </div>

            <ul className="list-unstyled d-md-none d-flex flex-column gap-2 mb-0 app-dt-cards" aria-label={caption} aria-busy={loading || undefined}>
                {loading
                    ? Array.from({ length: 3 }, (_, i) => <li key={i} className="app-dt-card" aria-hidden="true"><span className="app-skeleton w-50" /><span className="app-skeleton" /></li>)
                    : rows.map((r) => {
                          const k = rowKey(r);
                          return (
                              <li key={k}>
                                  <div {...interactive(r)} className={`app-dt-card${onRowClick ? " app-row-action" : ""}${selected.has(k) ? " is-selected" : ""}`}>
                                      <div className="d-flex align-items-start gap-2 mb-1">
                                          {selectable ? (
                                              <input type="checkbox" className="form-check-input mt-1" checked={selected.has(k)} onClick={(e) => e.stopPropagation()}
                                                  onChange={() => toggleOne(k)} aria-label={`${L.selectRow}: ${rowLabel ? rowLabel(r) : String(k)}`} />
                                          ) : null}
                                          <div className="fw-semibold">{content(primary, r)}</div>
                                      </div>
                                      <dl className="mb-0 small">
                                          {visible.filter((c) => c !== primary && !c.hideOnMobile).map((c) => (
                                              <div key={c.key} className="d-flex justify-content-between gap-2 py-1">
                                                  <dt className="fw-normal text-body-secondary">{c.header}</dt>
                                                  <dd className="mb-0 text-end">{content(c, r)}</dd>
                                              </div>
                                          ))}
                                      </dl>
                                  </div>
                              </li>
                          );
                      })}
            </ul>

            {!loading && rows.length === 0 ? (
                <div className="app-dt-empty"><i className={`bi ${query ? "bi-search" : "bi-inbox"}`} aria-hidden="true" /><EmptyState title={query ? L.noMatch : emptyTitle} description={query ? undefined : emptyDescription} actionLabel={query ? undefined : emptyActionLabel} onAction={onEmptyAction} /></div>
            ) : null}

            {loading ? <span className="visually-hidden" role="status">{L.loading}</span> : null}

            {isServer && meta && meta.last_page > 1 ? (
                <nav className="app-dt-pager" aria-label={L.pages}>
                    <span className="text-body-secondary small">{L.range(meta.from ?? 0, meta.to ?? 0, meta.total)}</span>
                    <div className="d-flex gap-1">
                        <button type="button" className="btn btn-sm btn-light" disabled={meta.current_page <= 1} onClick={() => goPage(meta.current_page - 1)}>
                            <i className="bi bi-chevron-right" aria-hidden="true" /> {L.prev}
                        </button>
                        <span className="btn btn-sm disabled border-0" aria-current="page"><bdi dir="ltr">{meta.current_page} / {meta.last_page}</bdi></span>
                        <button type="button" className="btn btn-sm btn-light" disabled={meta.current_page >= meta.last_page} onClick={() => goPage(meta.current_page + 1)}>
                            {L.next} <i className="bi bi-chevron-left" aria-hidden="true" />
                        </button>
                    </div>
                </nav>
            ) : null}
        </div>
    );
}
