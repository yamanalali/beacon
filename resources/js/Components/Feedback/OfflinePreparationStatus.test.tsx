import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { OfflinePreparationStatus } from './ConnectionStatus';
import type { OfflineState } from '../../lib/offline';
vi.mock('@inertiajs/react', () => ({ router: {}, Link: () => null, usePage: () => ({ props: {} }) }));
afterEach(cleanup);
const base: OfflineState = { supported: true, preparing: false, preparation: null, ready: false, busy: false, queue: [], pages: [], error: null };
it('shows download progress instead of claiming the interface is ready early', () => {
    render(<OfflinePreparationStatus status={{ ...base, preparing: true, preparation: { status: 'preparing', saved: 2, processed: 3, total: 8 } }} />);
    expect(screen.getByRole('progressbar').getAttribute('value')).toBe('3');
    expect(screen.getByTestId('offline-preparing').textContent).toContain('حُفظت 2 صفحة');
    expect(screen.queryByTestId('offline-prepared')).toBeNull();
});
it('explains that interrupted preparation resumes automatically', () => {
    render(<OfflinePreparationStatus status={{ ...base, ready: true, preparation: { status: 'interrupted', saved: 2, processed: 3, total: 8 } }} />);
    expect(screen.getByTestId('offline-preparation-interrupted').textContent).toContain('تلقائياً عند عودة الاتصال');
    expect(screen.queryByTestId('offline-prepared')).toBeNull();
});
it('confirms completion only after the worker finished preparing', () => {
    render(<OfflinePreparationStatus status={{ ...base, ready: true, preparation: { status: 'complete', saved: 8, processed: 8, total: 8 } }} />);
    expect(screen.getByTestId('offline-prepared')).toBeTruthy();
});
