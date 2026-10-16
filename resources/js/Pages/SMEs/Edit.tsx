import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader } from '../../Components/Common/PageHeader';
import { SectionCard } from '../../Components/Common/SectionCard';
import { TextInput } from '../../Components/Forms/TextInput';

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

const locations = [
    { value: 'damascus', label: 'دمشق' },
    { value: 'rural_damascus', label: 'ريف دمشق' },
    { value: 'daraa', label: 'درعا' },
];

function text(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

export default function Edit({ enterprise }: { enterprise: EnterpriseProfile }) {
    const form = useForm({
        business_name: enterprise.business_name,
        sector: text(enterprise.sector),
        location: text(enterprise.location),
        founding_date: text(enterprise.founding_date),
        employee_count: text(enterprise.employee_count),
        female_employee_count: text(enterprise.female_employee_count),
        male_employee_count: text(enterprise.male_employee_count),
        owner_name: text(enterprise.owner_name),
        contact_name: text(enterprise.contact_name),
        contact_phone: text(enterprise.contact_phone),
        contact_email: text(enterprise.contact_email),
        registration_status: text(enterprise.registration_status),
        initial_digital_readiness_note: text(enterprise.initial_digital_readiness_note),
        initial_financial_readiness_note: text(enterprise.initial_financial_readiness_note),
        programme_stage: text(enterprise.programme_stage),
        progress_summary: text(enterprise.progress_summary),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(`/enterprises/${enterprise.public_id}`);
    }

    return (
        <AppLayout title="تعديل الملف">
            <div className="app-page">
                <PageHeader title="تعديل الملف" description={enterprise.business_name} />
                <form onSubmit={submit} style={{ maxWidth: '42rem' }} className="d-flex flex-column gap-3">
                    <SectionCard title="المعلومات الأساسية">
                        <TextInput
                            id="business_name"
                            name="business_name"
                            label="اسم المنشأة"
                            value={form.data.business_name}
                            error={form.errors.business_name}
                            onChange={(value) => form.setData('business_name', value)}
                        />
                        <div className="mb-3">
                            <label htmlFor="location" className="form-label fw-semibold">
                                الموقع
                            </label>
                            <select
                                id="location"
                                name="location"
                                className={`form-select${form.errors.location ? ' is-invalid' : ''}`}
                                style={{ minHeight: '48px', fontSize: '16px' }}
                                value={form.data.location}
                                onChange={(event) => form.setData('location', event.target.value)}
                            >
                                {locations.map((location) => (
                                    <option key={location.value} value={location.value}>
                                        {location.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.location ? (
                                <div className="invalid-feedback d-block" role="alert">
                                    {form.errors.location}
                                </div>
                            ) : null}
                        </div>
                        <TextInput
                            id="contact_name"
                            name="contact_name"
                            label="اسم جهة الاتصال"
                            value={form.data.contact_name}
                            error={form.errors.contact_name}
                            onChange={(value) => form.setData('contact_name', value)}
                        />
                        <TextInput
                            id="contact_phone"
                            name="contact_phone"
                            label="هاتف التواصل"
                            value={form.data.contact_phone}
                            error={form.errors.contact_phone}
                            onChange={(value) => form.setData('contact_phone', value)}
                        />
                        <TextInput
                            id="contact_email"
                            name="contact_email"
                            type="email"
                            label="البريد الإلكتروني"
                            value={form.data.contact_email}
                            error={form.errors.contact_email}
                            onChange={(value) => form.setData('contact_email', value)}
                        />
                        <TextInput
                            id="owner_name"
                            name="owner_name"
                            label="اسم المالك"
                            value={form.data.owner_name}
                            error={form.errors.owner_name}
                            onChange={(value) => form.setData('owner_name', value)}
                        />
                    </SectionCard>

                    <SectionCard title="تفاصيل إضافية">
                        <TextInput
                            id="sector"
                            name="sector"
                            label="القطاع"
                            value={form.data.sector}
                            error={form.errors.sector}
                            onChange={(value) => form.setData('sector', value)}
                        />
                        <TextInput
                            id="founding_date"
                            name="founding_date"
                            type="date"
                            label="تاريخ التأسيس"
                            value={form.data.founding_date}
                            error={form.errors.founding_date}
                            onChange={(value) => form.setData('founding_date', value)}
                        />
                        <TextInput
                            id="employee_count"
                            name="employee_count"
                            type="number"
                            label="عدد العاملين"
                            value={form.data.employee_count}
                            error={form.errors.employee_count}
                            onChange={(value) => form.setData('employee_count', value)}
                        />
                        <TextInput
                            id="female_employee_count"
                            name="female_employee_count"
                            type="number"
                            label="عدد العاملات"
                            help="حقل اختياري للتخزين فقط، وليس إطاراً رسمياً."
                            value={form.data.female_employee_count}
                            error={form.errors.female_employee_count}
                            onChange={(value) => form.setData('female_employee_count', value)}
                        />
                        <TextInput
                            id="male_employee_count"
                            name="male_employee_count"
                            type="number"
                            label="عدد العاملين الذكور"
                            help="حقل اختياري للتخزين فقط، وليس إطاراً رسمياً."
                            value={form.data.male_employee_count}
                            error={form.errors.male_employee_count}
                            onChange={(value) => form.setData('male_employee_count', value)}
                        />
                        <TextInput
                            id="registration_status"
                            name="registration_status"
                            label="حالة التسجيل"
                            value={form.data.registration_status}
                            error={form.errors.registration_status}
                            onChange={(value) => form.setData('registration_status', value)}
                        />
                        <TextInput
                            id="programme_stage"
                            name="programme_stage"
                            label="مرحلة البرنامج"
                            value={form.data.programme_stage}
                            error={form.errors.programme_stage}
                            onChange={(value) => form.setData('programme_stage', value)}
                        />
                        <div className="mb-3">
                            <label htmlFor="progress_summary" className="form-label fw-semibold">
                                ملخص التقدم
                            </label>
                            <textarea
                                id="progress_summary"
                                name="progress_summary"
                                className="form-control"
                                style={{ fontSize: '16px' }}
                                rows={3}
                                value={form.data.progress_summary}
                                onChange={(event) => form.setData('progress_summary', event.target.value)}
                            />
                        </div>
                        <div className="mb-3">
                            <label htmlFor="initial_digital_readiness_note" className="form-label fw-semibold">
                                ملاحظة الجاهزية الرقمية الأولية
                            </label>
                            <p className="form-text">هذه ملاحظة وليست درجة</p>
                            <textarea
                                id="initial_digital_readiness_note"
                                name="initial_digital_readiness_note"
                                className="form-control"
                                style={{ fontSize: '16px' }}
                                rows={3}
                                value={form.data.initial_digital_readiness_note}
                                onChange={(event) => form.setData('initial_digital_readiness_note', event.target.value)}
                            />
                        </div>
                        <div className="mb-3">
                            <label htmlFor="initial_financial_readiness_note" className="form-label fw-semibold">
                                ملاحظة الجاهزية المالية الأولية
                            </label>
                            <textarea
                                id="initial_financial_readiness_note"
                                name="initial_financial_readiness_note"
                                className="form-control"
                                style={{ fontSize: '16px' }}
                                rows={3}
                                value={form.data.initial_financial_readiness_note}
                                onChange={(event) => form.setData('initial_financial_readiness_note', event.target.value)}
                            />
                        </div>
                        <button type="submit" className="btn btn-primary" style={{ minHeight: '48px' }} disabled={form.processing}>
                            حفظ الملف
                        </button>
                    </SectionCard>
                </form>
            </div>
        </AppLayout>
    );
}
