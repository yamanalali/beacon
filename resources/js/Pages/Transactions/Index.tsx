import { Link, router } from "@inertiajs/react";
import { useState } from "react";
import { DataTable, type Column, type Paginator } from "../../Components/Data/DataTable";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { PaymentStatusBadge } from "../../Components/Financial/PaymentStatusBadge";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import type { BookkeepingOptions, EnterpriseRef, TransactionRow } from "../../types/bookkeeping";

type Filters = {
    search: string;
    type: string;
    payment_status: string;
    from: string;
    to: string;
    show_corrected: boolean;
};

function Amount({ row }: { row: TransactionRow }) {
    return (
        <span className="fw-semibold" dir="ltr" style={{ color: row.is_inflow ? "var(--app-success)" : "var(--app-text)" }}>
            {row.is_inflow ? "+" : "−"}
            {row.amount} {row.currency}
        </span>
    );
}

function filterQuery(f: Filters): Record<string, string | undefined> {
    return {
        search: f.search || undefined,
        type: f.type || undefined,
        payment_status: f.payment_status || undefined,
        from: f.from || undefined,
        to: f.to || undefined,
        show_corrected: f.show_corrected ? "1" : undefined,
    };
}

const columns: Column<TransactionRow>[] = [
    { key: "transaction_date", header: "التاريخ", cell: (row) => <bdi dir="ltr">{row.transaction_date}</bdi>, numeric: true },
    { key: "type_label", header: "النوع", cell: (row) => `${row.type_label} · ${row.category_label}`, primary: true },
    { key: "category_label", header: "التصنيف", cell: (row) => row.category_label, hideOnMobile: true, defaultHidden: true },
    { key: "counterparty", header: "الجهة", cell: (row) => row.counterparty ?? "—" },
    { key: "amount", header: "المبلغ", cell: (row) => <Amount row={row} />, numeric: true },
    { key: "outstanding", header: "المتبقي", cell: (row) => <bdi dir="ltr">{row.outstanding}</bdi>, numeric: true, hideOnMobile: true },
    {
        key: "payment_status",
        header: "حالة الدفع",
        cell: (row) => (
            <div className="d-flex flex-wrap gap-1">
                <PaymentStatusBadge status={row.payment_status} label={row.payment_status_label} />
                {row.is_superseded ? <StatusBadge label={row.status_label} tone="neutral" /> : null}
            </div>
        ),
    },
    {
        key: "actions",
        header: "",
        hideable: false,
        hideOnMobile: true,
        cell: (row) => (
            <Link href={`/transactions/${row.public_id}`} className="btn btn-outline-primary btn-sm" style={{ minHeight: "40px" }} onClick={(e) => e.stopPropagation()}>
                التفاصيل
            </Link>
        ),
    },
];

export default function Index({
    enterprise,
    transactions,
    filters,
    options,
    can_create,
}: {
    enterprise: EnterpriseRef;
    transactions: Paginator<TransactionRow>;
    filters: Filters;
    options: BookkeepingOptions;
    can_create: boolean;
}) {
    const [values, setValues] = useState<Filters>(filters);
    const [showFilters, setShowFilters] = useState(false);
    const base = `/enterprises/${enterprise.public_id}/transactions`;

    const apply = () => {
        const query: Record<string, string> = {};
        if (values.search) query.search = values.search;
        if (values.type) query.type = values.type;
        if (values.payment_status) query.payment_status = values.payment_status;
        if (values.from) query.from = values.from;
        if (values.to) query.to = values.to;
        if (values.show_corrected) query.show_corrected = "1";
        router.get(base, query, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="المعاملات">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="المعاملات" description={`${enterprise.business_name} — المبيعات والمشتريات والمصاريف والدفعات.`} />

                <div className="d-flex flex-wrap gap-2">
                    {can_create ? (
                        <Link href={`${base}/create`} className="btn btn-primary" style={{ minHeight: "48px" }}>
                            <i className="bi bi-plus-lg me-2" aria-hidden="true" />
                            إضافة عملية
                        </Link>
                    ) : null}
                    <button
                        type="button"
                        className="btn btn-outline-secondary d-lg-none"
                        style={{ minHeight: "48px" }}
                        aria-expanded={showFilters}
                        onClick={() => setShowFilters((v) => !v)}
                    >
                        <i className="bi bi-funnel me-2" aria-hidden="true" />
                        البحث والتصفية
                    </button>
                    <Link href={`/enterprises/${enterprise.public_id}`} className="btn btn-link" style={{ minHeight: "48px" }}>
                        ملف المنشأة
                    </Link>
                </div>

                <div className={showFilters ? "" : "d-none d-lg-block"}>
                    <SectionCard title="البحث والتصفية">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                apply();
                            }}
                        >
                            <div className="row g-2">
                                <div className="col-12 col-lg-4">
                                    <TextInput id="search" name="search" label="بحث (الجهة أو الوصف)" value={values.search} onChange={(v) => setValues({ ...values, search: v })} />
                                </div>
                                <div className="col-6 col-lg-2">
                                    <SelectInput id="type" label="النوع" value={values.type} options={options.types} placeholder="الكل" onChange={(v) => setValues({ ...values, type: v })} />
                                </div>
                                <div className="col-6 col-lg-2">
                                    <SelectInput id="payment_status" label="حالة الدفع" value={values.payment_status} options={options.payment_statuses} placeholder="الكل" onChange={(v) => setValues({ ...values, payment_status: v })} />
                                </div>
                                <div className="col-6 col-lg-2">
                                    <TextInput id="from" name="from" type="date" label="من تاريخ" value={values.from} onChange={(v) => setValues({ ...values, from: v })} />
                                </div>
                                <div className="col-6 col-lg-2">
                                    <TextInput id="to" name="to" type="date" label="إلى تاريخ" value={values.to} onChange={(v) => setValues({ ...values, to: v })} />
                                </div>
                            </div>
                            <div className="form-check mb-3">
                                <input
                                    id="show_corrected"
                                    type="checkbox"
                                    className="form-check-input"
                                    checked={values.show_corrected}
                                    onChange={(e) => setValues({ ...values, show_corrected: e.target.checked })}
                                />
                                <label htmlFor="show_corrected" className="form-check-label">
                                    إظهار السجلات الأصلية التي تم تصحيحها
                                </label>
                            </div>
                            <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }}>
                                عرض النتائج
                            </button>
                        </form>
                    </SectionCard>
                </div>

                <DataTable
                    caption="المعاملات"
                    columns={columns}
                    rowKey={(row) => row.public_id}
                    rowLabel={(row) => `${row.type_label} ${row.transaction_date}`}
                    onRowClick={(row) => router.visit(`/transactions/${row.public_id}`)}
                    emptyTitle="لا توجد عمليات مسجلة"
                    server={{ paginator: transactions, url: base, query: filterQuery(filters), only: ["transactions", "filters"] }}
                />
            </div>
        </AppLayout>
    );
}
