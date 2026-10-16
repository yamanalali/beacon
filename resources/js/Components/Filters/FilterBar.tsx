import { router } from "@inertiajs/react";
import { useState, type ReactNode } from "react";

export type FilterValues = Record<string, string>;

/**
 * Collapsible filter panel. Filtering is done server-side: apply() issues an Inertia GET with the
 * values as query params so results stay authorization-scoped (§50) and match exports (§39).
 */
export function FilterBar({ initial, url, children, only }: {
    initial: FilterValues;
    url?: string;
    only?: string[];
    children: (values: FilterValues, set: (key: string, v: string) => void) => ReactNode;
}) {
    const [values, setValues] = useState<FilterValues>(initial);
    const [open, setOpen] = useState(false);
    const set = (key: string, v: string) => setValues((p) => ({ ...p, [key]: v }));
    const active = Object.values(values).filter(Boolean).length;
    const target = url ?? window.location.pathname;
    const clean = (v: FilterValues) => Object.fromEntries(Object.entries(v).filter(([, x]) => x !== ""));

    const apply = () => router.get(target, clean(values), { preserveState: true, preserveScroll: true, replace: true, only });
    const reset = () => {
        const empty = Object.fromEntries(Object.keys(values).map((k) => [k, ""]));
        setValues(empty);
        router.get(target, {}, { preserveState: true, replace: true, only });
    };

    return (
        <div className="border rounded-3 bg-white p-3 mb-3">
            <button type="button" className="btn btn-link p-0 d-lg-none fw-semibold text-decoration-none" aria-expanded={open} onClick={() => setOpen(!open)}>
                <i className="bi bi-funnel me-1" aria-hidden="true" />
                التصفية {active > 0 ? <span className="badge text-bg-primary ms-1">{active}</span> : null}
            </button>
            <form className={`${open ? "" : "d-none"} d-lg-block mt-3 mt-lg-0`} onSubmit={(e) => { e.preventDefault(); apply(); }}>
                <div className="row g-3 align-items-end">
                    {children(values, set)}
                    <div className="col-12 col-lg-auto d-flex gap-2">
                        <button type="submit" className="btn btn-primary flex-fill" style={{ minHeight: 48 }}>
                            <i className="bi bi-check2 me-1" aria-hidden="true" />تطبيق
                        </button>
                        <button type="button" className="btn btn-outline-secondary flex-fill" style={{ minHeight: 48 }} onClick={reset}>
                            مسح
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}