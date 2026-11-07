import { offlineReady } from "../../lib/offline";
import { useEffect, useRef, useState } from "react";
import { Link, useForm, usePage } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { SelectInput } from "../../Components/Forms/SelectInput";
import { TextInput } from "../../Components/Forms/TextInput";
import { ItemsEditor, rowsFromItems, toItemsPayload, type ItemRow } from "../../Components/Transactions/ItemsEditor";
import { readDrafts, saveDraft, removeDraft } from "../../lib/offlineDrafts";
import { exactTransactionMoney, normalizeTransactionAmount, transactionMinor, transactionToday } from "../../lib/transactionInput";
import type { BookkeepingOptions, EnterpriseRef, TransactionItemsProps, TransactionRow } from "../../types/bookkeeping";

type Existing = TransactionRow & Partial<TransactionItemsProps>;

export default function Form({ enterprise, options, original, template = null, counterparties = [], requires_approval = false }: {
    enterprise: EnterpriseRef;
    options: BookkeepingOptions;
    original: Existing | null;
    template?: Existing | null;
    counterparties?: string[];
    requires_approval?: boolean;
}) {
    const t = useT();
    const isCorrection = original !== null;
    const source = original ?? template;
    const form = useForm({
        type: source?.type ?? "sale",
        transaction_date: original?.transaction_date ?? transactionToday(),
        amount: source?.amount ?? "",
        // A copied record is new: disabled historical currencies cannot be selected.
        currency: source ? (options.currencies.some(c => c.value === source.currency) ? source.currency : "") : options.default_currency,
        category: source?.category ?? "",
        // Copy invoice details, never copy cash payments into a new transaction.
        payment_status: original?.payment_status ?? (template ? "unpaid" : "paid"),
        paid_amount: "",
        counterparty: source?.counterparty ?? "",
        description: source?.description ?? "",
        correction_reason: "",
        items: rowsFromItems(source?.items) as ItemRow[],
    });
    const { auth } = usePage().props;
    const scope = auth.user?.draft_scope ?? "";
    const [draftId, setDraftId] = useState<string>(() => crypto.randomUUID());
    const loadDrafts = () => readDrafts<typeof form.data>(scope, enterprise.public_id).filter((draft) =>
        /^[0-9a-f-]{36}$/i.test(draft.id) && Object.entries(form.data).every(([key, value]) => {
            const stored = draft.data[key as keyof typeof form.data];
            if (typeof value === "string") return typeof stored === "string";
            return Array.isArray(stored) && stored.length <= 200 && stored.every((row) => row && ["key", "description", "quantity", "unit_price", "total"].every((field) => typeof row[field as keyof ItemRow] === "string") && typeof row.total_manual === "boolean");
        }),
    );
    const [drafts, setDrafts] = useState(loadDrafts);
    const [draftNotice, setDraftNotice] = useState("");
    const persist = () => {
        if (!scope) {
            setDraftNotice(t("transactions.form.draft_scope_error", undefined, "تعذر تحديد الحساب لحفظ المسودة."));
            return false;
        }
        try {
            saveDraft(scope, enterprise.public_id, { id: draftId, updatedAt: new Date().toISOString(), data: form.data });
            setDrafts(loadDrafts());
            setDraftNotice(
                t("transactions.form.draft_saved", undefined, "تم حفظ المسودة على هذا الجهاز. راجعها وأرسلها عند الاتصال."),
            );
            return true;
        } catch {
            setDraftNotice(
                t(
                    "transactions.form.draft_save_failed",
                    undefined,
                    t("transactions.form.draft_storage_error", undefined, "تعذر الحفظ على هذا الجهاز. تحقق من مساحة التخزين وإعدادات المتصفح."),
                ),
            );
            return false;
        }
    };
    const [detailsOpen, setDetailsOpen] = useState(Boolean(source?.counterparty || source?.description || source?.items?.length));
    const formRef = useRef<HTMLFormElement>(null);
    useEffect(() => {
        const keys = Object.keys(form.errors);
        if (keys.some(key => ["counterparty", "description"].includes(key) || key === "items" || key.startsWith("items."))) setDetailsOpen(true);
        if (!keys.length) return;
        const timer = setTimeout(() => formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"], .is-invalid')?.focus(), 0);
        return () => clearTimeout(timer);
    }, [form.errors]);
    const categories = options.categories[form.data.type] ?? [];
    const cancelHref = original ? `/transactions/${original.public_id}` : `/enterprises/${enterprise.public_id}/transactions`;
    const amount = transactionMinor(form.data.amount);
    const previousAmount = original ? transactionMinor(original.amount) : null;
    const previousOutstanding = original ? transactionMinor(original.outstanding) : null;
    const paid = isCorrection ? (previousAmount !== null && previousOutstanding !== null ? previousAmount - previousOutstanding : null) : form.data.payment_status === "paid" ? amount : form.data.payment_status === "unpaid" ? 0n : transactionMinor(form.data.paid_amount);
    const validSummary = amount !== null && amount > 0n && form.data.currency !== "" && paid !== null && paid >= 0n && paid <= amount;
    const partialInvalid = !isCorrection && form.data.payment_status === "partially_paid" && paid !== null && amount !== null && (paid <= 0n || paid >= amount);
    const money = (value: bigint) => <bdi dir="ltr">{exactTransactionMoney(value, form.data.currency)}</bdi>;
    const errors = form.errors as Record<string, string | undefined>;
    const submitLabel = isCorrection
        ? t("transactions.form.save_correction", undefined, "حفظ التصحيح")
        : requires_approval
          ? t("transactions.form.submit_for_approval", undefined, "إرسال للموافقة")
          : t("transactions.form.save_operation", undefined, "حفظ العملية");
    const optionalCount = [Boolean(form.data.counterparty), Boolean(form.data.description), form.data.items.length > 0].filter(Boolean).length;

    const pageTitle = isCorrection
        ? t("transactions.form.correct_title", undefined, "تصحيح عملية")
        : t("transactions.form.add_title", undefined, "إضافة عملية");

    return (
        <AppLayout title={pageTitle}>
        <div className="app-page">
            <PageHeader
                title={pageTitle}
                description={
                    isCorrection
                        ? t(
                              "transactions.form.correct_description",
                              undefined,
                              "يبقى السجل الأصلي والدفعات السابقة محفوظين. أدخل القيم الصحيحة وسبب التصحيح.",
                          )
                        : t(
                              "transactions.form.add_description",
                              { name: enterprise.business_name },
                              `${enterprise.business_name} · أدخل البيانات الأساسية، وأضف التفاصيل عند الحاجة.`,
                          )
                }
            />
            {template && !isCorrection ? (
                <div className="alert alert-info" role="note">
                    {t("transactions.form.template_alert", undefined, "تم نسخ تفاصيل العملية السابقة بتاريخ اليوم دون نسخ دفعاتها أو مستنداتها. راجع المبلغ والعملة وحالة الدفع قبل الحفظ.")}
                    {!options.currencies.some((currency) => currency.value === template.currency)
                        ? t("transactions.form.template_currency_warning", undefined, " عملة العملية الأصلية موقوفة؛ اختر عملة متاحة وراجع المبلغ وأسعار البنود؛ لا يتم تحويل القيم تلقائياً.")
                        : ""}
                </div>
            ) : null}
            <div className="row g-3">
                <div className="col-12 col-lg-8">
                    <SectionCard
                        title={
                            isCorrection
                                ? t("transactions.form.correction_data", undefined, "بيانات التصحيح")
                                : t("transactions.form.operation_data", undefined, "بيانات العملية")
                        }
                    >
                        <form ref={formRef} noValidate onSubmit={event => {
                            event.preventDefault();
                            if (isCorrection && original) {
                                form.transform(({ paid_amount: _ignored, ...rest }) => ({ ...rest, amount: normalizeTransactionAmount(rest.amount), items: toItemsPayload(rest.items) }));
                                form.post(`/transactions/${original.public_id}/correct`);
                            } else {
                                persist();
                                if (!navigator.onLine && !offlineReady()) return;
                                form.transform(({ correction_reason: _ignored, ...rest }) => ({ ...rest, amount: normalizeTransactionAmount(rest.amount), paid_amount: rest.payment_status === "partially_paid" ? normalizeTransactionAmount(rest.paid_amount) : "", client_draft_id: draftId, items: toItemsPayload(rest.items) }));
                                form.post(`/enterprises/${enterprise.public_id}/transactions`, { onSuccess: () => { try { removeDraft(scope, enterprise.public_id, draftId); } catch { /* Retrying a retained draft is idempotent. */ } } });
                            }
                        }}>
                            <fieldset className="mb-3">
                                <legend className="form-label">{t("transactions.form.what_record", undefined, "ماذا تريد أن تسجل؟")}</legend>
                                <div className="row g-2" role="radiogroup" aria-label={t("transactions.type", undefined, "نوع العملية")} aria-describedby={errors.type ? "type-error" : undefined}>
                                    {options.types.map(option => <div key={option.value} className="col-6 col-sm-3">
                                        <input type="radio" className="btn-check" name="type" id={`transaction-type-${option.value}`} value={option.value} checked={form.data.type === option.value} disabled={form.processing} onChange={() => form.setData(data => ({ ...data, type: option.value as typeof data.type, category: "" }))} />
                                        <label htmlFor={`transaction-type-${option.value}`} className={`btn w-100 ${form.data.type === option.value ? "btn-primary" : "btn-outline-secondary"}`} style={{ minHeight: 48 }}>{option.label}</label>
                                    </div>)}
                                </div>
                                {errors.type ? <p id="type-error" className="text-danger small" role="alert">{errors.type}</p> : null}
                            </fieldset>
                            <div className="row g-2">
                                <div className="col-7"><TextInput id="amount" name="amount" label={t("common.amount", undefined, "المبلغ")} value={form.data.amount} onChange={value => form.setData("amount", value)} inputMode="decimal" required placeholder={t("transactions.form.amount_placeholder", undefined, "مثال: 1500")} help={t("transactions.form.amount_help", undefined, "يمكنك استخدام الأرقام العربية أو اللاتينية، مثل ١٥٠٠٫٥٠ أو 1500.50.")} error={errors.amount} /></div>
                                <div className="col-5"><SelectInput id="currency" label={t("transactions.form.currency", undefined, "العملة")} value={form.data.currency} options={options.currencies} placeholder={form.data.currency === "" ? t("transactions.form.choose_currency", undefined, "اختر العملة") : undefined} onChange={value => form.setData("currency", value)} error={errors.currency} required /></div>
                            </div>
                            <div className="row g-2">
                                <div className="col-12 col-sm-6"><TextInput id="transaction_date" name="transaction_date" type="date" label={t("common.date", undefined, "التاريخ")} value={form.data.transaction_date} onChange={value => form.setData("transaction_date", value)} error={errors.transaction_date} required /></div>
                                <div className="col-12 col-sm-6"><SelectInput id="category" label={t("transactions.category", undefined, "التصنيف")} value={form.data.category} options={categories} placeholder={t("transactions.form.choose_category", undefined, "اختر التصنيف المناسب")} onChange={value => form.setData("category", value)} error={errors.category} required /></div>
                            </div>
                            {isCorrection ? <div className="alert alert-info small" role="note">{t("transactions.form.correction_payments_note", undefined, "تبقى الدفعات المسجلة وتواريخها محفوظة. تُحسب حالة الدفع تلقائياً من المبلغ المصحح، ويمكن تسجيل دفعة جديدة من صفحة العملية بعد حفظ التصحيح.")}</div> : <>
                                <SelectInput id="payment_status" label={t("transactions.form.payment_paid", undefined, "هل تم الدفع؟")} value={form.data.payment_status} options={options.payment_statuses} onChange={value => form.setData(data => ({ ...data, payment_status: value as typeof data.payment_status, paid_amount: "" }))} error={errors.payment_status} />
                                {form.data.payment_status === "partially_paid" ? <TextInput id="paid_amount" name="paid_amount" label={t("transactions.form.paid_amount_now", undefined, "المبلغ المدفوع الآن")} value={form.data.paid_amount} onChange={value => form.setData("paid_amount", value)} inputMode="decimal" required help={t("transactions.form.paid_amount_help", undefined, "أدخل الدفعة الحالية فقط؛ يجب أن تكون أقل من المبلغ الكامل.")} error={errors.paid_amount} /> : null}
                            </>}
                            {partialInvalid ? <p className="text-danger small" role="alert">{t("transactions.form.partial_invalid", undefined, "للدفع الجزئي، أدخل مبلغاً أكبر من صفر وأقل من المبلغ الكامل.")}</p> : null}
                            <details className="border rounded-3 p-3 mb-3" open={detailsOpen} onToggle={event => setDetailsOpen(event.currentTarget.open)}>
                                <summary className="fw-semibold" style={{ cursor: "pointer", minHeight: 44 }}>{t("transactions.form.optional_details", undefined, "إضافة تفاصيل (اختياري)")}{optionalCount ? t("transactions.form.optional_filled", { count: optionalCount }, ` · ${optionalCount} أقسام معبأة`) : ""}</summary>
                                <div className="pt-3">
                                    <TextInput id="counterparty" name="counterparty" label={form.data.type === "sale" || form.data.type === "income" ? t("transactions.form.customer_optional", undefined, "اسم الزبون (اختياري)") : t("transactions.form.vendor_optional", undefined, "اسم المورد أو المستفيد (اختياري)")} value={form.data.counterparty} onChange={value => form.setData("counterparty", value)} list="counterparty-suggestions" help={t("transactions.form.counterparty_help", undefined, "اختر اسماً سابقاً أو اكتب اسماً جديداً.")} error={errors.counterparty} />
                                    <datalist id="counterparty-suggestions">{counterparties.map(name => <option key={name} value={name} />)}</datalist>
                                    <ItemsEditor rows={form.data.items} onChange={items => form.setData("items", items)} currency={form.data.currency} amount={normalizeTransactionAmount(form.data.amount)} errors={errors} disabled={form.processing} />
                                    <label htmlFor="description" className="form-label">{t("transactions.form.notes_optional", undefined, "ملاحظات (اختياري)")}</label>
                                    <textarea id="description" className={`form-control${errors.description ? " is-invalid" : ""}`} rows={3} value={form.data.description} aria-invalid={errors.description ? true : undefined} aria-describedby={errors.description ? "description-error" : undefined} onChange={event => form.setData("description", event.target.value)} />
                                    {errors.description ? <p id="description-error" className="invalid-feedback d-block" role="alert">{errors.description}</p> : null}
                                </div>
                            </details>
                            {isCorrection ? <div className="mb-3"><label htmlFor="correction_reason" className="form-label">{t("transactions.form.correction_reason", undefined, "سبب التصحيح")}</label><textarea id="correction_reason" className={`form-control${errors.correction_reason ? " is-invalid" : ""}`} rows={3} value={form.data.correction_reason} aria-invalid={errors.correction_reason ? true : undefined} onChange={event => form.setData("correction_reason", event.target.value)} />{errors.correction_reason ? <p className="invalid-feedback d-block" role="alert">{errors.correction_reason}</p> : null}</div> : null}
                            <section className="bg-body-tertiary border rounded-3 p-3 mb-3" aria-label={t("transactions.form.summary_aria", undefined, "ملخص العملية")} aria-live="polite">
                                <h2 className="h6">{t("transactions.form.review_before", { action: requires_approval && !isCorrection ? t("transactions.form.submit_action", undefined, "الإرسال") : t("transactions.form.save_action", undefined, "الحفظ") }, `راجع قبل ${requires_approval && !isCorrection ? "الإرسال" : "الحفظ"}`)}</h2>
                                <p className="small text-body-secondary">{options.types.find(type => type.value === form.data.type)?.label} · {categories.find(category => category.value === form.data.category)?.label ?? t("transactions.form.choose_category", undefined, "اختر التصنيف")} · {form.data.transaction_date}</p>
                                {validSummary && amount !== null && paid !== null ? <dl className="row mb-0"><dt className="col-5">{t("transactions.form.total_amount", undefined, "المبلغ الكامل")}</dt><dd className="col-7 text-end">{money(amount)}</dd><dt className="col-5">{t("transactions.form.paid", undefined, "المدفوع")}</dt><dd className="col-7 text-end">{money(paid)}</dd><dt className="col-5">{t("transactions.form.remaining", undefined, "المتبقي")}</dt><dd className="col-7 text-end fw-bold mb-0">{money(amount - paid)}</dd></dl> : <p className="small mb-0">{isCorrection ? t("transactions.form.correction_summary_hint", undefined, "راجع المبلغ المصحح؛ يجب ألا يقل عن الدفعات المسجلة.") : t("transactions.form.summary_payment_hint", undefined, "أدخل المبلغ وحالة الدفع لعرض المدفوع والمتبقي.")}</p>}
                                {requires_approval && !isCorrection ? <p className="small text-body-secondary mt-2 mb-0">{t("transactions.form.approval_reports_hint", undefined, "تدخل هذه العملية التقارير بعد اعتمادها من المسؤول.")}</p> : null}
                            </section>
                            {draftNotice ? <p role="status" className="small">{draftNotice}</p> : null}
                            {errors.client_draft_id ? <p className="text-danger small" role="alert">{errors.client_draft_id}</p> : null}
                            {Object.keys(errors).length ? <p role="alert" className="text-danger small">{t("transactions.form.fix_errors", undefined, "راجع الحقول الموضحة أعلاه ثم حاول مجدداً.")}</p> : null}
                                    <div className="d-flex flex-wrap gap-2">
                                <button type="submit" className="btn btn-primary flex-grow-1" style={{ minHeight: 48 }} disabled={form.processing}>{form.processing ? t("transactions.form.saving", undefined, "جارٍ الحفظ…") : submitLabel}</button>
                                {!isCorrection ? <button type="button" className="btn btn-outline-primary" style={{ minHeight: 48 }} disabled={form.processing} onClick={persist}>{t("transactions.form.save_draft", undefined, "حفظ مسودة")}</button> : null}
                                <Link href={cancelHref} className="btn btn-outline-secondary" style={{ minHeight: 48 }}>{t("common.cancel", undefined, "إلغاء")}</Link>
                            </div>
                        </form>
                    </SectionCard>
                </div>
                <aside className="col-12 col-lg-4">
                    <div className="app-form-guide mb-3">
                        <h2>{t("transactions.form.guide_title", undefined, "خطوات بسيطة لتسجيل العملية")}</h2>
                        <ol>
                            <li><strong>{t("transactions.form.guide_step1_title", undefined, "اختر النوع")}</strong><span>{t("transactions.form.guide_step1_body", undefined, "تظهر التصنيفات المناسبة لاختيارك.")}</span></li>
                            <li><strong>{t("transactions.form.guide_step2_title", undefined, "أدخل المبلغ والدفع")}</strong><span>{t("transactions.form.guide_step2_body", undefined, "راجع المدفوع والمتبقي في الملخص.")}</span></li>
                            <li><strong>{t("transactions.form.guide_step3_title", undefined, "أضف التفاصيل عند الحاجة")}</strong><span>{t("transactions.form.guide_step3_body", undefined, "اسم الزبون أو المورد والبنود والملاحظات اختيارية.")}</span></li>
                        </ol>
                    </div>
                    {!isCorrection ? <section className="border rounded-3 p-3" aria-label={t("transactions.form.local_drafts_aria", undefined, "المسودات المحلية")}>
                        <h2 className="h6">{t("transactions.form.device_drafts", { count: drafts.length }, `مسودات هذا الجهاز${drafts.length ? ` (${drafts.length})` : ""}`)}</h2>
                        <p className="small text-body-secondary">{t("transactions.form.drafts_hint", undefined, "احفظ النموذج عند ضعف الاتصال، ثم استعده وراجعه قبل الإرسال. المسودات خاصة بهذا الجهاز ولا تُرسل تلقائياً.")}</p>
                        <p className="small text-body-secondary">{t("transactions.form.drafts_trust", undefined, "استخدم جهازاً موثوقاً واحذف المسودات بعد الانتهاء.")}</p>
                        {drafts.map(draft => <div key={draft.id} className="border-top pt-2 mt-2">
                            <p className="small mb-1">{draft.data.counterparty || draft.data.amount || t("common.draft", undefined, "مسودة")} · {new Date(draft.updatedAt).toLocaleString()}</p>
                            <div className="d-flex gap-2"><button type="button" className="btn btn-sm btn-outline-primary" disabled={form.processing} onClick={() => { form.setData({ ...form.data, ...draft.data }); setDraftId(draft.id); setDetailsOpen(Boolean(draft.data.counterparty || draft.data.description || draft.data.items.length)); setDraftNotice(t("transactions.form.restore_notice", undefined, "راجع البيانات قبل الإرسال. إذا سبق إرسالها، أعد الإرسال دون تعديل أو تحقق من سجل العمليات أولاً.")); }}>{t("transactions.form.restore", undefined, "استعادة")}</button><button type="button" className="btn btn-sm btn-outline-danger" disabled={form.processing} onClick={() => { try { removeDraft(scope, enterprise.public_id, draft.id); setDrafts(loadDrafts()); } catch { setDraftNotice(t("transactions.form.draft_delete_error", undefined, "تعذر حذف المسودة.")); } }}>{t("common.delete", undefined, "حذف")}</button></div>
                        </div>)}
                    </section> : null}
                </aside>
            </div>
        </div>
    </AppLayout>
    );
}
