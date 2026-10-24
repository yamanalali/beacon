// @vitest-environment node
import { IDBFactory } from 'fake-indexeddb';
import { beforeEach, describe, expect, it } from 'vitest';
import { data, entries, enqueue, mutation, cacheable, replayRequest, syncState, entryDetails } from '../../../public/offline-core.js';
import { localReport } from '../../../public/offline-reports.js';
let factory;
beforeEach(() => { factory = new IDBFactory(); });
const row = (id = 'one', scope = 'account-a') => ({ id, scope, method: 'POST', url: '/enterprises/E/transactions', headers: { 'content-type': 'application/json', 'x-xsrf-token': 'old' }, body: new TextEncoder().encode('{"amount":"100","_token":"stale"}').buffer, versions: {}, createdAt: 1, status: 'pending' });
it('isolates accounts and persists structured requests across database connections', async () => {
    await enqueue(row(), factory); await enqueue(row('two', 'account-b'), factory);
    expect((await entries('account-a', factory)).map(x => x.id)).toEqual(['one']);
    expect((await entries('account-b', factory)).map(x => x.id)).toEqual(['two']);
    expect((await data('entries', 'get', 'one', factory)).body.byteLength).toBeGreaterThan(0);
});
it('does not lose the prior queue when storage capacity is reached', async () => {
    for (let n = 0; n < 100; n++) await enqueue(row(String(n)), factory);
    await expect(enqueue(row('overflow'), factory)).rejects.toThrow(/ممتلئة/);
    expect(await entries('account-a', factory)).toHaveLength(100);
    // Updating an existing operation at capacity is safe.
    await enqueue({ ...row('0'), status: 'review' }, factory);
    expect(await entries('account-a', factory)).toHaveLength(100);
});
it('rejects an oversized attachment atomically', async () => {
    await enqueue(row(), factory);
    await expect(enqueue({ ...row('big'), body: new ArrayBuffer(50 * 1024 * 1024) }, factory)).rejects.toThrow();
    expect(await entries('account-a', factory)).toHaveLength(1);
});
it('replays JSON with fresh CSRF, the same idempotency key and no Inertia redirect headers', async () => {
    const request = await replayRequest({ ...row(), headers: { ...row().headers, 'X-Inertia': 'true', 'X-Inertia-Version': 'old' } }, 'fresh');
    expect(JSON.parse(request.body)).toEqual({ amount: '100', _beacon_versions: '{}' });
    expect(request.headers.get('X-CSRF-TOKEN')).toBe('fresh');
    expect(request.headers.get('X-XSRF-TOKEN')).toBeNull();
    expect(request.headers.get('X-Inertia')).toBeNull();
    expect(request.headers.get('X-Beacon-Operation-Key')).toBe('one');
});
it('preserves binary uploads and method override while removing stale form tokens', async () => {
    const form = new FormData(); form.append('_token', 'old'); form.append('_method', 'PUT'); form.append('file', new Blob(['file bytes'], { type: 'application/pdf' }), 'invoice.pdf');
    const request = new Request('https://beacon.test/upload', { method: 'POST', body: form });
    const entry = { ...row(), headers: Object.fromEntries(request.headers), body: await request.arrayBuffer() };
    const replay = await replayRequest(entry, 'fresh');
    expect(replay.body.get('_token')).toBeNull();
    expect(replay.body.get('_method')).toBe('PUT');
    expect(await replay.body.get('file').text()).toBe('file bytes');
    expect(replay.headers.get('Content-Type')).toBeNull();
    const details = await entryDetails(entry);
    expect(details.find(x => x.file)).toMatchObject({ value: 'invoice.pdf' });
    expect(details.some(x => ['_token', '_method'].includes(x.name))).toBe(false);
});
it('only queues supported business writes and never auth, sharing or approval operations', () => {
    expect(mutation('POST', '/enterprises/E/transactions')).toBeTruthy();
    expect(mutation('PUT', '/documents/D')).toBeTruthy();
    for (const url of ['/logout', '/login', '/transactions/T/approve', '/referrals/R/share', '/account/password', '/admin/users', '/operations/backups']) expect(mutation('POST', url)).toBeNull();
    expect(cacheable('/enterprises/E/transactions/create')).toBe(true);
    expect(cacheable('/account/two-factor')).toBe(false);
    expect(cacheable('/invitations/secret')).toBe(false);
    expect(cacheable('/offline/session')).toBe(false);
});
it.each([[401, 'auth'], [419, 'auth'], [409, 'review'], [422, 'review'], [403, 'review'], [503, 'pending']])('pauses or reviews HTTP %i', (status, state) => expect(syncState(status)).toBe(state));
const tx = (overrides = {}) => ({ status: 'posted', type: 'sale', category: 'general', amount: '100.30', currency: 'USD', date: '2026-10-02', counterparty: 'Shop', settlements: [{ date: '2026-10-03', amount: '40.10' }], ...overrides });
const snapshot = { complete: true, saved_at: '2026-10-25', types: { sale: 'مبيعات', expense: 'مصروف' }, categories: { sale: { general: 'عام' } }, transactions: [tx(), tx({ type: 'expense', amount: '25.10', settlements: [{ date: '2026-10-04', amount: '25.10' }] }), tx({ currency: 'SYP', amount: '1000.00', settlements: [] }), tx({ status: 'pending_approval', amount: '99999.00' }), tx({ status: 'superseded', amount: '99999.00' })] };
const filters = { report: 'income_statement', from: '2026-10-01', to: '2026-10-31', basis: 'accrual' };
describe('local financial reports', () => {
    it('keeps exact amounts, separate currencies and only posted transactions', () => {
        expect(localReport(snapshot, filters).sections[0].rows).toEqual([['SYP', '1000.00', '0.00', '1000.00'], ['USD', '100.30', '25.10', '75.20']]);
    });
    it('uses settlement dates and never double counts revenue', () => {
        expect(localReport(snapshot, { ...filters, basis: 'cash' }).sections[0].rows).toEqual([['USD', '40.10', '25.10', '15.00']]);
        expect(localReport(snapshot, { ...filters, report: 'cash_flow', to: '2026-10-03' }).sections[0].rows).toEqual([['USD', '40.10', '0.00', '40.10']]);
    });
    it('calculates debts as of the cutoff date', () => {
        expect(localReport(snapshot, { ...filters, report: 'receivables', to: '2026-10-02' }).sections[0].rows).toEqual([['SYP', '1000.00'], ['USD', '100.30']]);
        expect(localReport(snapshot, { ...filters, report: 'payables' }).sections[0].rows).toEqual([]);
    });
    it.each(['sales', 'expenses', 'monthly'])('supports %s without server queries', report => expect(localReport(snapshot, { ...filters, report }).sections[0].rows.length).toBeGreaterThan(0));
    it('refuses incomplete snapshots, invalid dates and unknown reports', () => {
        expect(() => localReport({ ...snapshot, complete: false }, filters)).toThrow();
        expect(() => localReport(snapshot, { ...filters, to: '2026-01-01' })).toThrow();
        expect(() => localReport(snapshot, { ...filters, report: 'unknown' })).toThrow();
    });
    it('does not round large decimal totals through JavaScript floats', () => {
        const input = { ...snapshot, transactions: [tx({ amount: '99999999999999.99', settlements: [] }), tx({ amount: '0.01', settlements: [] })] };
        expect(localReport(input, filters).sections[0].rows[0][1]).toBe('100000000000000.00');
    });
});

it('preserves insertion order when multiple requests share a millisecond', async () => {
    await enqueue(row('zz-last-key'), factory);
    await enqueue(row('aa-first-key'), factory);
    expect((await entries('account-a', factory)).map(entry => entry.id)).toEqual(['zz-last-key', 'aa-first-key']);
});
