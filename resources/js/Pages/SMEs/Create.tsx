import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader } from '../../Components/Common/PageHeader';
import { SectionCard } from '../../Components/Common/SectionCard';
import { TextInput } from '../../Components/Forms/TextInput';

const locations = [
    { value: 'damascus', label: 'دمشق' },
    { value: 'rural_damascus', label: 'ريف دمشق' },
    { value: 'daraa', label: 'درعا' },
];

export default function Create() {
    const form = useForm({
        business_name: '',
        location: '',
        contact_phone: '',
        contact_email: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/enterprises');
    }

    return (
        <AppLayout title="منشأة جديدة">
            <div className="app-page">
                <PageHeader title="تسجيل منشأة" description="الخطوة 1 من 1" />
                <form onSubmit={submit} style={{ maxWidth: '40rem' }}>
                    <SectionCard title="البيانات اللازمة للبدء">
                        <p className="mb-3" style={{ color: 'var(--app-text-muted, #57534e)' }}>
                            الخطوة 1 من 1
                        </p>
                        <TextInput
                            id="business_name"
                            name="business_name"
                            label="اسم المنشأة"
                            value={form.data.business_name}
                            error={form.errors.business_name}
                            onChange={(value) => form.setData('business_name', value)}
                        />
                        <div className="mb-3 w-100">
                            <label htmlFor="location" className="form-label mb-1 fw-semibold">
                                الموقع
                            </label>
                            <select
                                id="location"
                                name="location"
                                className={`form-select w-100${form.errors.location ? ' is-invalid' : ''}`}
                                style={{ minHeight: '48px', fontSize: '16px' }}
                                value={form.data.location}
                                onChange={(event) => form.setData('location', event.target.value)}
                            >
                                <option value="">اختر الموقع</option>
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
                            id="contact_phone"
                            name="contact_phone"
                            label="هاتف التواصل"
                            value={form.data.contact_phone}
                            error={form.errors.contact_phone}
                            help="الهاتف أو البريد كافٍ للبدء."
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
                        <button type="submit" className="btn btn-primary" style={{ minHeight: '48px' }} disabled={form.processing}>
                            حفظ المنشأة
                        </button>
                    </SectionCard>
                </form>
            </div>
        </AppLayout>
    );
}
