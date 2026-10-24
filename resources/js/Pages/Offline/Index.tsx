import { OfflinePreparationStatus } from "../../Components/Feedback/ConnectionStatus";
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader } from '../../Components/Common/PageHeader';
import { SectionCard } from '../../Components/Common/SectionCard';
import { offlineCommand, prepareOffline, syncOffline, useOffline, type OfflineReply } from '../../lib/offline';
const stateLabels = { pending: 'بانتظار الاتصال', auth: 'يلزم تسجيل الدخول', review: 'تحتاج مراجعة' };
export default function Index() {
    const { auth, offline } = usePage().props;
    const status = useOffline();
    const [error, setError] = useState('');
    const [details, setDetails] = useState<Record<string, NonNullable<OfflineReply['details']>>>({});
    const showDetails = async (id: string) => { try { const reply = await offlineCommand('DETAILS', { id }); setDetails(value => ({ ...value, [id]: reply.details ?? [] })); } catch (e) { setError(e instanceof Error ? e.message : 'تعذرت قراءة الطلب.'); } };
    const download = async (id: string, field: string) => { try { const reply = await offlineCommand('FILE', { id, field }); if (!reply.file) return; const url = URL.createObjectURL(reply.file); const a = document.createElement('a'); a.href = url; a.download = reply.name ?? 'attachment'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); } catch { setError('تعذر تنزيل المرفق.'); } };
    const act = async (type: string, id?: string) => { setError(''); try { await offlineCommand(type, { id }); } catch (e) { setError(e instanceof Error ? e.message : 'تعذر تنفيذ الطلب.'); } };
    return <AppLayout title="العمل دون اتصال"><div className="app-page d-flex flex-column gap-3">
        <PageHeader title="العمل دون اتصال" description="صفحاتك المحفوظة والعمليات التي تنتظر المزامنة، في مكان واحد." />
        {offline?.queued_id ? <div className="alert alert-info" role="status">حُفظ الطلب على هذا الجهاز. لم يُعتمد على الخادم بعد؛ سيُرسل تلقائياً عند عودة الاتصال.</div> : null}
        {offline?.missing_page ? <div className="alert alert-warning" role="alert">الصفحة المطلوبة لم تُحفظ بعد. افتحها وأنت متصل لتجهيزها لاحقاً.</div> : null}
        {error || status.error ? <div className="alert alert-warning" role="alert">{error || status.error}</div> : null}
        <SectionCard title="تجهيز هذا الجهاز">
            <p>يبدأ حفظ ملفات الواجهة والصفحات الأساسية تلقائياً فور تسجيل الدخول، دون الضغط على أي زر. تظهر حالة التجهيز هنا، وتُحفظ الصفحات التي تفتحها لاحقاً أيضاً لمدة سبعة أيام. الزر أدناه اختياري لتحديث النسخة المحفوظة.</p>
            <p className="small text-body-secondary">الملفات المحفوظة خاصة بهذا المتصفح. تسجيل الخروج أو مسح بيانات المتصفح يحذف النسخ والطلبات غير المرسلة. لا تستخدم هذا الخيار على جهاز مشترك.</p>
            {!status.supported ? <p role="status">يلزم متصفح يدعم العمل دون اتصال، وموقع يعمل عبر HTTPS.</p> : null}
            <OfflinePreparationStatus status={status} />
            <div className="d-flex flex-wrap gap-2 mt-2">
                <button type="button" className="btn btn-primary" disabled={!status.ready || status.busy || status.preparing || !navigator.onLine} onClick={() => void prepareOffline([location.pathname, ...auth.navigation.map(i => i.href), ...(auth.quick_record ? [auth.quick_record.manual_url] : [])])}>{status.preparing || status.busy ? 'يرجى الانتظار…' : 'تحديث النسخة المحفوظة'}</button>
                <button type="button" className="btn btn-outline-primary" disabled={!status.ready || status.busy} onClick={() => void syncOffline()}>المزامنة الآن</button>
                <button type="button" className="btn btn-outline-danger" disabled={!status.ready || status.busy} onClick={() => { if (window.confirm('حذف كل الصفحات والملفات والطلبات غير المرسلة من هذا الجهاز؟')) void act('CLEAR'); }}>مسح بيانات هذا الجهاز</button>
            </div>
            {status.lastSync ? <p className="small mt-2 mb-0" role="status">آخر عملية تمت مزامنتها: {status.lastSync.label} · {new Date(status.lastSync.at).toLocaleString()}</p> : null}
        </SectionCard>
        <SectionCard title={`قائمة الانتظار (${status.queue.length})`}>
            <p className="small text-body-secondary">هذه الطلبات لا تدخل الأرصدة أو التقارير الرسمية قبل قبولها على الخادم. عند تعارض سجل، تتوقف المزامنة حتى تراجعه. لا تُرسل الطلب نفسه يدوياً من نموذج آخر.</p>
            {!status.queue.length ? <p role="status">لا توجد عمليات بانتظار الإرسال.</p> : <ul className="list-unstyled mb-0">{status.queue.map(item => <li key={item.id} className="border rounded-3 p-3 mb-2">
                <div className="d-flex flex-wrap justify-content-between gap-2"><strong>{item.label}</strong><span className={item.status === 'review' ? 'text-danger' : 'text-body-secondary'}>{stateLabels[item.status]}</span></div>
                <p className="small">{new Date(item.createdAt).toLocaleString()}</p>
                {item.errors.length ? <ul role="alert">{item.errors.map((message, i) => <li key={i}>{message}</li>)}</ul> : null}
                <div className="d-flex flex-wrap gap-2">
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => void showDetails(item.id)}>عرض البيانات والمرفقات</button>
                    <Link className="btn btn-sm btn-outline-primary" href={item.source}>فتح النموذج للمراجعة</Link>
                    <button type="button" className="btn btn-sm btn-outline-primary" disabled={status.busy} onClick={() => void act('RETRY', item.id)}>إعادة المحاولة</button>
                    <button type="button" className="btn btn-sm btn-outline-danger" disabled={status.busy} onClick={() => { if (window.confirm('حذف هذا الطلب من الانتظار؟ لن يُرسل إلى الخادم.')) void act('REMOVE', item.id); }}>حذف الطلب</button>
                </div>
                {details[item.id] ? <dl className="row small mt-3">{details[item.id].map((field, i) => <div className="col-12 border-top py-2" key={i}><dt>{field.label}</dt><dd className="mb-0" style={{ overflowWrap: 'anywhere', whiteSpace: 'pre-wrap' }}>{field.value}{field.file ? <button type="button" className="btn btn-sm btn-link" onClick={() => void download(item.id, field.name)}>تنزيل المرفق</button> : null}</dd></div>)}</dl> : null}
                {item.status === 'review' ? <p className="small mt-2 mb-0">إذا تغير السجل أو كانت البيانات ناقصة، احذف الطلب المعلّق ثم أعد إدخاله من أحدث نموذج بعد المراجعة.</p> : null}
            </li>)}</ul>}
        </SectionCard>
        <SectionCard title={`الصفحات المحفوظة (${status.pages.length})`}>
            <p className="small">الصفحات والتقارير تعرض آخر نسخة محفوظة مع تاريخها. لا تتضمن الطلبات التي لم تُزامن. الملفات التي سبق فتحها متاحة ضمن حدود مساحة الجهاز.</p>
            <div className="d-flex flex-wrap gap-2">{status.pages.filter(page => !/\/export\//.test(page.url)).map(page => <Link key={page.url} href={page.url} className="btn btn-sm btn-outline-secondary">{page.title}</Link>)}</div>
        </SectionCard>
        <SectionCard title="ما يحتاج اتصالاً مباشراً؟"><p className="mb-0">تسجيل الدخول لأول مرة، إدارة الحساب والصلاحيات، النسخ الاحتياطي، الموافقات والنشر والمشاركة الخارجية، وقراءة الفواتير أو الصوت بواسطة الخادم والمساعد الذكي. يمكنك رفع المستند إلى قائمة الانتظار الآن، ثم فتحه ومعالجته بعد مزامنته.</p></SectionCard>
    </div></AppLayout>;
}
