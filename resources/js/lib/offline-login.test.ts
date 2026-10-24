import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { BeaconSharedProps } from '../types/shared';
vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn(), on: vi.fn() } }));
const userPage = { auth: { user: { draft_scope: 'user-a' }, navigation: [{ href: '/reports' }], quick_record: { manual_url: '/enterprises/E/transactions/create' } } } as unknown as BeaconSharedProps;
const guestPage = { auth: { user: null } } as unknown as BeaconSharedProps;
let commands: { type: string; urls?: string[]; scope?: string }[];
let events: Record<string, () => Promise<void>>;
let responseFor: (type: string) => object;
const complete = { status: 'complete', saved: 4, processed: 4, total: 4 };
let release: (value: unknown) => void;
beforeEach(() => {
    vi.resetModules(); vi.useFakeTimers(); vi.stubEnv('DEV', false);
    commands = []; events = {}; responseFor = () => complete;
    Object.defineProperty(window, 'isSecureContext', { configurable: true, value: true });
    class Channel {
        port1: { onmessage: ((event: { data: unknown }) => void) | null; close: () => void } = { onmessage: null, close: () => {} };
        port2 = { reply: (data: unknown) => this.port1.onmessage?.({ data }) };
    }
    vi.stubGlobal('MessageChannel', Channel);
    const controller = { postMessage: (message: { type: string }, ports: { reply: (data: unknown) => void }[]) => {
        commands.push(message);
        queueMicrotask(() => ports[0].reply({ ok: true, active: message.type !== 'LOCK', preparation: responseFor(message.type), queue: [], pages: [], syncing: false, error: null }));
    } };
    const registration = { active: controller };
    const pending = new Promise(resolve => { release = resolve; });
    vi.stubGlobal('navigator', { onLine: true, serviceWorker: { controller, register: vi.fn(() => pending), ready: Promise.resolve(registration), addEventListener: vi.fn() } });
    vi.spyOn(window, 'addEventListener').mockImplementation((name, fn) => { events[name] = fn as () => Promise<void>; });
});
afterEach(() => { vi.clearAllTimers(); vi.useRealTimers(); vi.unstubAllGlobals(); vi.unstubAllEnvs(); vi.restoreAllMocks(); });
describe('automatic preparation after login', () => {
    it('starts automatically and includes the manual form without a prepare action', async () => {
        const offline = await import('./offline');
        const task = offline.initializeOffline(userPage, '/dashboard/sme');
        expect(offline.offlineSnapshot().preparing).toBe(true);
        release({ active: navigator.serviceWorker.controller }); await task;
        expect(commands.map(command => command.type)).toEqual(['CONFIGURE']);
        expect(commands[0]).toMatchObject({ scope: 'user-a', urls: ['/dashboard/sme', '/reports', '/enterprises/E/transactions/create'] });
        expect(offline.offlineSnapshot()).toMatchObject({ ready: true, preparing: false, preparation: complete });
    });
    it('does not let delayed guest startup lock the newly signed-in account', async () => {
        const offline = await import('./offline');
        const guest = offline.initializeOffline(guestPage, '/login');
        const login = offline.initializeOffline(userPage, '/dashboard/sme');
        release({ active: navigator.serviceWorker.controller }); await Promise.all([guest, login]);
        expect(commands.map(command => command.type)).toEqual(['CONFIGURE']);
        expect(offline.offlineSnapshot().ready).toBe(true);
    });
    it('resumes interrupted preparation on reconnect without a manual command', async () => {
        let attempts = 0;
        responseFor = type => type === 'CONFIGURE' && ++attempts === 1 ? { ...complete, status: 'interrupted', saved: 1 } : complete;
        const offline = await import('./offline');
        const login = offline.initializeOffline(userPage, '/dashboard/sme');
        release({ active: navigator.serviceWorker.controller }); await login;
        expect(offline.offlineSnapshot().preparation?.status).toBe('interrupted');
        await events.online();
        expect(commands.map(command => command.type)).toEqual(['CONFIGURE', 'CONFIGURE', 'SYNC']);
        expect(offline.offlineSnapshot().preparation?.status).toBe('complete');
    });
});
