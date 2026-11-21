import { usePage } from "@inertiajs/react";
import { useOnlineStatus } from "../../hooks/useOnlineStatus";
import { Link, router } from "@inertiajs/react";
import { useEffect, useMemo, useState } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { SelectInput, type SelectOption } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import type { EnterpriseRef } from "../../types/bookkeeping";
import { Money } from "../../Components/Common/Money";
import { useT } from "../../Hooks/useT";

type Section = { title: string; columns: string[]; rows: string[][] };
type Report = { key: string; title: string; period_label: string; sections: Section[]; notes: string[] };
type Filters = { report: string; from: string; to: string; basis: string };

/** Server cells are raw strings; decimal amounts ("1000000.00", "-250.50") get the shared money format. */
export function isAmountCell(cell: string): boolean {
    return /^-?\d+\.\d+$/.test(cell.trim());
}
/** Index of the currency column when the section has one (amounts then show that row's symbol). */
export function currencyColumn(columns: string[]): number {
    return columns.findIndex((c) => /العملة|currency/i.test(c));
}

export default function Show({
    enterprise,
    report,
    filters,
    reports,
    bases,
    print,
    can_export = true,
}: {
    enterprise: EnterpriseRef;
    report: Report;
    filters: Filters;
    reports: SelectOption[];
    bases: SelectOption[];
    print: boolean;
    can_export?: boolean;
}) {
    const t = useT();
    const online = useOnlineStatus();
    const { offline } = usePage().props;
    const local = !online || offline?.cached === true;
    const downloadLocalCsv = () => {
        const safe = (value: string) => { const text = /^\s*[=+@]/.test(value) || /^\s*-(?!\d)/.test(value) ? "'" + value : value; return '"' + text.replaceAll('"', '""') + '"'; };
        const rows = [[report.title], [report.period_label], ...report.notes.map(note => [note]), ...report.sections.flatMap(section => [[section.title], section.columns, ...section.rows])];
        const blob = new Blob(["\uFEFF" + rows.map(row => row.map(safe).join(",")).join("\r\n")], { type: "text/csv;charset=utf-8" });
        const url = URL.createObjectURL(blob); const link = document.createElement("a"); link.href = url; link.download = `offline-${report.key}-${filters.from}-${filters.to}.csv`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    };
    const [values, setValues] = useState<Filters>(filters);
    const [reportSearch, setReportSearch] = useState("");
    const base = `/enterprises/${enterprise.public_id}/reports`;
    const query = new URLSearchParams(filters).toString();
    const filteredReports = useMemo(() => {
        const q = reportSearch.trim().toLowerCase();
        if (!q) {
            return reports;
        }
        return reports.filter(
            (option) => option.label.toLowerCase().includes(q) || option.value.toLowerCase().includes(q),
        );
    }, [reports, reportSearch]);

    useEffect(() => {
        if (print && can_export) {
            window.print();
        }
    }, [print, can_export]);

    useEffect(() => {
        if (filteredReports.length === 0) {
            return;
        }
        if (!filteredReports.some((option) => option.value === values.report)) {
            setValues((current) => ({ ...current, report: filteredReports[0].value }));
        }
    }, [filteredReports, values.report]);

    return (
        <AppLayout title={t("reports.title", undefined, "التقارير")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title={report.title} description={`${enterprise.business_name} — ${report.period_label}`} />

                <div className="d-print-none">
                    <SectionCard title={t("reports.select_report", undefined, "اختيار التقرير")}>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(base, values, { preserveState: true });
                            }}
                        >
                            <div className="row g-2">
                                <div className="col-12 col-lg-4">
                                    <TextInput
                                        id="report-search"
                                        name="report_search"
                                        label={t("reports.search_label", undefined, "بحث عن تقرير")}
                                        value={reportSearch}
                                        onChange={setReportSearch}
                                        placeholder={t("reports.search_placeholder", undefined, "مثال: مبيعات، مصروف، تدفق نقدي")}
                                    />
                                </div>
                                <div className="col-12 col-lg-4">
                                    <SelectInput
                                        id="report"
                                        label={t("reports.report_label", undefined, "التقرير")}
                                        value={values.report}
                                        options={filteredReports}
                                        onChange={(v) => setValues({ ...values, report: v })}
                                    />
                                    {filteredReports.length === 0 ? (
                                        <p className="small text-body-secondary mb-0" role="status">
                                            {t("reports.search_empty", undefined, "لا يوجد تقرير يطابق البحث.")}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="col-6 col-lg-2">
                                    <TextInput
                                        id="from"
                                        name="from"
                                        type="date"
                                        label={t("filters.date_from", undefined, "من تاريخ")}
                                        value={values.from}
                                        onChange={(v) => setValues({ ...values, from: v })}
                                    />
                                </div>
                                <div className="col-6 col-lg-2">
                                    <TextInput
                                        id="to"
                                        name="to"
                                        type="date"
                                        label={t("filters.date_to", undefined, "إلى تاريخ")}
                                        value={values.to}
                                        onChange={(v) => setValues({ ...values, to: v })}
                                    />
                                </div>
                                {values.report === "income_statement" ? (
                                    <div className="col-12 col-lg-4">
                                        <SelectInput
                                            id="basis"
                                            label={t("reports.basis_label", undefined, "طريقة الاحتساب")}
                                            value={values.basis}
                                            options={bases}
                                            onChange={(v) => setValues({ ...values, basis: v })}
                                        />
                                    </div>
                                ) : null}
                            </div>
                            <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={filteredReports.length === 0}>
                                {t("reports.show_report", undefined, "عرض التقرير")}
                            </button>
                        </form>
                    </SectionCard>
                </div>

                <div className="d-flex flex-wrap gap-2 d-print-none">
                    {can_export ? <>
                    {local ? (
                        <button type="button" className="btn btn-outline-primary" disabled>
                            {t("reports.excel_when_online", undefined, "Excel متاح عند الاتصال")}
                        </button>
                    ) : (
                        <a className="btn btn-outline-primary" style={{ minHeight: "48px" }} href={`${base}/export/xlsx?${query}`}>
                            <i className="bi bi-file-earmark-excel me-2" aria-hidden="true" />
                            {t("reports.download_excel", undefined, "تنزيل Excel")}
                        </a>
                    )}
                    {local ? (
                        <button type="button" className="btn btn-outline-primary" onClick={downloadLocalCsv}>
                            {t("reports.download_csv_cached", undefined, "تنزيل CSV من النسخة المحفوظة")}
                        </button>
                    ) : (
                        <a className="btn btn-outline-primary" style={{ minHeight: "48px" }} href={`${base}/export/csv?${query}`}>
                            <i className="bi bi-filetype-csv me-2" aria-hidden="true" />
                            {t("reports.download_csv", undefined, "تنزيل CSV")}
                        </a>
                    )}
                    {local ? (
                        <button type="button" className="btn btn-outline-primary" disabled>
                            {t("reports.pdf_when_online", undefined, "PDF متاح عند الاتصال")}
                        </button>
                    ) : (
                        <a className="btn btn-outline-primary" style={{ minHeight: "48px" }} href={`${base}/export/pdf?${query}`}>
                            <i className="bi bi-filetype-pdf me-2" aria-hidden="true" />
                            {t("reports.download_pdf", undefined, "تنزيل PDF")}
                        </a>
                    )}
                    <button type="button" className="btn btn-outline-primary" style={{ minHeight: "48px" }} onClick={() => window.print()}>
                        <i className="bi bi-printer me-2" aria-hidden="true" />
                        {t("common.print", undefined, "طباعة")}
                    </button>
                    </> : null}
                    <Link href={`/enterprises/${enterprise.public_id}/transactions`} className="btn btn-link" style={{ minHeight: "48px" }}>
                        {t("transactions.title", undefined, "المعاملات")}
                    </Link>
                </div>

                {report.sections.map((section) => (
                    <SectionCard key={section.title} title={section.title}>
                        {section.rows.length === 0 ? (
                            <EmptyState title={t("reports.empty_period", undefined, "لا توجد بيانات لهذه الفترة")} />
                        ) : (
                            <div className="table-responsive">
                                <table className="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            {section.columns.map((c) => (
                                                <th key={c}>{c}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {section.rows.map((row, i) => {
                                            const cur = currencyColumn(section.columns);
                                            return (
                                                <tr key={i}>
                                                    {row.map((cell, j) => (
                                                        <td key={j} className={isAmountCell(cell) ? "text-nowrap" : undefined}>
                                                            {isAmountCell(cell) ? <Money amount={cell} currency={cur >= 0 && cur !== j ? row[cur] : null} /> : cell}
                                                        </td>
                                                    ))}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                ))}

                <div className="small text-body-secondary">
                    {report.notes.map((n) => (
                        <p key={n} className="mb-1">
                            {n}
                        </p>
                    ))}
                    <p className="mb-0">
                        {t(
                            "reports.disclaimer",
                            undefined,
                            "هذا التقرير محسوب من سجلاتك المحفوظة وليس تقييماً أو موافقة على تمويل.",
                        )}
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
