import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader } from '../../Components/Common/PageHeader';
import { SectionCard } from '../../Components/Common/SectionCard';
import { TextInput } from '../../Components/Forms/TextInput';
import { useT } from '../../Hooks/useT';

export default function Create() {
    const t = useT();
    const form = useForm({
        business_name: '',
        location: '',
        contact_phone: '',
        contact_email: '',
    });

    const locations = [
        { value: 'damascus', label: t('smes.location.damascus', undefined, 'دمشق') },
        { value: 'rural_damascus', label: t('smes.location.rural_damascus', undefined, 'ريف دمشق') },
        { value: 'daraa', label: t('smes.location.daraa', undefined, 'درعا') },
    ];

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/enterprises');
    }

    return (
        <AppLayout title={t('smes.create_title', undefined, 'منشأة جديدة')}>
            <div className="app-page">
                <PageHeader title={t('smes.register_title', undefined, 'تسجيل منشأة')} description={t('smes.step_1_of_1', undefined, 'الخطوة 1 من 1')} />
                <form onSubmit={submit} style={{ maxWidth: '40rem' }}>
                    <SectionCard title={t('smes.starter_fields', undefined, 'البيانات اللازمة للبدء')}>
                        <p className="mb-3" style={{ color: 'var(--app-text-muted, #57534e)' }}>
                            {t('smes.step_1_of_1', undefined, 'الخطوة 1 من 1')}
                        </p>
                        <TextInput
                            id="business_name"
                            name="business_name"
                            label={t('smes.business_name', undefined, 'اسم المنشأة')}
                            value={form.data.business_name}
                            error={form.errors.business_name}
                            onChange={(value) => form.setData('business_name', value)}
                        />
                        <div className="mb-3 w-100">
                            <label htmlFor="location" className="form-label mb-1 fw-semibold">
                                {t('smes.location', undefined, 'الموقع')}
                            </label>
                            <select
                                id="location"
                                name="location"
                                className={`form-select w-100${form.errors.location ? ' is-invalid' : ''}`}
                                style={{ minHeight: '48px', fontSize: '16px' }}
                                value={form.data.location}
                                onChange={(event) => form.setData('location', event.target.value)}
                            >
                                <option value="">{t('smes.location_placeholder', undefined, 'اختر الموقع')}</option>
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
                            label={t('smes.contact_phone', undefined, 'هاتف التواصل')}
                            value={form.data.contact_phone}
                            error={form.errors.contact_phone}
                            help={t('smes.contact_help', undefined, 'الهاتف أو البريد كافٍ للبدء.')}
                            onChange={(value) => form.setData('contact_phone', value)}
                        />
                        <TextInput
                            id="contact_email"
                            name="contact_email"
                            type="email"
                            label={t('smes.contact_email', undefined, 'البريد الإلكتروني')}
                            value={form.data.contact_email}
                            error={form.errors.contact_email}
                            onChange={(value) => form.setData('contact_email', value)}
                        />
                        <button type="submit" className="btn btn-primary w-100" style={{ minHeight: '48px' }} disabled={form.processing}>
                            {t('smes.save_enterprise', undefined, 'حفظ المنشأة')}
                        </button>
                    </SectionCard>
                </form>
            </div>
        </AppLayout>
    );
}
