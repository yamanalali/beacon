import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import type { BeaconSharedProps } from '../types/shared';
export type QueuedOperation = { id: string; label: string; createdAt: number; status: 'pending' | 'auth' | 'review'; errors: string[]; source: string };
export type OfflineState = { supported: boolean; ready: boolean; busy: boolean; queue: QueuedOperation[]; pages: { url: string; title: string }[]; error: string | null; offlineAt?: number | null; lastSync?: { at: number; label: string; url: string | null } | null };
let state: OfflineState = { supported: false, ready: false, busy: false, queue: [], pages: [], error: null };
const listeners = new Set<() => void>();
const publish = (patch: Partial<OfflineState>) => { state = { ...state, ...patch }; for (const fn of listeners) fn(); };
const subscribe = (fn: () => void) => { listeners.add(fn); return () => { listeners.delete(fn); }; };
export const offlineReady = () => state.ready && !!navigator.serviceWorker?.controller;
export const offlineSnapshot = () => state;
export function useOffline() { return useSyncExternalStore(subscribe, offlineSnapshot, offlineSnapshot); }
let registration: Promise<ServiceWorkerRegistration> | null = null;
let account: string | null = null;
let initializing = false;
const warmed = new Set<string>();
export type OfflineReply = { details?: { name: string; label: string; value: string; file: boolean; size?: number }[]; file?: Blob; name?: string };
export async function offlineCommand(type: string, payload: Record<string, unknown> = {}) {
    const worker = navigator.serviceWorker?.controller ?? (await registration)?.active;
    if (!worker) throw new Error('يجري تجهيز العمل دون اتصال.');
    return new Promise<OfflineReply>((resolve, reject) => {
        const channel = new MessageChannel();
        const timer = window.setTimeout(() => { channel.port1.close(); reject(new Error('لم يكتمل الطلب. تحقق من حالة الاتصال وحاول مجدداً.')); }, 120000);
        channel.port1.onmessage = ({ data }) => {
            clearTimeout(timer); channel.port1.close();
            if (!data.ok) { publish({ error: data.error, busy: false }); reject(new Error(data.error)); return; }
            if (data.details || data.file) { resolve(data); return; }
            const synchronized = state.queue.length > 0 && data.queue.length === 0 && data.lastSync?.at !== state.lastSync?.at;
            publish({ ready: data.active, queue: data.queue, pages: data.pages, lastSync: data.lastSync, error: data.error, offlineAt: data.offlineAt, busy: data.syncing });
            resolve(data);
            if (synchronized && navigator.onLine) router.reload();
        };
        worker.postMessage({ type, scope: account, ...payload }, [channel.port2]);
    });
}
export async function prepareOffline(urls: string[]) {
    publish({ busy: true });
    try { await offlineCommand('PREPARE', { urls }); }
    catch (error) { publish({ busy: false, error: error instanceof Error ? error.message : 'تعذر تجهيز الصفحات.' }); }
}
export async function syncOffline() {
    publish({ busy: true });
    try { await offlineCommand('SYNC'); }
    catch (error) { publish({ busy: false, error: error instanceof Error ? error.message : 'تعذرت المزامنة.' }); }
}
export function confirmOfflineLogout(): boolean {
    return state.queue.length === 0 || window.confirm('لديك عمليات لم تُرسل بعد. تسجيل الخروج يحذف العمليات والملفات المحفوظة على هذا الجهاز. هل تريد المتابعة؟');
}
export async function initializeOffline(props: BeaconSharedProps, url: string) {
    if (!('serviceWorker' in navigator) || !window.isSecureContext || import.meta.env.DEV) return;
    publish({ supported: true });
    try {
        if (!registration) {
            registration = navigator.serviceWorker.register('/sw.js', { type: 'module', updateViaCache: 'none' });
            navigator.serviceWorker.addEventListener('message', event => { if (event.data?.type === 'BEACON_OFFLINE_CHANGED') void offlineCommand('STATUS').catch(() => {}); });
            window.addEventListener('online', () => void syncOffline());
            window.addEventListener('focus', () => { if (navigator.onLine && state.ready) void syncOffline(); });
            window.setInterval(() => { if (navigator.onLine && state.ready && state.queue.length && !state.busy) void syncOffline(); }, 30000);
        }
        await registration;
        const ready = await Promise.race([navigator.serviceWorker.ready, new Promise<never>((_, reject) => setTimeout(() => reject(new Error('تعذر تجهيز ملفات التطبيق. افتح الصفحة وأنت متصل ثم حاول مجدداً.')), 25000))]);
        if (!navigator.serviceWorker.controller && ready.active) await new Promise<void>(resolve => {
            const done = () => { navigator.serviceWorker.removeEventListener('controllerchange', done); resolve(); };
            navigator.serviceWorker.addEventListener('controllerchange', done); setTimeout(done, 3000);
        });
        const scope = props.auth?.user?.draft_scope ?? null;
        if (!scope) { account = null; warmed.clear(); await offlineCommand('LOCK'); return; }
        const urls = [url, ...(props.auth.navigation ?? []).map(item => item.href), ...(props.auth.quick_record ? [props.auth.quick_record.manual_url] : [])];
        if (!navigator.onLine) { account = scope; await offlineCommand('STATUS'); return; }
        if (initializing) return;
        if (account !== scope || !state.ready) {
            initializing = true;
            try { await offlineCommand('CONFIGURE', { scope, urls }); account = scope; urls.forEach(u => warmed.add(u)); }
            finally { initializing = false; }
        } else if (!warmed.has(url)) { warmed.add(url); await prepareOffline([url]); }
    } catch (error) { publish({ error: error instanceof Error ? error.message : 'تعذر تجهيز العمل دون اتصال.' }); }
}
export function mountOffline(props: BeaconSharedProps, url: string) {
    void initializeOffline(props, url);
    router.on('navigate', event => void initializeOffline(event.detail.page.props, event.detail.page.url));
}
