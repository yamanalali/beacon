import { Link, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { DataTable, type Column, type Paginator } from "../../Components/Data/DataTable";
import { ExportButton } from "../../Components/Data/ExportButton";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { PaymentStatusBadge } from "../../Components/Financial/PaymentStatusBadge";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import type { BookkeepingOptions, EnterpriseRef, TransactionRow } from "../../types/bookkeeping";
import { Money } from "../../Components/Common/Money";
import { currencyName, currencySymbol, formatMoneyIsolated } from "../../lib/money";
import { useT } from "../../Hooks/useT";

type SummaryCurrency = {
    currency: string;
    income: string;
    expense: string;
    remaining: string;
    count: number;
};

type Summary = SummaryCurrency & { by_currency: SummaryCurrency[] };

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
        <span className={`app-tx-amount ${row.is_inflow ? "is-in" : "is-out"}`}>
            <Money amount={row.amount} currency={row.currency} signed={row.is_inflow ? "in" : "out"} />
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

function hasActiveFilters(f: Filters): boolean {
    return Boolean(f.search || f.type || f.payment_status || f.from || f.to || f.show_corrected);
}

export default function Index({
    enterprise,
    transactions,
    summary = null,
    filters,
    options,
    can_create,
    can_export = false,
}: {
    enterprise: EnterpriseRef;
    transactions: Paginator<TransactionRow>;
    summary?: Summary | null;
    filters: Filters;
    options: BookkeepingOptions;
    can_create: boolean;
    can_export?: boolean;
}) {
    const t = useT();
    const [values, setValues] = useState<Filters>(filters);
    const [showFilters, setShowFilters] = useState(hasActiveFilters(filters));
    const base = `/enterprises/${enterprise.public_id}/transactions`;
    const money = formatMoneyIsolated;
    const extraCurrencies = (summary?.by_currency ?? []).filter((c) => c.currency !== summary?.currency && c.count > 0);
    const remainingTone = summary ? (Number(summary.remaining) > 0 ? "is-up" : Number(summary.remaining) < 0 ? "is-down" : "") : "";

    const columns: Column<TransactionRow>[] = useMemo(
        () => [
            { key: "transaction_date", header: t("common.date", undefined, "التاريخ"), cell: (row) => <bdi dir="ltr">{row.transaction_date}</bdi>, numeric: true },
            {
                key: "type_label",
                header: t("transactions.operation", undefined, "العملية"),
                primary: true,
                cell: (row) => (
                    <span className="app-tx-primary">
                        <span className={`app-tx-dot ${row.is_inflow ? "is-in" : "is-out"}`} aria-hidden="true" />
                        <span>
                            <span className="d-block fw-semibold">{row.type_label}</span>
                            <span className="small text-body-secondary">{row.category_label}</span>
                        </span>
                    </span>
                ),
            },
            { key: "category_label", header: t("transactions.category", undefined, "التصنيف"), cell: (row) => row.category_label, hideOnMobile: true, defaultHidden: true },
            { key: "counterparty", header: t("transactions.counterparty", undefined, "الجهة"), cell: (row) => row.counterparty ?? "—" },
            { key: "amount", header: t("common.amount", undefined, "المبلغ"), cell: (row) => <Amount row={row} />, numeric: true },
            {
                key: "outstanding",
                header: t("transactions.outstanding", undefined, "المتبقي للدفع"),
                cell: (row) => (Number(row.outstanding) > 0 ? <Money amount={row.outstanding} currency={row.currency} /> : <span className="text-body-secondary">—</span>),
                numeric: true,
                hideOnMobile: true,
            },
            {
                key: "payment_status",
                header: t("transactions.payment_status", undefined, "حالة الدفع"),
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
                        {t("common.details", undefined, "التفاصيل")}
                    </Link>
                ),
            },
        ],
        [t],
    );

    const visit = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string> = {};
        if (merged.search) query.search = merged.search;
        if (merged.type) query.type = merged.type;
        if (merged.payment_status) query.payment_status = merged.payment_status;
        if (merged.from) query.from = merged.from;
        if (merged.to) query.to = merged.to;
        if (merged.show_corrected) query.show_corrected = "1";
        router.get(base, query, { preserveState: true, replace: true, only: ["transactions", "filters", "summary"] });
    };

    const apply = () => visit(values);

    const clearFilters = () => {
        const empty: Filters = { search: "", type: "", payment_status: "", from: "", to: "", show_corrected: false };
        setValues(empty);
        router.get(base, {}, { preserveState: true, replace: true, only: ["transactions", "filters", "summary"] });
    };

    const quickTypes = [
        { value: "", label: t("common.all", undefined, "الكل") },
        { value: "sale", label: t("transactions.sale", undefined, "مبيعات") },
        { value: "income", label: t("transactions.income", undefined, "دخل") },
        { value: "expense", label: t("transactions.expense", undefined, "مصروف") },
        { value: "purchase", label: t("transactions.purchase", undefined, "شراء") },
    ];

    return (
        <AppLayout title={t("transactions.title", undefined, "المعاملات")}>
            <div className="app-page app-tx-page d-flex flex-column gap-3">
                <div className="app-page-toolbar flex-wrap gap-3 mb-0">
                    <PageHeader
                        title={t("transactions.title", undefined, "المعاملات")}
                        description={t("transactions.description", undefined, "اطّلع على الدخل والمصروف والمتبقي، ثم راجع العمليات بسرعة.")}
                    />
                    <div className="d-flex flex-wrap gap-2">
                        {can_create ? (
                            <Link href={`${base}/create`} className="btn btn-primary" style={{ minHeight: "48px" }}>
                                <i className="bi bi-plus-lg me-2" aria-hidden="true" />
                                {t("transactions.add_operation", undefined, "إضافة عملية")}
                            </Link>
                        ) : null}
                        {can_export ? <ExportButton baseUrl={`${base}/export`} formats={["csv", "xlsx"]} /> : null}
                    </div>
                </div>

                {summary ? (
                    <section className="app-money-strip" aria-label={t("transactions.summary_aria", undefined, "ملخص المعاملات")} data-testid="transactions-summary">
                        <div className={`app-money-strip__remain ${remainingTone}`}>
                            <span className="app-money-strip__label">{t("transactions.remaining", undefined, "المتبقي")}</span>
                            <p className="app-money-strip__value">
                                <bdi>{money(summary.remaining, summary.currency)}</bdi>
                            </p>
                            <span className="app-money-strip__hint">
                                {currencyName(summary.currency)} · <bdi dir="ltr">{summary.count}</bdi> {t("transactions.posted_count", undefined, "عملية معتمدة")}
                            </span>
                        </div>
                        <div className="app-money-strip__split">
                            <div className="app-money-strip__metric is-in">
                                <span className="app-money-strip__label">
                                    <i className="bi bi-arrow-down-left me-1" aria-hidden="true" />
                                    {t("transactions.income", undefined, "الدخل")}
                                </span>
                                <strong>
                                    <bdi>{money(summary.income, summary.currency)}</bdi>
                                </strong>
                            </div>
                            <div className="app-money-strip__metric is-out">
                                <span className="app-money-strip__label">
                                    <i className="bi bi-arrow-up-right me-1" aria-hidden="true" />
                                    {t("transactions.expense", undefined, "المصروف")}
                                </span>
                                <strong>
                                    <bdi>{money(summary.expense, summary.currency)}</bdi>
                                </strong>
                            </div>
                        </div>
                        {extraCurrencies.length > 0 ? (
                            <div className="app-money-strip__extra" data-testid="transactions-summary-extra">
                                {extraCurrencies.map((c) => (
                                    <div key={c.currency} className="app-money-strip__extra-card">
                                        <span className="fw-semibold">
                                            {currencyName(c.currency)} <bdi className="text-body-secondary">({currencySymbol(c.currency)})</bdi>
                                        </span>
                                        <span>
                                            {t("transactions.income", undefined, "دخل")} <bdi>{money(c.income, c.currency)}</bdi>
                                        </span>
                                        <span>
                                            {t("transactions.expense", undefined, "مصروف")} <bdi>{money(c.expense, c.currency)}</bdi>
                                        </span>
                                        <span className="fw-semibold">
                                            {t("transactions.remaining", undefined, "متبقي")} <bdi>{money(c.remaining, c.currency)}</bdi>
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : null}
                    </section>
                ) : null}

                <section className="app-tx-toolbar" aria-label={t("filters.apply", undefined, "تصفية سريعة")}>
                    <div className="app-tx-chips" role="group" aria-label={t("transactions.type", undefined, "نوع العملية")}>
                        {quickTypes.map((chip) => (
                            <button
                                key={chip.value || "all"}
                                type="button"
                                className={`app-tx-chip${filters.type === chip.value ? " is-active" : ""}`}
                                aria-pressed={filters.type === chip.value}
                                onClick={() => {
                                    setValues((v) => ({ ...v, type: chip.value }));
                                    visit({ type: chip.value });
                                }}
                            >
                                {chip.label}
                            </button>
                        ))}
                    </div>
                    <div className="app-tx-toolbar__actions">
                        <button
                            type="button"
                            className={`btn btn-outline-secondary${showFilters || hasActiveFilters(filters) ? " active" : ""}`}
                            style={{ minHeight: "44px" }}
                            aria-expanded={showFilters}
                            onClick={() => setShowFilters((v) => !v)}
                        >
                            <i className="bi bi-funnel me-1" aria-hidden="true" />
                            {t("common.filter", undefined, "تصفية")}
                            {hasActiveFilters(filters) ? <span className="app-tx-filter-dot" aria-hidden="true" /> : null}
                        </button>
                        {hasActiveFilters(filters) ? (
                            <button type="button" className="btn btn-link" style={{ minHeight: "44px" }} onClick={clearFilters}>
                                {t("filters.clear", undefined, "مسح التصفية")}
                            </button>
                        ) : null}
                    </div>
                </section>

                {showFilters ? (
                    <form
                        className="app-tx-filters"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                    >
                        <div className="row g-2 align-items-end">
                            <div className="col-12 col-lg-4">
                                <TextInput id="search" name="search" label={t("transactions.search", undefined, "بحث (الجهة أو الوصف)")} value={values.search} onChange={(v) => setValues({ ...values, search: v })} />
                            </div>
                            <div className="col-6 col-lg-2">
                                <SelectInput id="payment_status" label={t("transactions.payment_status", undefined, "حالة الدفع")} value={values.payment_status} options={options.payment_statuses} placeholder={t("common.all", undefined, "الكل")} onChange={(v) => setValues({ ...values, payment_status: v })} />
                            </div>
                            <div className="col-6 col-lg-2">
                                <TextInput id="from" name="from" type="date" label={t("filters.date_from", undefined, "من تاريخ")} value={values.from} onChange={(v) => setValues({ ...values, from: v })} />
                            </div>
                            <div className="col-6 col-lg-2">
                                <TextInput id="to" name="to" type="date" label={t("filters.date_to", undefined, "إلى تاريخ")} value={values.to} onChange={(v) => setValues({ ...values, to: v })} />
                            </div>
                            <div className="col-6 col-lg-2">
                                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }}>
                                    {t("filters.apply", undefined, "تطبيق")}
                                </button>
                            </div>
                        </div>
                        <div className="form-check mt-2 mb-0">
                            <input
                                id="show_corrected"
                                type="checkbox"
                                className="form-check-input"
                                checked={values.show_corrected}
                                onChange={(e) => setValues({ ...values, show_corrected: e.target.checked })}
                            />
                            <label htmlFor="show_corrected" className="form-check-label">
                                {t("transactions.show_corrected", undefined, "إظهار السجلات الأصلية التي تم تصحيحها")}
                            </label>
                        </div>
                    </form>
                ) : null}

                <DataTable
                    caption={t("transactions.title", undefined, "المعاملات")}
                    columns={columns}
                    rowKey={(row) => row.public_id}
                    rowLabel={(row) => `${row.type_label} ${row.transaction_date}`}
                    onRowClick={(row) => router.visit(`/transactions/${row.public_id}`)}
                    emptyTitle={t("transactions.empty_title", undefined, "لا توجد عمليات مسجلة")}
                    emptyDescription={hasActiveFilters(filters) ? t("transactions.empty_filtered", undefined, "جرّب مسح التصفية أو تغيير الفترة.") : t("transactions.empty_cta_hint", undefined, "أضف أول عملية ليظهر الملخص هنا.")}
                    server={{ paginator: transactions, url: base, query: filterQuery(filters), only: ["transactions", "filters", "summary"] }}
                />
            </div>
        </AppLayout>
    );
}
