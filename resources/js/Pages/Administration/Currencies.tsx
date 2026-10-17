import { useForm } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
type Currency = { code: string; name: string; enabled: boolean; is_default: boolean; used: boolean };
function Row({ currency: c, current }: { currency: Currency; current: string }) {
    const form = useForm({ name: c.name, enabled: c.enabled, is_default: c.code === current });
    return <form className="app-mobile-record" onSubmit={e => { e.preventDefault(); form.put(`/admin/currencies/${c.code}`, { preserveScroll: true }); }}>
        <div className="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong><bdi>{c.code}</bdi>{c.code === current ? " · الافتراضية" : ""}</strong><span className="small text-body-secondary">{c.used ? "مستخدمة في سجلات سابقة" : "لم تستخدم بعد"}</span></div>
        <label className="form-label" htmlFor={`name-${c.code}`}>اسم العملة</label><input id={`name-${c.code}`} className="form-control mb-3" value={form.data.name} required onChange={e => form.setData("name", e.target.value)} />
        <div className="d-flex flex-wrap gap-3 align-items-center"><label><input type="checkbox" className="form-check-input me-2" checked={form.data.enabled} onChange={e => form.setData("enabled", e.target.checked)} />متاحة للعمليات الجديدة</label><label><input type="checkbox" className="form-check-input me-2" checked={form.data.is_default} disabled={!form.data.enabled} onChange={e => form.setData("is_default", e.target.checked)} />العملة الافتراضية</label><button className="btn btn-primary ms-auto" disabled={form.processing}>حفظ</button></div>
        {Object.values(form.errors).map((error,i) => <p className="text-danger mt-2 mb-0" role="alert" key={i}>{error}</p>)}
    </form>;
}
export default function Currencies({ currencies, defaultCurrency }: { currencies: Currency[]; defaultCurrency: string }) {
    const form = useForm({ code: "", name: "" });
    return <AppLayout title="العملات"><div className="app-page d-flex flex-column gap-3"><PageHeader title="إدارة العملات" description="حدد العملات المتاحة والعملة الافتراضية، مع الحفاظ على السجلات القديمة." />
        <div className="alert alert-info mb-0">إيقاف العملة يمنع استخدامها في عمليات جديدة. تبقى التقارير والدفعات والتصحيحات على سجلاتها السابقة متاحة. لا تُجرى أي تحويلات بين العملات.</div>
        <SectionCard title="العملات المتاحة"><div className="d-flex flex-column gap-3">{currencies.map(c => <Row key={`${c.code}-${c.name}-${c.enabled}-${defaultCurrency}`} currency={c} current={defaultCurrency} />)}</div></SectionCard>
        <SectionCard title="إضافة عملة"><form className="row g-3 align-items-end" onSubmit={e => { e.preventDefault(); form.post("/admin/currencies", { onSuccess: () => form.reset() }); }}><div className="col-12 col-md-3"><label htmlFor="currency-code" className="form-label">رمز العملة (3 أحرف)</label><input id="currency-code" className="form-control" dir="ltr" maxLength={3} pattern="[A-Z]{3}" required value={form.data.code} onChange={e => form.setData("code", e.target.value.toUpperCase())} /></div><div className="col-12 col-md-6"><label htmlFor="currency-name" className="form-label">الاسم</label><input id="currency-name" className="form-control" required value={form.data.name} onChange={e => form.setData("name", e.target.value)} /></div><div className="col-12 col-md-3"><button className="btn btn-primary w-100" disabled={form.processing}>إضافة</button></div>{Object.values(form.errors).map((error,i) => <p className="text-danger mb-0" role="alert" key={i}>{error}</p>)}</form></SectionCard>
    </div></AppLayout>;
}
