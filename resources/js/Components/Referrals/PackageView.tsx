import { useState, type ReactNode } from "react";
import { SectionCard } from "../Common/SectionCard";
import { EmptyState } from "../Feedback/EmptyState";
import { StatusBadge } from "../Feedback/StatusBadge";
import { formatMoney } from "../../lib/money";

/**
 * Readable referral package (ReferralPackageBuilder output). Never renders raw JSON, technical keys or ids.
 * Known keys get Arabic labels; unknown keys get a humanized Arabic fallback.
 */

export type Pkg = Record<string, unknown>;

const LABELS: Record<string, string> = {
    // enterprise_profile
    business_name: "اسم المنشأة", sector: "القطاع", location: "الموقع", founding_date: "تاريخ التأسيس",
    employee_count: "عدد الموظفين", registration_status: "حالة التسجيل", legal_form: "الشكل القانوني", owner_name: "اسم المالك",
    // readiness_assessment
    score: "النتيجة", band: "التصنيف", mandatory_conditions_met: "الشروط الإلزامية", finalized_at: "تاريخ الاعتماد",
    rule_set_version: "نسخة القواعد", disclaimer: "تنبيه",
    // compliance_status
    requirement: "المتطلب", version: "النسخة", status: "الحالة",
    // financial / documents (not sent yet; labels ready)
    currency: "العملة", period: "الفترة", period_start: "بداية الفترة", period_end: "نهاية الفترة", revenue: "الإيرادات",
    income: "الدخل", expenses: "المصروفات", net_income: "صافي الدخل", profit: "الربح", cash_balance: "الرصيد النقدي",
    receivables: "الذمم المدينة", payables: "الذمم الدائنة", transactions_count: "عدد العمليات", title: "العنوان",
    name: "الاسم", type: "النوع", uploaded_at: "تاريخ الرفع", date: "التاريخ", notes: "ملاحظات", note: "ملاحظة",
    amount: "المبلغ", total: "المجموع", description: "الوصف",
};

const SECTION_TITLES: Record<string, string> = {
    enterprise_profile: "معلومات المنشأة",
    financial_summary: "الملخص المالي",
    readiness_assessment: "نتيجة الجاهزية",
    documents: "المستندات",
    compliance_status: "حالة الامتثال",
};
const KNOWN_ORDER = ["enterprise_profile", "financial_summary", "readiness_assessment", "documents", "compliance_status"];

const WORDS: Record<string, string> = {
    date: "تاريخ", at: "تاريخ", count: "عدد", status: "حالة", name: "اسم", amount: "مبلغ", total: "مجموع", note: "ملاحظة",
    notes: "ملاحظات", type: "نوع", number: "رقم", value: "قيمة", rate: "نسبة", score: "نتيجة", balance: "رصيد", period: "فترة",
};

/** Technical fields never shown to users. */
export const isTechnicalKey = (k: string) => /(^id$|_id$|^public_id$|uuid|hash|token|^created_by$|^updated_at$|^created_at$|_path$|^path$|^url$|mime)/i.test(k);

/** Arabic label for a key; unknown keys get a word-based Arabic fallback, else a generic one. Never the raw key. */
export function humanizeKey(k: string): string {
    if (LABELS[k]) return LABELS[k];
    const parts = k.split(/[_\s-]+/).map((w) => WORDS[w.toLowerCase()]).filter(Boolean);
    return parts.length ? parts.join(" ") : "معلومة إضافية";
}

const MONEY_KEY = /(amount|revenue|income|expense|profit|balance|receivable|payable|total|sales|cost|price)/i;
const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/;
const DATE_TIME = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?/;

export function formatDate(v: string): string {
    const d = new Date(DATE_ONLY.test(v) ? `${v}T00:00:00` : v.replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return v;
    const date = d.toLocaleDateString("ar-SY-u-nu-latn", { year: "numeric", month: "long", day: "numeric" });
    return DATE_ONLY.test(v) ? date : `${date} ${d.toLocaleTimeString("ar-SY-u-nu-latn", { hour: "2-digit", minute: "2-digit" })}`;
}

export const isEmpty = (v: unknown): boolean =>
    v === null || v === undefined || (typeof v === "string" && v.trim() === "") || (Array.isArray(v) && v.length === 0) ||
    (typeof v === "object" && !Array.isArray(v) && Object.entries(v as object).every(([k, x]) => isTechnicalKey(k) || isEmpty(x)));

const isPlainObject = (v: unknown): v is Record<string, unknown> => typeof v === "object" && v !== null && !Array.isArray(v);

/** Primitive/collection value as readable UI. `ctx` is the parent object (for a sibling `currency`). */
export function ValueView({ k, v, ctx }: { k: string; v: unknown; ctx?: Record<string, unknown> }): ReactNode {
    if (typeof v === "boolean") return <StatusBadge label={v ? "نعم" : "لا"} tone={v ? "success" : "danger"} />;
    if (typeof v === "number" || (typeof v === "string" && /^-?\d+(\.\d+)?$/.test(v.trim()) && MONEY_KEY.test(k))) {
        const cur = typeof ctx?.currency === "string" ? ctx.currency : null;
        if (MONEY_KEY.test(k) && cur) return <bdi dir="ltr">{formatMoney(v as number | string, cur)}</bdi>;
        return <bdi dir="ltr">{Number(v).toLocaleString("en-US", { maximumFractionDigits: 2 })}</bdi>;
    }
    if (typeof v === "string") {
        if (DATE_ONLY.test(v) || DATE_TIME.test(v)) return <span>{formatDate(v)}</span>;
        return <span>{v}</span>;
    }
    if (Array.isArray(v)) {
        if (v.every((x) => !isPlainObject(x) && !Array.isArray(x))) return <span>{v.filter((x) => !isEmpty(x)).map(String).join("، ")}</span>;
        return <RecordsTable rows={v.filter(isPlainObject)} />;
    }
    if (isPlainObject(v)) return <Lines obj={v} />;
    return null;
}

/** Label/value lines for an object; empty and technical fields are hidden. */
export function Lines({ obj, skip = [] }: { obj: Record<string, unknown>; skip?: string[] }) {
    const entries = Object.entries(obj).filter(([k, v]) => !skip.includes(k) && !isTechnicalKey(k) && !isEmpty(v) && !(k === "currency" && Object.keys(obj).some((x) => MONEY_KEY.test(x))));
    if (entries.length === 0) return null;
    return (
        <div>
            {entries.map(([k, v], i) => (
                <div key={i} className="app-record-line">
                    <span>{humanizeKey(k)}</span>
                    <span><ValueView k={k} v={v} ctx={obj} /></span>
                </div>
            ))}
        </div>
    );
}

/** Lists of objects: table on md+, cards on mobile. */
export function RecordsTable({ rows }: { rows: Record<string, unknown>[] }) {
    const cols = [...new Set(rows.flatMap((r) => Object.keys(r)))].filter((k) => !isTechnicalKey(k) && rows.some((r) => !isEmpty(r[k])));
    if (rows.length === 0 || cols.length === 0) return null;
    return (
        <>
            <div className="table-responsive d-none d-md-block">
                <table className="table align-middle mb-0">
                    <thead><tr>{cols.map((c) => <th key={c} scope="col">{humanizeKey(c)}</th>)}</tr></thead>
                    <tbody>
                        {rows.map((r, i) => (
                            <tr key={i}>{cols.map((c) => <td key={c}>{isEmpty(r[c]) ? "—" : <ValueView k={c} v={r[c]} ctx={r} />}</td>)}</tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="d-md-none d-flex flex-column gap-2">
                {rows.map((r, i) => <div key={i} className="app-mobile-record"><Lines obj={r} /></div>)}
            </div>
        </>
    );
}

function ReadinessCard({ data }: { data: Record<string, unknown> }) {
    const score = data.score;
    const band = typeof data.band === "string" && data.band.trim() ? data.band : null;
    const met = data.mandatory_conditions_met;
    return (
        <SectionCard title={SECTION_TITLES.readiness_assessment}>
            <div className="d-flex flex-wrap align-items-center gap-2 mb-2" data-testid="readiness-badges">
                {!isEmpty(score) ? <span className="app-status app-status--neutral"><span>النتيجة <bdi dir="ltr">{Number(score).toLocaleString("en-US", { maximumFractionDigits: 2 })}</bdi> من 100</span></span> : <StatusBadge label="لم تُحسب النتيجة" tone="neutral" />}
                {/* Band colour token is not in the package yet (label only), so the badge stays neutral. */}
                {band ? <StatusBadge label={band} tone="neutral" /> : null}
                {typeof met === "boolean" ? <StatusBadge label={met ? "الشروط الإلزامية مستوفاة" : "شرط إلزامي غير مستوفى"} tone={met ? "success" : "danger"} /> : null}
            </div>
            <Lines obj={data} skip={["score", "band", "mandatory_conditions_met", "disclaimer"]} />
            {typeof data.disclaimer === "string" && data.disclaimer ? <p className="form-text mb-0 mt-2">{data.disclaimer}</p> : null}
        </SectionCard>
    );
}

const EMPTY_TEXT: Record<string, string> = {
    enterprise_profile: "لم تتم مشاركة معلومات المنشأة في هذه الإحالة.",
    financial_summary: "لم تتم مشاركة الملخص المالي، أو لا توجد معاملات في الفترة.",
    readiness_assessment: "لم تتم مشاركة نتيجة الجاهزية، أو لا يوجد تقييم معتمد.",
    documents: "لم تتم مشاركة مستندات في هذه الإحالة.",
    compliance_status: "لم تتم مشاركة حالة الامتثال في هذه الإحالة.",
};

/** Server sends *_label next to raw codes (#78); show the label in place of the code. */
function EnterpriseCard({ data }: { data: Record<string, unknown> }) {
    const obj: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(data)) {
        if (k.endsWith("_label")) continue;
        const label = data[`${k}_label`];
        obj[k] = typeof label === "string" && label.trim() ? label : v;
    }
    return <SectionCard title={SECTION_TITLES.enterprise_profile}><Lines obj={obj} /></SectionCard>;
}

type CurrencyTotals = { currency: string; income_total: string | number; expense_total: string | number; net: string | number; transaction_count: number };

/** One card per currency, SYP first; never converted. */
export function FinancialSummary({ data }: { data: Record<string, unknown> }) {
    const period = isPlainObject(data.period) ? (data.period as { from?: string; to?: string }) : null;
    const rows = (Array.isArray(data.currencies) ? data.currencies : []).filter(isPlainObject) as unknown as CurrencyTotals[];
    const sorted = [...rows].sort((a, b) => (a.currency === "SYP" ? -1 : b.currency === "SYP" ? 1 : a.currency.localeCompare(b.currency)));
    return (
        <SectionCard title={SECTION_TITLES.financial_summary}>
            {period?.from && period?.to ? <p className="text-muted mb-2" data-testid="fin-period">الفترة من {formatDate(period.from)} إلى {formatDate(period.to)}</p> : null}
            {sorted.length === 0 ? (
                <EmptyState title="لا توجد معاملات" description={EMPTY_TEXT.financial_summary} />
            ) : (
                <div className="row g-2">
                    {sorted.map((c) => {
                        const net = Number(c.net);
                        return (
                            <div key={c.currency} className="col-12 col-md-6 col-xl-4">
                                <div className="app-mobile-record h-100" data-testid="fin-currency">
                                    <div className="fw-semibold mb-1">{c.currency}</div>
                                    <div className="app-record-line"><span>الإيرادات</span><bdi dir="ltr">{formatMoney(c.income_total, c.currency)}</bdi></div>
                                    <div className="app-record-line"><span>المصروفات</span><bdi dir="ltr">{formatMoney(c.expense_total, c.currency)}</bdi></div>
                                    <div className="app-record-line">
                                        <span>الصافي</span>
                                        <bdi dir="ltr" data-testid="fin-net" style={{ color: net > 0 ? "var(--app-success)" : net < 0 ? "var(--app-danger)" : undefined, fontWeight: 600 }}>{formatMoney(c.net, c.currency)}</bdi>
                                    </div>
                                    <div className="app-record-line"><span>عدد المعاملات</span><bdi dir="ltr">{Number(c.transaction_count ?? 0).toLocaleString("en-US")}</bdi></div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </SectionCard>
    );
}

type Doc = { name: string; type: string | null; date: string | null };
type SortKey = "name" | "type" | "date";

/** Sortable table on md+, cards on mobile. Default: newest first (server order). */
export function DocumentsTable({ docs }: { docs: Doc[] }) {
    const [sort, setSort] = useState<{ key: SortKey; dir: 1 | -1 }>({ key: "date", dir: -1 });
    const sorted = [...docs].sort((a, b) => {
        const x = a[sort.key] ?? "";
        const y = b[sort.key] ?? "";
        return (sort.key === "date" ? String(x).localeCompare(String(y)) : String(x).localeCompare(String(y), "ar")) * sort.dir;
    });
    const head = (key: SortKey, label: string) => (
        <th scope="col" aria-sort={sort.key === key ? (sort.dir === 1 ? "ascending" : "descending") : "none"}>
            <button type="button" className="btn btn-link p-0 text-reset text-decoration-none fw-semibold" onClick={() => setSort((s) => ({ key, dir: s.key === key ? (s.dir === 1 ? -1 : 1) : 1 }))}>
                {label} {sort.key === key ? <i className={`bi ${sort.dir === 1 ? "bi-sort-up" : "bi-sort-down"}`} aria-hidden="true" /> : null}
            </button>
        </th>
    );
    return (
        <SectionCard title={SECTION_TITLES.documents}>
            <div className="table-responsive d-none d-md-block">
                <table className="table align-middle mb-0" data-testid="docs-table">
                    <thead><tr>{head("name", "الاسم")}{head("type", "النوع")}{head("date", "التاريخ")}</tr></thead>
                    <tbody>
                        {sorted.map((d, i) => <tr key={i}><td>{d.name}</td><td>{d.type || "—"}</td><td>{d.date ? formatDate(d.date) : "—"}</td></tr>)}
                    </tbody>
                </table>
            </div>
            <div className="d-md-none d-flex flex-column gap-2" data-testid="docs-cards">
                {sorted.map((d, i) => (
                    <div key={i} className="app-mobile-record">
                        <div className="fw-semibold">{d.name}</div>
                        {d.type ? <div className="app-record-line"><span>النوع</span><span>{d.type}</span></div> : null}
                        {d.date ? <div className="app-record-line"><span>التاريخ</span><span>{formatDate(d.date)}</span></div> : null}
                    </div>
                ))}
            </div>
        </SectionCard>
    );
}

function EmptySection({ k }: { k: string }) {
    return (
        <SectionCard title={SECTION_TITLES[k]}>
            <div data-testid={`empty-${k}`}><EmptyState title="غير متوفر" description={EMPTY_TEXT[k]} /></div>
        </SectionCard>
    );
}

function Section({ k, v }: { k: string; v: unknown }) {
    if (k === "financial_summary" && isPlainObject(v) && Array.isArray(v.currencies)) return <FinancialSummary data={v} />;
    if (isEmpty(v)) return KNOWN_ORDER.includes(k) ? <EmptySection k={k} /> : null;
    if (k === "readiness_assessment" && isPlainObject(v)) return <ReadinessCard data={v} />;
    if (k === "enterprise_profile" && isPlainObject(v)) return <EnterpriseCard data={v} />;
    if (k === "documents" && Array.isArray(v) && v.every((d) => isPlainObject(d) && typeof d.name === "string")) return <DocumentsTable docs={v.filter(isPlainObject) as unknown as Doc[]} />;
    return (
        <SectionCard title={SECTION_TITLES[k] ?? humanizeKey(k)}>
            {Array.isArray(v) ? <ValueView k={k} v={v} /> : isPlainObject(v) ? <Lines obj={v} /> : <ValueView k={k} v={v} />}
        </SectionCard>
    );
}

export function PackageView({ pkg }: { pkg: Pkg }) {
    // Every known section is shown; missing (not disclosed) or empty ones get an Arabic empty state.
    if (Object.keys(pkg).length === 0) return <p className="text-muted mb-0">لا توجد بيانات مشاركة بعد.</p>;
    const known = KNOWN_ORDER;
    const extra = Object.entries(pkg).filter(([k, v]) => !KNOWN_ORDER.includes(k) && !isTechnicalKey(k) && !isEmpty(v));
    return (
        <div className="d-flex flex-column gap-3" data-testid="package-view">
            {known.map((k) => <Section key={k} k={k} v={pkg[k]} />)}
            {extra.length ? (
                <SectionCard title="تفاصيل إضافية">
                    {extra.map(([k, v], i) =>
                        isPlainObject(v) || (Array.isArray(v) && v.some(isPlainObject)) ? (
                            <div key={i} className="mb-3"><div className="fw-semibold mb-1">{humanizeKey(k)}</div><ValueView k={k} v={v} /></div>
                        ) : (
                            <div key={i} className="app-record-line"><span>{humanizeKey(k)}</span><span><ValueView k={k} v={v} /></span></div>
                        ),
                    )}
                </SectionCard>
            ) : null}
        </div>
    );
}
