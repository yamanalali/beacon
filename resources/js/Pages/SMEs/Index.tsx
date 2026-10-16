import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader } from '../../Components/Common/PageHeader';
import { EmptyState } from '../../Components/Feedback/EmptyState';
import { SearchInput } from '../../Components/Navigation/SearchInput';

type EnterpriseRow = {
    public_id: string;
    business_name: string;
    sector: string | null;
    location: string | null;
};

type Paginator = {
    data: EnterpriseRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

const locationLabels: Record<string, string> = {
    damascus: 'دمشق',
    rural_damascus: 'ريف دمشق',
    daraa: 'درعا',
};

export default function Index({
    enterprises,
    filters,
}: {
    enterprises: Paginator;
    filters: { search: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        router.get('/enterprises', { search }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="المنشآت">
            <div className="app-page">
                <PageHeader title="المنشآت" description="المنشآت ضمن نطاقك." />
                <form onSubmit={submitSearch} className="mb-4" style={{ maxWidth: '28rem' }}>
                    <SearchInput id="enterprise-search" label="البحث باسم المنشأة" value={search} onChange={setSearch} />
                    <button type="submit" className="btn btn-primary mt-2" style={{ minHeight: '48px' }}>
                        بحث
                    </button>
                </form>

                {enterprises.data.length === 0 ? (
                    <EmptyState title="لا توجد منشآت حتى الآن" />
                ) : (
                    <>
                        <div className="d-lg-none d-flex flex-column gap-3">
                            {enterprises.data.map((enterprise) => (
                                <a
                                    key={enterprise.public_id}
                                    href={`/enterprises/${enterprise.public_id}`}
                                    className="text-decoration-none border p-3"
                                    style={{
                                        background: 'var(--app-surface, #fff)',
                                        borderColor: 'var(--app-border, #e7e5e4)',
                                        borderRadius: 'var(--app-radius, 0.75rem)',
                                        color: 'inherit',
                                    }}
                                >
                                    <div className="fw-semibold" style={{ fontSize: '1.05rem' }}>
                                        {enterprise.business_name}
                                    </div>
                                    <div className="mt-2" style={{ color: 'var(--app-text-muted, #57534e)' }}>
                                        الموقع: {enterprise.location ? locationLabels[enterprise.location] ?? enterprise.location : '—'}
                                    </div>
                                    <div style={{ color: 'var(--app-text-muted, #57534e)' }}>
                                        القطاع: {enterprise.sector ?? '—'}
                                    </div>
                                </a>
                            ))}
                        </div>
                        <div className="d-none d-lg-block table-responsive">
                            <table className="table align-middle">
                                <thead>
                                    <tr>
                                        <th>المنشأة</th>
                                        <th>الموقع</th>
                                        <th>القطاع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {enterprises.data.map((enterprise) => (
                                        <tr key={enterprise.public_id}>
                                            <td>
                                                <a href={`/enterprises/${enterprise.public_id}`}>{enterprise.business_name}</a>
                                            </td>
                                            <td>{enterprise.location ? locationLabels[enterprise.location] ?? enterprise.location : '—'}</td>
                                            <td>{enterprise.sector ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
