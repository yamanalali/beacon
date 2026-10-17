import { Link, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { SelectInput, type SelectOption } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import type { EnterpriseRef } from "../../types/bookkeeping";
import { Money } from "../../Components/Common/Money";

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
    const [values, setValues] = useState<Filters>(filters);
    const base = `/enterprises/${enterprise.public_id}/reports`;
    const query = new URLSearchParams(filters).toString();

    useEffect(() => {
        if (print && can_export) {
            window.print();
        }
    }, [print, can_export]);

    return (
        <AppLayout title="التقارير">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title={report.title} description={`${enterprise.business_name} — ${report.period_label}`} />

                <div className="d-print-none">
                    <SectionCard title="اختيار التقرير">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(base, values, { preserveState: true });
                            }}
                        >
                            <div className="row g-2">
                                <div className="col-12 col-lg-4">
                                    <SelectInput id="report" label="التقرير" value={values.report} options={reports} onChange={(v) => setValues({ ...values, report: v })} />
                                </div>
                                <div className="col-6 col-lg-2">
                                    <TextInput id="from" name="from" type="date" label="من تاريخ" value={values.from} onChange={(v) => setValues({ ...values, from: v })} />
                                </div>
                                <div className="col-6 col-lg-2">
                                    <TextInput id="to" name="to" type="date" label="إلى تاريخ" value={values.to} onChange={(v) => setValues({ ...values, to: v })} />
                                </div>
                                {values.report === "income_statement" ? (
                                    <div className="col-12 col-lg-4">
                                        <SelectInput id="basis" label="طريقة الاحتساب" value={values.basis} options={bases} onChange={(v) => setValues({ ...values, basis: v })} />
                                    </div>
                                ) : null}
                            </div>
                            <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }}>
                                عرض التقرير
                            </button>
                        </form>
                    </SectionCard>
                </div>

                <div className="d-flex flex-wrap gap-2 d-print-none">
                    {can_export ? <>
                    <a className="btn btn-outline-primary" style={{ minHeight: "48px" }} href={`${base}/export/xlsx?${query}`}>
                        <i className="bi bi-file-earmark-excel me-2" aria-hidden="true" />
                        تنزيل Excel
                    </a>
                    <a className="btn btn-outline-primary" style={{ minHeight: "48px" }} href={`${base}/export/csv?${query}`}>
                        <i className="bi bi-filetype-csv me-2" aria-hidden="true" />
                        تنزيل CSV
                    </a>
                    <button type="button" className="btn btn-outline-primary" style={{ minHeight: "48px" }} onClick={() => window.print()}>
                        <i className="bi bi-printer me-2" aria-hidden="true" />
                        طباعة / حفظ PDF
                    </button>
                    </> : null}
                    <Link href={`/enterprises/${enterprise.public_id}/transactions`} className="btn btn-link" style={{ minHeight: "48px" }}>
                        المعاملات
                    </Link>
                </div>

                {report.sections.map((section) => (
                    <SectionCard key={section.title} title={section.title}>
                        {section.rows.length === 0 ? (
                            <EmptyState title="لا توجد بيانات لهذه الفترة" />
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
                    <p className="mb-0">هذا التقرير محسوب من سجلاتك المحفوظة وليس تقييماً أو موافقة على تمويل.</p>
                </div>
            </div>
        </AppLayout>
    );
}
