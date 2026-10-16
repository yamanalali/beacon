import type { KeyboardEvent, ReactNode } from "react";
import { EmptyState } from "../Feedback/EmptyState";

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
};

/**
 * Desktop: real table. Mobile: stacked cards (§45: never shrink desktop tables on mobile).
 */
export function DataTable<T>({ columns, rows, rowKey, caption, emptyTitle = "لا توجد بيانات", emptyDescription, onRowClick, rowLabel }: {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    caption?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    onRowClick?: (row: T) => void;
    /** Accessible name for a clickable row, e.g. "عرض المعاملة 12". */
    rowLabel?: (row: T) => string;
}) {
    const interactive = (r: T) =>
        onRowClick
            ? {
                  tabIndex: 0,
                  role: "button",
                  "aria-label": rowLabel ? rowLabel(r) : undefined,
                  onClick: () => onRowClick(r),
                  onKeyDown: (e: KeyboardEvent<HTMLElement>) => {
                      if (e.key === "Enter" || e.key === " ") {
                          e.preventDefault();
                          onRowClick(r);
                      }
                  },
                  style: { cursor: "pointer" },
              }
            : {};
    if (rows.length === 0) return <EmptyState title={emptyTitle} description={emptyDescription} />;
    const primary = columns.find((c) => c.primary) ?? columns[0];
    const rest = columns.filter((c) => c !== primary && !c.hideOnMobile);

    return (
        <>
            <div className="table-responsive d-none d-lg-block">
                <table className="table table-hover align-middle mb-0">
                    {caption ? <caption className="visually-hidden">{caption}</caption> : null}
                    <thead className="table-light">
                        <tr>{columns.map((c) => <th key={c.key} scope="col" className={c.numeric ? "text-end" : ""}>{c.header}</th>)}</tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={rowKey(r)} {...interactive(r)} className={onRowClick ? "app-row-action" : undefined}>
                                {columns.map((c) => (
                                    <td key={c.key} className={c.numeric ? "text-end" : ""}>
                                        {c.numeric ? <bdi dir="ltr">{c.cell(r)}</bdi> : c.cell(r)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <ul className="list-unstyled d-lg-none d-flex flex-column gap-2 mb-0" aria-label={caption}>
                {rows.map((r) => (
                    <li key={rowKey(r)}>
                        <div {...interactive(r)} className={`app-mobile-record${onRowClick ? " app-row-action" : ""}`}>
                        <div className="fw-semibold mb-1">{primary.cell(r)}</div>
                        <dl className="row g-1 mb-0 small">
                            {rest.map((c) => (
                                <div key={c.key} className="col-12 d-flex justify-content-between gap-2">
                                    <dt className="fw-normal text-body-secondary">{c.header}</dt>
                                    <dd className="mb-0 text-end">{c.numeric ? <bdi dir="ltr">{c.cell(r)}</bdi> : c.cell(r)}</dd>
                                </div>
                            ))}
                        </dl>
                        </div>
                    </li>
                ))}
            </ul>
        </>
    );
}