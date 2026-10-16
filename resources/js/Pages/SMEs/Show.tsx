import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader } from '../../Components/Common/PageHeader';
import { SectionCard } from '../../Components/Common/SectionCard';
import { EmptyState } from '../../Components/Feedback/EmptyState';
import { FormError } from '../../Components/Forms/FormError';

type Person = { id: number; name: string; email?: string };
type Option = { id: number; name: string };

type EnterpriseProfile = {
    public_id: string;
    business_name: string;
    sector: string | null;
    location: string | null;
    founding_date: string | null;
    employee_count: number | null;
    female_employee_count: number | null;
    male_employee_count: number | null;
    owner_name: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    registration_status: string | null;
    initial_digital_readiness_note: string | null;
    initial_financial_readiness_note: string | null;
    programme_stage: string | null;
    progress_summary: string | null;
};

const locationLabels: Record<string, string> = {
    damascus: 'دمشق',
    rural_damascus: 'ريف دمشق',
    daraa: 'درعا',
};

const incompleteLabels: Record<string, string> = {
    sector: 'القطاع',
    founding_date: 'تاريخ التأسيس',
    employee_count: 'عدد العاملين',
    owner_name: 'اسم المالك',
    contact: 'اسم جهة الاتصال',
    registration_status: 'حالة التسجيل',
};

function Line({ label, value }: { label: string; value: string | number | null }) {
    return (
        <p className="mb-2">
            <span className="fw-semibold">{label}: </span>
            <span>{value === null || value === '' ? '—' : value}</span>
        </p>
    );
}

export default function Show({
    enterprise,
    incomplete_fields,
    members,
    assignments,
    advisor_options,
    member_options,
    can_assign,
}: {
    enterprise: EnterpriseProfile;
    incomplete_fields: string[];
    members: Person[] | null;
    assignments: Person[] | null;
    advisor_options: Option[];
    member_options: Option[];
    can_assign: boolean;
}) {
    const advisorForm = useForm({ user_id: '' });
    const memberForm = useForm({ user_id: '' });

    return (
        <AppLayout title={enterprise.business_name}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title={enterprise.business_name} description="ملف المنشأة" />
                <div>
                    <a className="btn btn-outline-primary" style={{ minHeight: '44px' }} href={`/enterprises/${enterprise.public_id}/edit`}>
                        تعديل الملف
                    </a>
                </div>

                {incomplete_fields.length > 0 ? (
                    <SectionCard title="معلومات ناقصة">
                        <ul className="mb-0">
                            {incomplete_fields.map((field) => (
                                <li key={field}>{incompleteLabels[field] ?? field}</li>
                            ))}
                        </ul>
                    </SectionCard>
                ) : null}

                <SectionCard title="الملف">
                    <Line label="اسم المنشأة" value={enterprise.business_name} />
                    <Line label="القطاع" value={enterprise.sector} />
                    <Line label="الموقع" value={enterprise.location ? locationLabels[enterprise.location] ?? enterprise.location : null} />
                    <Line label="تاريخ التأسيس" value={enterprise.founding_date} />
                    <Line label="عدد العاملين" value={enterprise.employee_count} />
                    <Line label="عدد العاملات" value={enterprise.female_employee_count} />
                    <Line label="عدد العاملين الذكور" value={enterprise.male_employee_count} />
                    <p className="mb-2" style={{ color: 'var(--app-text-muted, #57534e)', fontSize: '0.875rem' }}>
                        أعداد العاملات والعاملين اختيارية وليست إطاراً رسمياً للنوع الاجتماعي.
                    </p>
                    <Line label="اسم المالك" value={enterprise.owner_name} />
                    <Line label="اسم جهة الاتصال" value={enterprise.contact_name} />
                    <Line label="الهاتف" value={enterprise.contact_phone} />
                    <Line label="البريد الإلكتروني" value={enterprise.contact_email} />
                    <Line label="مرحلة البرنامج" value={enterprise.programme_stage} />
                    <Line label="ملخص التقدم" value={enterprise.progress_summary} />
                </SectionCard>

                <SectionCard title="التسجيل">
                    <Line label="حالة التسجيل" value={enterprise.registration_status} />
                </SectionCard>

                <SectionCard title="ملاحظات الجاهزية الأولية">
                    <p className="mb-3">هذه ملاحظة وليست درجة</p>
                    <Line label="ملاحظة الجاهزية الرقمية الأولية" value={enterprise.initial_digital_readiness_note} />
                    <Line label="ملاحظة الجاهزية المالية الأولية" value={enterprise.initial_financial_readiness_note} />
                </SectionCard>

                <SectionCard title="المعاملات والمستندات">
                    <div className="d-flex flex-wrap gap-2">
                        <Link href={`/enterprises/${enterprise.public_id}/transactions`} className="btn btn-primary" style={{ minHeight: '48px' }}>
                            <i className="bi bi-cash-stack me-2" aria-hidden="true" />
                            المعاملات المالية
                        </Link>
                        <Link href={`/enterprises/${enterprise.public_id}/documents`} className="btn btn-outline-primary" style={{ minHeight: '48px' }}>
                            <i className="bi bi-folder2 me-2" aria-hidden="true" />
                            المستندات
                        </Link>
                    </div>
                </SectionCard>

                <SectionCard title="الموافقة">
                    <EmptyState title="سجل الموافقة غير متاح حتى يعتمد بيكون نص المواققة" />
                </SectionCard>

                <SectionCard title="الإرشاد والمتابعة">
                    <Link
                        href={`/enterprises/${enterprise.public_id}/advisory`}
                        className="btn btn-primary"
                    >
                        عرض الإرشاد والمتابعة
                    </Link>
                </SectionCard>

                {members !== null ? (
                    <SectionCard title="مستخدمو المنشأة">
                        {members.length === 0 ? (
                            <p className="mb-0">لا يوجد مستخدم مرتبط.</p>
                        ) : (
                            <ul className="mb-0">
                                {members.map((member) => (
                                    <li key={member.id}>
                                        {member.name} — {member.email}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>
                ) : null}

                {assignments !== null ? (
                    <SectionCard title="المستشارون">
                        {assignments.length === 0 ? (
                            <p className="mb-0">لا يوجد مستشار معيّن.</p>
                        ) : (
                            <ul className="mb-0">
                                {assignments.map((advisor) => (
                                    <li key={advisor.id}>{advisor.name}</li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>
                ) : null}

                {can_assign ? (
                    <SectionCard title="تعيين">
                        <form
                            className="mb-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                advisorForm.post(`/enterprises/${enterprise.public_id}/advisors`);
                            }}
                        >
                            <label htmlFor="advisor_user_id" className="form-label fw-semibold">
                                تعيين مستشار
                            </label>
                            <select
                                id="advisor_user_id"
                                className="form-select"
                                style={{ minHeight: '48px', fontSize: '16px' }}
                                value={advisorForm.data.user_id}
                                onChange={(event) => advisorForm.setData('user_id', event.target.value)}
                            >
                                <option value="">اختر مستشاراً</option>
                                {advisor_options.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            <FormError message={advisorForm.errors.user_id} />
                            <button type="submit" className="btn btn-primary mt-2" style={{ minHeight: '48px' }} disabled={advisorForm.processing}>
                                تعيين المستشار
                            </button>
                        </form>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                memberForm.post(`/enterprises/${enterprise.public_id}/members`);
                            }}
                        >
                            <label htmlFor="member_user_id" className="form-label fw-semibold">
                                ربط مستخدم منشأة
                            </label>
                            <select
                                id="member_user_id"
                                className="form-select"
                                style={{ minHeight: '48px', fontSize: '16px' }}
                                value={memberForm.data.user_id}
                                onChange={(event) => memberForm.setData('user_id', event.target.value)}
                            >
                                <option value="">اختر مستخدماً</option>
                                {member_options.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            <FormError message={memberForm.errors.user_id} />
                            <button type="submit" className="btn btn-primary mt-2" style={{ minHeight: '48px' }} disabled={memberForm.processing}>
                                ربط المستخدم
                            </button>
                        </form>
                    </SectionCard>
                ) : null}
            </div>
        </AppLayout>
    );
}
