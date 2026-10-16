import { Link, useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import { ItemsEditor, rowsFromItems, toItemsPayload, type ItemRow } from "../../Components/Transactions/ItemsEditor";
import type {
    BookkeepingOptions,
    EnterpriseRef,
    TransactionItemsProps,
    TransactionRow,
} from "../../types/bookkeeping";

export default function Form({
    enterprise,
    options,
    original,
}: {
    enterprise: EnterpriseRef;
    options: BookkeepingOptions;
    original: (TransactionRow & Partial<TransactionItemsProps>) | null;
}) {
    const isCorrection = original !== null;
    const form = useForm({
        type: original?.type ?? "sale",
        transaction_date:
            original?.transaction_date ?? new Date().toISOString().slice(0, 10),
        amount: original?.amount ?? "",
        currency: original?.currency ?? options.default_currency,
        category: original?.category ?? "",
        payment_status:
            original?.payment_status === "partially_paid"
                ? "partially_paid"
                : (original?.payment_status ?? "paid"),
        paid_amount: "",
        counterparty: original?.counterparty ?? "",
        description: original?.description ?? "",
        correction_reason: "",
        // A correction replaces the item set, so start from the original items.
        items: rowsFromItems(original?.items) as ItemRow[],
    });

    const categories = options.categories[form.data.type] ?? [];
    const cancelHref = isCorrection
        ? `/transactions/${original.public_id}`
        : `/enterprises/${enterprise.public_id}/transactions`;

    return (
        <AppLayout title={isCorrection ? "تصحيح عملية" : "إضافة عملية"}>
            <div className="app-page">
                <PageHeader
                    title={isCorrection ? "تصحيح عملية" : "إضافة عملية"}
                    description={
                        isCorrection
                            ? "سيبقى السجل الأصلي محفوظاً كما هو، وسيُحفظ سجل جديد بالقيم الصحيحة مع سبب التصحيح."
                            : enterprise.business_name
                    }
                />
                <div className="row">
                    <div className="col-12 col-lg-8">
                        <SectionCard
                            title={
                                isCorrection
                                    ? "بيانات التصحيح"
                                    : "تفاصيل العملية"
                            }
                        >
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (isCorrection) {
                                        form.transform((d) => ({ ...d, items: toItemsPayload(d.items) }));
                                        form.post(
                                            `/transactions/${original.public_id}/correct`,
                                        );
                                    } else {
                                        form.transform(
                                            ({
                                                correction_reason: _ignored,
                                                ...rest
                                            }) => ({ ...rest, items: toItemsPayload(rest.items) }),
                                        );
                                        form.post(
                                            `/enterprises/${enterprise.public_id}/transactions`,
                                        );
                                    }
                                }}
                            >
                                <SelectInput
                                    id="type"
                                    label="نوع العملية"
                                    value={form.data.type}
                                    options={options.types}
                                    onChange={(v) =>
                                        form.setData((d) => ({
                                            ...d,
                                            type: v as typeof d.type,
                                            category: "",
                                        }))
                                    }
                                    error={form.errors.type}
                                />
                                <TextInput
                                    id="transaction_date"
                                    name="transaction_date"
                                    type="date"
                                    label="التاريخ"
                                    value={form.data.transaction_date}
                                    onChange={(v) =>
                                        form.setData("transaction_date", v)
                                    }
                                    error={form.errors.transaction_date}
                                />
                                <div className="row g-2">
                                    <div className="col-7">
                                        <TextInput
                                            id="amount"
                                            name="amount"
                                            type="text"
                                            label="المبلغ"
                                            value={form.data.amount}
                                            onChange={(v) =>
                                                form.setData("amount", v)
                                            }
                                            error={form.errors.amount}
                                            help="مثال: 15000 أو 15000.50"
                                        />
                                    </div>
                                    <div className="col-5">
                                        <SelectInput
                                            id="currency"
                                            label="العملة"
                                            value={form.data.currency}
                                            options={options.currencies}
                                            onChange={(v) =>
                                                form.setData("currency", v)
                                            }
                                            error={form.errors.currency}
                                        />
                                    </div>
                                </div>
                                <SelectInput
                                    id="category"
                                    label="التصنيف"
                                    value={form.data.category}
                                    options={categories}
                                    placeholder="اختر التصنيف"
                                    onChange={(v) =>
                                        form.setData("category", v)
                                    }
                                    error={form.errors.category}
                                />
                                <SelectInput
                                    id="payment_status"
                                    label="هل تم الدفع؟"
                                    value={form.data.payment_status}
                                    options={options.payment_statuses}
                                    onChange={(v) =>
                                        form.setData(
                                            "payment_status",
                                            v as typeof form.data.payment_status,
                                        )
                                    }
                                    error={form.errors.payment_status}
                                />
                                {form.data.payment_status ===
                                "partially_paid" ? (
                                    <TextInput
                                        id="paid_amount"
                                        name="paid_amount"
                                        label="المبلغ المدفوع الآن"
                                        value={form.data.paid_amount}
                                        onChange={(v) =>
                                            form.setData("paid_amount", v)
                                        }
                                        error={form.errors.paid_amount}
                                    />
                                ) : null}
                                {form.data.payment_status !== "paid" ? (
                                    <p className="small text-body-secondary">
                                        يمكنك تسجيل الدفعات اللاحقة من صفحة
                                        العملية، ولن تُحسب العملية مرتين.
                                    </p>
                                ) : null}
                                <TextInput
                                    id="counterparty"
                                    name="counterparty"
                                    label="الزبون أو المورد (اختياري)"
                                    value={form.data.counterparty}
                                    onChange={(v) =>
                                        form.setData("counterparty", v)
                                    }
                                    error={form.errors.counterparty}
                                />
                                <ItemsEditor
                                    rows={form.data.items}
                                    onChange={(items) => form.setData("items", items)}
                                    currency={form.data.currency}
                                    amount={form.data.amount}
                                    errors={form.errors as Record<string, string | undefined>}
                                    disabled={form.processing}
                                />
                                <div className="mb-3">
                                    <label
                                        htmlFor="description"
                                        className="form-label mb-1 fw-semibold"
                                    >
                                        ملاحظات (اختياري)
                                    </label>
                                    <textarea
                                        id="description"
                                        className="form-control"
                                        style={{
                                            minHeight: "96px",
                                            fontSize: "16px",
                                        }}
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                "description",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                {isCorrection ? (
                                    <div className="mb-3">
                                        <label
                                            htmlFor="correction_reason"
                                            className="form-label mb-1 fw-semibold"
                                        >
                                            سبب التصحيح
                                        </label>
                                        <textarea
                                            id="correction_reason"
                                            className={`form-control${form.errors.correction_reason ? " is-invalid" : ""}`}
                                            style={{
                                                minHeight: "80px",
                                                fontSize: "16px",
                                            }}
                                            value={form.data.correction_reason}
                                            onChange={(e) =>
                                                form.setData(
                                                    "correction_reason",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.correction_reason ? (
                                            <div className="invalid-feedback d-block">
                                                {form.errors.correction_reason}
                                            </div>
                                        ) : null}
                                    </div>
                                ) : null}
                                <div className="d-flex flex-wrap gap-2">
                                    <button
                                        type="submit"
                                        className="btn btn-primary flex-grow-1 flex-lg-grow-0"
                                        style={{ minHeight: "48px" }}
                                        disabled={form.processing}
                                    >
                                        {isCorrection
                                            ? "حفظ التصحيح"
                                            : "حفظ العملية"}
                                    </button>
                                    <Link
                                        href={cancelHref}
                                        className="btn btn-outline-secondary"
                                        style={{ minHeight: "48px" }}
                                    >
                                        إلغاء
                                    </Link>
                                </div>
                            </form>
                        </SectionCard>
                    </div>
                    <aside className="col-12 col-lg-4 mt-3 mt-lg-0">
                        <div className="app-form-guide">
                            <span className="app-form-guide__icon">
                                <i
                                    className="bi bi-journal-check"
                                    aria-hidden="true"
                                />
                            </span>
                            <h2>سجلّات أوضح، قرارات أفضل</h2>
                            <p>
                                أدخل تفاصيل العملية كما تظهر في مستنداتك، وحدّد
                                حالة الدفع بدقة.
                            </p>
                            <ol>
                                <li>
                                    <strong>نوع العملية</strong>
                                    <span>
                                        اختر البيع أو الشراء أو المصروف المناسب.
                                    </span>
                                </li>
                                <li>
                                    <strong>المبلغ والعملة</strong>
                                    <span>راجع القيمة قبل حفظ العملية.</span>
                                </li>
                                <li>
                                    <strong>حالة الدفع</strong>
                                    <span>
                                        حدّد إن كانت العملية مدفوعة أو آجلة.
                                    </span>
                                </li>
                            </ol>
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
