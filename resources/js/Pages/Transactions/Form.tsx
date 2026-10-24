import { offlineReady } from "../../lib/offline";
import { useEffect, useRef, useState } from "react";
import { Link, useForm, usePage } from "@inertiajs/react";
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
        if (!scope) { setDraftNotice("تعذر تحديد الحساب لحفظ المسودة."); return false; }
        try {
            saveDraft(scope, enterprise.public_id, { id: draftId, updatedAt: new Date().toISOString(), data: form.data });
            setDrafts(loadDrafts());
            setDraftNotice("تم حفظ المسودة على هذا الجهاز. راجعها وأرسلها عند الاتصال.");
            return true;
        } catch { setDraftNotice("تعذر الحفظ على هذا الجهاز. تحقق من مساحة التخزين وإعدادات المتصفح."); return false; }
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
    const submitLabel = isCorrection ? "حفظ التصحيح" : requires_approval ? "إرسال للموافقة" : "حفظ العملية";
    const optionalCount = [Boolean(form.data.counterparty), Boolean(form.data.description), form.data.items.length > 0].filter(Boolean).length;

    return <AppLayout title={isCorrection ? "تصحيح عملية" : "إضافة عملية"}>
        <div className="app-page">
            <PageHeader title={isCorrection ? "تصحيح عملية" : "إضافة عملية"} description={isCorrection ? "يبقى السجل الأصلي والدفعات السابقة محفوظين. أدخل القيم الصحيحة وسبب التصحيح." : `${enterprise.business_name} · أدخل البيانات الأساسية، وأضف التفاصيل عند الحاجة.`} />
            {template && !isCorrection ? <div className="alert alert-info" role="note">تم نسخ تفاصيل العملية السابقة بتاريخ اليوم دون نسخ دفعاتها أو مستنداتها. راجع المبلغ والعملة وحالة الدفع قبل الحفظ.{!options.currencies.some(currency => currency.value === template.currency) ? " عملة العملية الأصلية موقوفة؛ اختر عملة متاحة وراجع المبلغ وأسعار البنود؛ لا يتم تحويل القيم تلقائياً." : ""}</div> : null}
            <div className="row g-3">
                <div className="col-12 col-lg-8">
                    <SectionCard title={isCorrection ? "بيانات التصحيح" : "بيانات العملية"}>
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
                                <legend className="form-label">ماذا تريد أن تسجل؟</legend>
                                <div className="row g-2" role="radiogroup" aria-label="نوع العملية" aria-describedby={errors.type ? "type-error" : undefined}>
                                    {options.types.map(option => <div key={option.value} className="col-6 col-sm-3">
                                        <input type="radio" className="btn-check" name="type" id={`transaction-type-${option.value}`} value={option.value} checked={form.data.type === option.value} disabled={form.processing} onChange={() => form.setData(data => ({ ...data, type: option.value as typeof data.type, category: "" }))} />
                                        <label htmlFor={`transaction-type-${option.value}`} className={`btn w-100 ${form.data.type === option.value ? "btn-primary" : "btn-outline-secondary"}`} style={{ minHeight: 48 }}>{option.label}</label>
                                    </div>)}
                                </div>
                                {errors.type ? <p id="type-error" className="text-danger small" role="alert">{errors.type}</p> : null}
                            </fieldset>
                            <div className="row g-2">
                                <div className="col-7"><TextInput id="amount" name="amount" label="المبلغ" value={form.data.amount} onChange={value => form.setData("amount", value)} inputMode="decimal" required placeholder="مثال: 1500" help="يمكنك استخدام الأرقام العربية أو اللاتينية، مثل ١٥٠٠٫٥٠ أو 1500.50." error={errors.amount} /></div>
                                <div className="col-5"><SelectInput id="currency" label="العملة" value={form.data.currency} options={options.currencies} placeholder={form.data.currency === "" ? "اختر العملة" : undefined} onChange={value => form.setData("currency", value)} error={errors.currency} required /></div>
                            </div>
                            <div className="row g-2">
                                <div className="col-12 col-sm-6"><TextInput id="transaction_date" name="transaction_date" type="date" label="التاريخ" value={form.data.transaction_date} onChange={value => form.setData("transaction_date", value)} error={errors.transaction_date} required /></div>
                                <div className="col-12 col-sm-6"><SelectInput id="category" label="التصنيف" value={form.data.category} options={categories} placeholder="اختر التصنيف المناسب" onChange={value => form.setData("category", value)} error={errors.category} required /></div>
                            </div>
                            {isCorrection ? <div className="alert alert-info small" role="note">تبقى الدفعات المسجلة وتواريخها محفوظة. تُحسب حالة الدفع تلقائياً من المبلغ المصحح، ويمكن تسجيل دفعة جديدة من صفحة العملية بعد حفظ التصحيح.</div> : <>
                                <SelectInput id="payment_status" label="هل تم الدفع؟" value={form.data.payment_status} options={options.payment_statuses} onChange={value => form.setData(data => ({ ...data, payment_status: value as typeof data.payment_status, paid_amount: "" }))} error={errors.payment_status} />
                                {form.data.payment_status === "partially_paid" ? <TextInput id="paid_amount" name="paid_amount" label="المبلغ المدفوع الآن" value={form.data.paid_amount} onChange={value => form.setData("paid_amount", value)} inputMode="decimal" required help="أدخل الدفعة الحالية فقط؛ يجب أن تكون أقل من المبلغ الكامل." error={errors.paid_amount} /> : null}
                            </>}
                            {partialInvalid ? <p className="text-danger small" role="alert">للدفع الجزئي، أدخل مبلغاً أكبر من صفر وأقل من المبلغ الكامل.</p> : null}
                            <details className="border rounded-3 p-3 mb-3" open={detailsOpen} onToggle={event => setDetailsOpen(event.currentTarget.open)}>
                                <summary className="fw-semibold" style={{ cursor: "pointer", minHeight: 44 }}>إضافة تفاصيل (اختياري){optionalCount ? ` · ${optionalCount} أقسام معبأة` : ""}</summary>
                                <div className="pt-3">
                                    <TextInput id="counterparty" name="counterparty" label={form.data.type === "sale" || form.data.type === "income" ? "اسم الزبون (اختياري)" : "اسم المورد أو المستفيد (اختياري)"} value={form.data.counterparty} onChange={value => form.setData("counterparty", value)} list="counterparty-suggestions" help="اختر اسماً سابقاً أو اكتب اسماً جديداً." error={errors.counterparty} />
                                    <datalist id="counterparty-suggestions">{counterparties.map(name => <option key={name} value={name} />)}</datalist>
                                    <ItemsEditor rows={form.data.items} onChange={items => form.setData("items", items)} currency={form.data.currency} amount={normalizeTransactionAmount(form.data.amount)} errors={errors} disabled={form.processing} />
                                    <label htmlFor="description" className="form-label">ملاحظات (اختياري)</label>
                                    <textarea id="description" className={`form-control${errors.description ? " is-invalid" : ""}`} rows={3} value={form.data.description} aria-invalid={errors.description ? true : undefined} aria-describedby={errors.description ? "description-error" : undefined} onChange={event => form.setData("description", event.target.value)} />
                                    {errors.description ? <p id="description-error" className="invalid-feedback d-block" role="alert">{errors.description}</p> : null}
                                </div>
                            </details>
                            {isCorrection ? <div className="mb-3"><label htmlFor="correction_reason" className="form-label">سبب التصحيح</label><textarea id="correction_reason" className={`form-control${errors.correction_reason ? " is-invalid" : ""}`} rows={3} value={form.data.correction_reason} aria-invalid={errors.correction_reason ? true : undefined} onChange={event => form.setData("correction_reason", event.target.value)} />{errors.correction_reason ? <p className="invalid-feedback d-block" role="alert">{errors.correction_reason}</p> : null}</div> : null}
                            <section className="bg-body-tertiary border rounded-3 p-3 mb-3" aria-label="ملخص العملية" aria-live="polite">
                                <h2 className="h6">راجع قبل {requires_approval && !isCorrection ? "الإرسال" : "الحفظ"}</h2>
                                <p className="small text-body-secondary">{options.types.find(type => type.value === form.data.type)?.label} · {categories.find(category => category.value === form.data.category)?.label ?? "اختر التصنيف"} · {form.data.transaction_date}</p>
                                {validSummary && amount !== null && paid !== null ? <dl className="row mb-0"><dt className="col-5">المبلغ الكامل</dt><dd className="col-7 text-end">{money(amount)}</dd><dt className="col-5">المدفوع</dt><dd className="col-7 text-end">{money(paid)}</dd><dt className="col-5">المتبقي</dt><dd className="col-7 text-end fw-bold mb-0">{money(amount - paid)}</dd></dl> : <p className="small mb-0">{isCorrection ? "راجع المبلغ المصحح؛ يجب ألا يقل عن الدفعات المسجلة." : "أدخل المبلغ وحالة الدفع لعرض المدفوع والمتبقي."}</p>}
                                {requires_approval && !isCorrection ? <p className="small text-body-secondary mt-2 mb-0">تدخل هذه العملية التقارير بعد اعتمادها من المسؤول.</p> : null}
                            </section>
                            {draftNotice ? <p role="status" className="small">{draftNotice}</p> : null}
                            {errors.client_draft_id ? <p className="text-danger small" role="alert">{errors.client_draft_id}</p> : null}
                            {Object.keys(errors).length ? <p role="alert" className="text-danger small">راجع الحقول الموضحة أعلاه ثم حاول مجدداً.</p> : null}
                                    <div className="d-flex flex-wrap gap-2">
                                <button type="submit" className="btn btn-primary flex-grow-1" style={{ minHeight: 48 }} disabled={form.processing}>{form.processing ? "جارٍ الحفظ…" : submitLabel}</button>
                                {!isCorrection ? <button type="button" className="btn btn-outline-primary" style={{ minHeight: 48 }} disabled={form.processing} onClick={persist}>حفظ مسودة</button> : null}
                                <Link href={cancelHref} className="btn btn-outline-secondary" style={{ minHeight: 48 }}>إلغاء</Link>
                            </div>
                        </form>
                    </SectionCard>
                </div>
                <aside className="col-12 col-lg-4">
                    <div className="app-form-guide mb-3"><h2>خطوات بسيطة لتسجيل العملية</h2><ol><li><strong>اختر النوع</strong><span>تظهر التصنيفات المناسبة لاختيارك.</span></li><li><strong>أدخل المبلغ والدفع</strong><span>راجع المدفوع والمتبقي في الملخص.</span></li><li><strong>أضف التفاصيل عند الحاجة</strong><span>اسم الزبون أو المورد والبنود والملاحظات اختيارية.</span></li></ol></div>
                    {!isCorrection ? <section className="border rounded-3 p-3" aria-label="المسودات المحلية">
                        <h2 className="h6">مسودات هذا الجهاز{drafts.length ? ` (${drafts.length})` : ""}</h2>
                        <p className="small text-body-secondary">احفظ النموذج عند ضعف الاتصال، ثم استعده وراجعه قبل الإرسال. المسودات خاصة بهذا الجهاز ولا تُرسل تلقائياً.</p>
                        <p className="small text-body-secondary">استخدم جهازاً موثوقاً واحذف المسودات بعد الانتهاء.</p>
                        {drafts.map(draft => <div key={draft.id} className="border-top pt-2 mt-2">
                            <p className="small mb-1">{draft.data.counterparty || draft.data.amount || "مسودة"} · {new Date(draft.updatedAt).toLocaleString()}</p>
                            <div className="d-flex gap-2"><button type="button" className="btn btn-sm btn-outline-primary" disabled={form.processing} onClick={() => { form.setData({ ...form.data, ...draft.data }); setDraftId(draft.id); setDetailsOpen(Boolean(draft.data.counterparty || draft.data.description || draft.data.items.length)); setDraftNotice("راجع البيانات قبل الإرسال. إذا سبق إرسالها، أعد الإرسال دون تعديل أو تحقق من سجل العمليات أولاً."); }}>استعادة</button><button type="button" className="btn btn-sm btn-outline-danger" disabled={form.processing} onClick={() => { try { removeDraft(scope, enterprise.public_id, draft.id); setDrafts(loadDrafts()); } catch { setDraftNotice("تعذر حذف المسودة."); } }}>حذف</button></div>
                        </div>)}
                    </section> : null}
                </aside>
            </div>
        </div>
    </AppLayout>;
}
