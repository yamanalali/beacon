import { localReport } from './offline-reports.js';
import { data, entries, metadata, setMetadata, clearScope, enqueue, mutation, cacheable, replayRequest, syncState, entryDetails, MAX_AGE } from './offline-core.js';
const PRIVATE = 'beacon-private-';
let syncing;
const clientScopes = new Map();
const clientScope = async id => clientScopes.get(id) ?? await metadata('client:' + id);
const relative = url => { const u = new URL(url, self.location.origin); return u.pathname + u.search; };
const sameOrigin = url => new URL(url, self.location.origin).origin === self.location.origin;
const cacheName = scope => PRIVATE + scope;
const key = (url, inertia) => new Request(self.location.origin + '/__offline_cache__?kind=' + (inertia ? 'page' : 'html') + '&url=' + encodeURIComponent(relative(url)));
const notify = async () => { for (const c of await self.clients.matchAll({ includeUncontrolled: true })) c.postMessage({ type: 'BEACON_OFFLINE_CHANGED' }); };
async function scopeNow() { return (await metadata('unlocked')) ? await metadata('scope') : null; }
async function purgeCaches() { for (const n of await caches.keys()) if (n.startsWith(PRIVATE)) await caches.delete(n); }
async function lock(clear = false) {
    const scope = await metadata('scope');
    await setMetadata('unlocked', false);
    await purgeCaches();
    if (clear && scope) await clearScope(scope);
    await notify();
}
async function updateAuthorization(scope, authorization, existing = true) {
    if (!authorization) return;
    const previous = await metadata('authorization:' + scope);
    if (existing && previous !== authorization) {
        await caches.delete(cacheName(scope));
        for (const entry of await entries(scope)) {
            entry.status = 'review';
            entry.errors = ['تحدّث أمان الحساب أو صلاحياته. راجع هذا الطلب قبل إعادة إرساله.'];
            await data('entries', 'put', entry);
        }
    }
    await setMetadata('authorization:' + scope, authorization);
}
async function cached(url, inertia = false) {
    const scope = await scopeNow(); if (!scope) return null;
    const response = await (await caches.open(cacheName(scope))).match(key(url, inertia));
    if (!response || Date.now() - Number(response.headers.get('X-Beacon-Cached-At')) > MAX_AGE) return null;
    return response;
}
async function remember(request, response) {
    const scope = await scopeNow();
    if (!scope || !response.ok || response.headers.get('X-Beacon-Offline-Scope') !== scope || !sameOrigin(response.url || request.url)) return;
    if (request.headers.has('X-Inertia-Partial-Data') || request.headers.has('X-Inertia-Partial-Component') || request.headers.has('X-Inertia-Except-Once-Props')) return;
    const authorization = response.headers.get('X-Beacon-Authorization');
    if (authorization && authorization !== await metadata('authorization:' + scope)) {
        const session = await fetch('/offline/session', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!session.ok) { await lock(); return; }
        const current = await session.json();
        if (current.scope !== scope || current.authorization !== authorization) return;
        await updateAuthorization(scope, authorization);
    }
    const type = response.headers.get('Content-Type') ?? '';
    // API JSON is not a page and never substitutes for an HTML navigation.
    const inertia = response.headers.get('X-Inertia') === 'true';
    if (type.includes('application/json') && !inertia) return;
    const blob = await response.clone().blob();
    if (blob.size > 20 * 1024 * 1024) return;
    const headers = new Headers(response.headers); headers.delete('Content-Length'); headers.delete('Content-Encoding'); headers.set('X-Beacon-Cached-At', String(Date.now()));
    const cache = await caches.open(cacheName(scope));
    headers.set('X-Beacon-Cached-Bytes', String(blob.size));
    const oldKeys = await cache.keys();
    let total = 0;
    for (const old of oldKeys) total += Number((await cache.match(old))?.headers.get('X-Beacon-Cached-Bytes') ?? 0);
    for (const old of oldKeys) {
        if (total + blob.size < 100 * 1024 * 1024) break;
        if (new URL(old.url).searchParams.get('url') === '/offline') continue;
        total -= Number((await cache.match(old))?.headers.get('X-Beacon-Cached-Bytes') ?? 0); await cache.delete(old);
    }
    await cache.put(key(request.url, inertia), new Response(blob, { headers, status: response.status }));
    if (inertia) {
        const template = await cached('/offline', false);
        if (template) {
            const html = await template.text(), page = await response.clone().json();
            const initial = html.match(/<script[^>]*data-page="[^"]+"[^>]*>([\s\S]*?)<\/script>/);
            if (initial && JSON.parse(initial[1]).version === page.version) {
                const htmlHeaders = new Headers(headers); htmlHeaders.delete('X-Inertia'); htmlHeaders.set('Content-Type', 'text/html; charset=utf-8');
                await cache.put(key(request.url, false), new Response(htmlWithPage(html, page), { headers: htmlHeaders }));
            }
        }
    }
    const keys = await cache.keys();
    for (const old of keys.filter(r => new URL(r.url).searchParams.get('url') !== '/offline').slice(0, Math.max(0, keys.length - 240))) await cache.delete(old);
}
async function pageResponse(source, extra = {}, error = null) {
    let response = await cached(source, true) ?? await cached('/offline', true);
    let page;
    try { page = await response?.json(); } catch { /* A stale cache is not allowed to become a JSON error modal. */ }
    if (!page?.props?.auth?.user) return new Response(await (await caches.match('/offline.html'))?.text() ?? 'Reconnect to open Beacon.', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    const savedAt = response.headers.get('X-Beacon-Cached-At');
    page.props.flash = {};
    page.props.errors = error ? { offline: error } : {};
    page.props.offline = { cached: true, cached_at: savedAt, ...extra };
    if (!error && extra.queued_id) { page.component = 'Offline/Index'; page.url = '/offline'; }
    return new Response(JSON.stringify(page), { status: 200, headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true', 'Cache-Control': 'no-store' } });
}
async function saveReportData(id) {
    const scope = await scopeNow(); if (!scope) return;
    const url = '/offline/report-data/' + id;
    try {
        const result = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!result.ok || result.headers.get('X-Beacon-Offline-Scope') !== scope) return;
        const text = await result.text(); if (text.length > 20 * 1024 * 1024) return;
        const headers = new Headers(result.headers); headers.delete('Content-Length'); headers.delete('Content-Encoding'); headers.set('X-Beacon-Cached-At', String(Date.now()));
        await (await caches.open(cacheName(scope))).put(key(url, false), new Response(text, { headers }));
    } catch { /* Retain the prior dated snapshot if the connection drops. */ }
}
function linkedPages(page) {
    const props = page.props ?? {};
    const links = [];
    const businesses = [props.enterprise, ...(props.enterprises?.data ?? [])].filter(Boolean);
    for (const enterprise of businesses) {
        if (!/^[A-Za-z0-9-]+$/.test(enterprise.public_id ?? '')) continue;
        const base = '/enterprises/' + enterprise.public_id;
        links.push(base, base + '/edit', ...['transactions', 'transactions/create', 'documents', 'reports', 'compliance', 'readiness', 'advisory', 'referrals', 'mfi-matching'].map(path => base + '/' + path));
    }
    for (const [prop, path] of [['transactions', 'transactions'], ['documents', 'documents'], ['assessments', 'readiness-assessments'], ['referrals', 'referrals']]) {
        const rows = Array.isArray(props[prop]) ? props[prop] : props[prop]?.data ?? [];
        for (const row of rows) if (/^[A-Za-z0-9-]+$/.test(row.public_id ?? '')) links.push('/' + path + '/' + row.public_id);
    }
    return links;
}
async function warm(urls, expand = true, track = false) {
    const scope = await scopeNow();
    const eligible = url => sameOrigin(url) && cacheable(new URL(url, self.location.origin).pathname) && !/\/(?:export|download|preview)(?:\/|$)/.test(url);
    const todo = [...new Set(urls.filter(eligible).map(relative))], done = new Set();
    let saved = 0;
    const progress = async status => {
        if (!track || !scope) return;
        await setMetadata('preparation:' + scope, { status, saved, processed: done.size, total: Math.min(110, todo.length) });
        await notify();
    };
    await progress('preparing');
    for (let i = 0; i < todo.length && done.size < 110; i++) {
        const url = todo[i];
        if (done.has(url) || !sameOrigin(url) || !cacheable(new URL(url, self.location.origin).pathname) || /\/(?:export|download|preview)(?:\/|$)/.test(url)) continue;
        if (!scope || await scopeNow() !== scope) { await progress('interrupted'); return; }
        done.add(url);
        let available = false;
        for (const inertia of [true, false]) {
            const version = await metadata('inertiaVersion');
            const req = new Request(new URL(url, self.location.origin), { credentials: 'same-origin', headers: inertia ? { 'X-Inertia': 'true', 'X-Inertia-Version': version ?? '', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml' } : { Accept: 'text/html' } });
            try {
                const response = await fetch(req);
                if (response.status >= 500 || [401, 419].includes(response.status) || (response.redirected && new URL(response.url).pathname === '/login')) throw new Error('Preparation interrupted');
                await remember(req, response);
                if (response.ok && response.headers.get('X-Beacon-Offline-Scope') === scope && await cached(url, inertia)) available = true;
                if (inertia && response.ok && response.headers.get('X-Inertia') === 'true') {
                    const page = await response.clone().json();
                    if (expand) for (const link of linkedPages(page).filter(eligible).map(relative)) if (!todo.includes(link)) todo.push(link);
                    if (page.component === 'FinancialReports/Show' && page.props?.enterprise?.public_id) await saveReportData(page.props.enterprise.public_id);
                }
            } catch { if (available) saved++; await progress('interrupted'); return; }
        }
        if (available) saved++;
        if (track && (done.size % 2 === 0 || done.size === todo.length)) await progress('preparing');
    }
    await progress('complete');
}
function htmlWithPage(html, page) {
    return html.replace(/(<script[^>]*data-page="[^"]+"[^>]*>)[\s\S]*?(<\/script>)/, (_, open, close) => open + JSON.stringify(page).replace(/</g, '\\u003c') + close);
}
async function offlineReport(request, inertia) {
    const match = new URL(request.url).pathname.match(/^\/enterprises\/([A-Za-z0-9-]+)\/reports$/);
    if (!match) return null;
    const snapshot = await cached('/offline/report-data/' + match[1], false);
    const base = await cached('/enterprises/' + match[1] + '/reports', true);
    if (!snapshot || !base) return null;
    try {
        const page = await base.json();
        const filters = { ...page.props.filters, ...Object.fromEntries(new URL(request.url).searchParams) };
        const report = localReport(await snapshot.json(), filters);
        page.props = { ...page.props, report, filters, print: false, errors: {}, flash: {}, offline: { cached: true, cached_at: snapshot.headers.get('X-Beacon-Cached-At') } };
        page.url = relative(request.url);
        if (inertia) return new Response(JSON.stringify(page), { headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true' } });
        const template = await cached('/offline', false); if (!template) return null;
        const headers = new Headers(template.headers); headers.delete('Content-Length'); headers.delete('Content-Encoding'); headers.set('Content-Type', 'text/html; charset=utf-8');
        return new Response(htmlWithPage(await template.text(), page), { headers });
    } catch { return null; }
}
async function installAssets() {
    const response = await fetch('/offline/assets', { cache: 'no-store' });
    if (!response.ok) throw new Error('Build assets are not available.');
    const manifest = await response.json();
    const name = 'beacon-static-' + manifest.version;
    if (await metadata('staticCache') === name && await caches.has(name)) return;
    const cache = await caches.open(name);
    // A worker is installed only after the entire app (including lazy page chunks) is saved.
    await cache.addAll(manifest.assets);
    await setMetadata('staticCache', name);
}
self.addEventListener('install', event => { event.waitUntil(installAssets()); });
self.addEventListener('activate', event => { event.waitUntil((async () => {
    const active = await metadata('staticCache');
    for (const name of await caches.keys()) if (name.startsWith('beacon-static-') && name !== active) await caches.delete(name);
    const live = new Set((await self.clients.matchAll({ includeUncontrolled: true })).map(client => 'client:' + client.id));
    for (const row of await data('meta', 'getAll')) if (row.key.startsWith('client:') && !live.has(row.key)) await data('meta', 'delete', row.key);
    await self.clients.claim();
})()); });
async function queueRequest(request, id, label) {
    const scope = await scopeNow(); if (!scope) throw new Error('افتح التطبيق وأنت متصل أولاً لتجهيز العمل دون اتصال.');
    const source = request.referrer && sameOrigin(request.referrer) ? relative(request.referrer) : '/offline';
    const sourcePage = await cached(source, true);
    let versions = {}, formOptions = null, enterprise = null;
    if (sourcePage) {
        try { const props = (await sourcePage.clone().json()).props; versions = props.offline_versions ?? JSON.parse(sourcePage.headers.get('X-Beacon-Offline-Versions') ?? '{}'); formOptions = props.options ?? null; enterprise = props.enterprise?.public_id ?? null; } catch { /* Missing versions stop unsafe edits at the server. */ }
    }
    const body = await request.clone().arrayBuffer();
    await enqueue({ id, scope, method: request.method, url: relative(request.url), source, label, body,
        headers: Object.fromEntries([...request.headers].filter(([name]) => !/^(cookie|authorization|x-csrf-token|x-xsrf-token)$/i.test(name))),
        createdAt: Date.now(), status: 'pending', errors: [], versions, formOptions, enterprise });
    try { await self.registration.sync?.register('beacon-outbox'); } catch { /* Online/focus/manual sync works without Background Sync. */ }
    await notify();
    return request.headers.get('X-Inertia') === 'true' ? pageResponse(source, { queued_id: id })
        : new Response(JSON.stringify({ offline_queued: true, message: 'حُفظ الطلب على هذا الجهاز وسيُرسل عند عودة الاتصال.' }), { status: 202, headers: { 'Content-Type': 'application/json' } });
}
async function handleMutation(request, clientId) {
    if (await scopeNow() && await clientScope(clientId) && await clientScope(clientId) !== await scopeNow()) return new Response(null, { status: 409, headers: { 'X-Inertia-Location': '/offline.html' } });
    const path = new URL(request.url).pathname;
    if (path === '/logout') {
        await setMetadata('logoutPending', true);
        await lock(true);
        try { const result = await fetch(request); if (result.ok || result.redirected) await setMetadata('logoutPending', false); return result; }
        catch { return new Response(null, { status: 409, headers: { 'X-Inertia-Location': '/offline.html' } }); }
    }
    // multipart PUT is sent as POST + _method by Inertia/Laravel.
    let method = request.method;
    if (method === 'POST') {
        try {
            const type = request.headers.get('Content-Type') ?? '';
            if (/multipart|urlencoded/.test(type)) method = String((await request.clone().formData()).get('_method') ?? method).toUpperCase();
            else if (type.includes('json')) method = String((await request.clone().json())._method ?? method).toUpperCase();
        } catch { /* Original validation handles malformed forms. */ }
    }
    const label = mutation(method, path);
    if (!label) {
        try { return await fetch(request); } catch {
            const message = 'هذا الإجراء يحتاج اتصالاً بالخادم. بيانات النموذج لم تُرسل؛ حاول بعد عودة الاتصال.';
            if (request.headers.get('X-Inertia') === 'true') return pageResponse(request.referrer || '/offline', {}, message);
            return new Response(JSON.stringify({ message }), { status: 503, headers: { 'Content-Type': 'application/json' } });
        }
    }
    const scope = await scopeNow();
    if (!scope) return fetch(request); // Unsupported/unprepared browsers keep normal server behavior.
    const id = crypto.randomUUID();
    const headers = new Headers(request.headers);
    headers.set('X-Beacon-Operation-Key', id); headers.set('X-Beacon-Offline-Scope', scope);
    try { const result = await fetch(new Request(request.clone(), { headers })); if (result.status !== 503) return result; }
    catch { /* The server may have committed: replay uses the SAME operation key. */ }
    try { return await queueRequest(request, id, label); } catch (error) {
        await setMetadata('lastError', error.message ?? 'تعذر الحفظ على الجهاز.'); await notify();
        return request.headers.get('X-Inertia') === 'true' ? pageResponse(request.referrer || '/offline', {}, error.message)
            : new Response(JSON.stringify({ message: 'تعذر الحفظ على الجهاز. لا تغلق النموذج.' }), { status: 507, headers: { 'Content-Type': 'application/json' } });
    }
}
async function finishLogout() {
    if (!await metadata('logoutPending')) return false;
    try {
        const session = await fetch('/offline/session', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (session.ok) {
            const { csrf } = await session.json();
            const response = await fetch('/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok && !response.redirected) return true;
        } else if (![401, 403].includes(session.status)) return true;
        await setMetadata('logoutPending', false);
    } catch { return true; }
    return false;
}
async function handleGet(request, clientId) {
    const url = new URL(request.url);
    if (/^\/(?:build\/|icons\/|fonts\/)/.test(url.pathname) || ['/offline.html', '/manifest.webmanifest', '/offline-core.js', '/offline-reports.js'].includes(url.pathname)) {
        return await caches.match(request) ?? fetch(request);
    }
    if (!cacheable(url.pathname)) {
        try { return await fetch(request); }
        catch {
            if (request.headers.get('X-Inertia') === 'true') return pageResponse(request.referrer || '/offline', {}, 'هذه الصفحة تحتاج اتصالاً مباشراً بالخادم.');
            if (request.mode === 'navigate' || request.headers.get('Accept')?.includes('text/html')) return caches.match('/offline.html');
            return new Response(JSON.stringify({ message: 'هذه الميزة تحتاج اتصالاً بالخادم.' }), { status: 503, headers: { 'Content-Type': 'application/json' } });
        }
    }
    if (await scopeNow() && await clientScope(clientId) && await clientScope(clientId) !== await scopeNow()) return caches.match('/offline.html');
    if (await finishLogout()) return caches.match('/offline.html');
    try {
        const result = await fetch(request);
        if (result.status !== 503) {
            if ([401, 403].includes(result.status)) await lock();
            try { await remember(request, result); } catch { /* Caching must never break a successful online response. */ } return result;
        }
    } catch { /* Network first: a cached page always advertises its age. */ }
    await setMetadata('lastOfflineAt', Date.now()); await notify();
    const inertia = request.headers.get('X-Inertia') === 'true';
    const local = await offlineReport(request, inertia); if (local) return local;
    const result = await cached(request.url, inertia);
    if (result) {
        if (inertia) return pageResponse(request.url);
        if ((result.headers.get('Content-Type') ?? '').includes('text/html')) {
            const html = await result.text();
            const match = html.match(/<script[^>]*data-page="[^"]+"[^>]*>([\s\S]*?)<\/script>/);
            if (match) {
                try {
                    const page = JSON.parse(match[1]); page.props.flash = {}; page.props.errors = {}; page.props.offline = { cached: true, cached_at: result.headers.get('X-Beacon-Cached-At') };
                    const headers = new Headers(result.headers); headers.delete('Content-Length'); headers.delete('Content-Encoding');
                    return new Response(htmlWithPage(html, page), { headers });
                } catch { /* The original HTML is retained for compatibility. */ }
            }
            return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
        }
        return result;
    }
    if (inertia) return pageResponse('/offline', { missing_page: relative(request.url) });
    return await cached('/offline', false) ?? await caches.match('/offline.html');
}
self.addEventListener('fetch', event => {
    if (!sameOrigin(event.request.url)) return;
    if (event.request.method === 'GET') event.respondWith(handleGet(event.request, event.clientId));
    else event.respondWith(handleMutation(event.request, event.clientId));
});
async function synchronize() {
    const scope = await scopeNow(); if (!scope || await metadata('logoutPending')) return;
    let session;
    try {
        const response = await fetch('/offline/session', { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) { if ([401, 403, 419].includes(response.status)) await lock(); throw new Error('انتهت الجلسة. سجّل الدخول بالحساب نفسه لاستكمال المزامنة.'); }
        session = await response.json();
        if (session.scope !== scope) throw new Error('الحساب الحالي مختلف. سجّل الدخول بالحساب صاحب العمليات.');
    } catch (error) { await setMetadata('lastError', error.message ?? 'تعذر الاتصال بالخادم.'); await notify(); return; }
    await updateAuthorization(scope, session.authorization);
    await setMetadata('lastError', null);
    const refreshUrls = new Set(['/offline']);
    for (const entry of await entries(scope)) {
        if (entry.status === 'review') break; // Preserve order, never skip a conflict silently.
        try {
            const response = await fetch(entry.url, await replayRequest(entry, session.csrf));
            const body = await response.json().catch(() => null);
            if (response.ok && body?.synced === true && body.operation_key === entry.id) {
                await data('entries', 'delete', entry.id);
                // Carry forward only versions acknowledged by this operation, preserving
                // concurrent-change detection between each operation in an offline batch.
                for (const next of await entries(scope)) {
                    for (const [resource, version] of Object.entries(body.versions ?? {})) {
                        if (next.versions?.[resource] === entry.versions?.[resource]) next.versions[resource] = version;
                    }
                    await data('entries', 'put', next);
                }
                refreshUrls.add(entry.source);
                if (body.url && sameOrigin(body.url)) refreshUrls.add(body.url);
                if (entry.enterprise) refreshUrls.add('/enterprises/' + entry.enterprise + '/reports');
                await setMetadata('lastSync', { at: Date.now(), label: entry.label, url: body.url });
                // Invalidate snapshots only after every queued operation has a durable acknowledgement.
                await notify(); continue;
            }
            entry.status = syncState(response.status);
            entry.errors = response.status === 422 ? Object.values(body?.errors ?? {}).flat().filter(x => typeof x === 'string')
                : [response.status === 409 ? 'تغير السجل. افتح أحدث نسخة وأعد إدخال التغيير بعد المراجعة.' : response.status === 403 ? 'لم تعد لديك صلاحية لهذا الإجراء.' : response.status === 404 ? 'لم يعد السجل متاحاً.' : response.status === 401 || response.status === 419 ? 'سجّل الدخول بالحساب نفسه لاستكمال المزامنة.' : 'تعذر إرسال الطلب. سيبقى محفوظاً لإعادة المحاولة.'];
            if (!entry.errors.length) entry.errors = ['راجع البيانات المطلوبة قبل إعادة المحاولة.'];
            await data('entries', 'put', entry); await notify(); break;
        } catch { await setMetadata('lastError', 'لا يزال الاتصال غير متاح. العمليات محفوظة على الجهاز.'); await notify(); break; }
    }
    if (refreshUrls.size > 1) await warm([...refreshUrls], false);
    if (!(await entries(scope)).length) {
        await setMetadata('lastOfflineAt', null);
        await warm(['/offline'], false);
    }
}
const sync = () => { if (!syncing) { syncing = synchronize().finally(() => { syncing = null; void notify(); }); void notify(); } return syncing; };
self.addEventListener('sync', event => { if (event.tag === 'beacon-outbox') event.waitUntil(sync()); });
self.addEventListener('message', event => {
    // Only controlled same-origin windows can configure/read this account's storage.
    if (!event.source?.url || !sameOrigin(event.source.url)) return;
    event.waitUntil((async () => {
        try {
            const { type, scope, urls = [], id } = event.data ?? {};
            if (scope) { clientScopes.set(event.source.id, scope); await setMetadata('client:' + event.source.id, scope); }
            const currentScope = await scopeNow();
            if (currentScope && await clientScope(event.source.id) && await clientScope(event.source.id) !== currentScope && !['CONFIGURE', 'STATUS'].includes(type)) throw new Error('تغير الحساب. افتح التطبيق من جديد قبل المتابعة.');
            if (type === 'CONFIGURE') {
                const response = await fetch('/offline/session', { headers: { Accept: 'application/json' }, cache: 'no-store' });
                const session = response.ok ? await response.json() : null;
                if (!session || session.scope !== scope) throw new Error('يلزم تسجيل الدخول لتجهيز هذا الجهاز.');
                const oldVersion = await metadata('inertiaVersion');
                if (oldVersion && oldVersion !== session.version) await purgeCaches();
                await setMetadata('inertiaVersion', session.version);
                await installAssets();
                const previous = await metadata('scope');
                if (previous && previous !== scope) { await clearScope(previous); await purgeCaches(); }
                await updateAuthorization(scope, session.authorization, previous === scope);
                await setMetadata('scope', scope); await setMetadata('unlocked', true);
                await warm(['/offline', ...urls], true, true); await sync();
            } else if (type === 'LOCK') await lock();
            else if (type === 'SYNC') await sync();
            else if (type === 'PREPARE') await warm(urls, true, true);
            else if (type === 'CLEAR') { await lock(true); await setMetadata('scope', null); }
            else if (type === 'DETAILS' || type === 'FILE') {
                const row = await data('entries', 'get', id);
                if (!row || row.scope !== await scopeNow()) throw new Error('العملية غير متاحة لهذا الحساب.');
                if (type === 'DETAILS') { event.ports[0]?.postMessage({ ok: true, details: await entryDetails(row) }); return; }
                const form = await new Response(row.body, { headers: { 'Content-Type': row.headers['content-type'] } }).formData();
                const file = form.get(event.data.field);
                if (!(file instanceof Blob)) throw new Error('المرفق غير متاح.');
                event.ports[0]?.postMessage({ ok: true, file, name: file.name ?? 'attachment' }); return;
            }
            else if (type === 'REMOVE' || type === 'RETRY') {
                if (syncing) throw new Error('انتظر انتهاء المزامنة قبل تعديل قائمة الانتظار.');
                const row = await data('entries', 'get', id);
                if (!row || row.scope !== await scopeNow()) throw new Error('العملية غير متاحة لهذا الحساب.');
                if (type === 'REMOVE') await data('entries', 'delete', id);
                else { row.status = 'pending'; row.errors = []; await data('entries', 'put', row); await sync(); }
                await notify();
            }
            const current = await scopeNow();
            const active = await clientScope(event.source.id) && await clientScope(event.source.id) !== current ? null : current;
            // Messages never return request bodies or authentication material.
            const queue = active ? (await entries(active)).map(({ id, label, createdAt, status, errors, source }) => ({ id, label, createdAt, status, errors, source })) : [];
            const saved = active ? await (await caches.open(cacheName(active))).keys() : [];
            const pageMap = new Map();
            const names = { 'SMEs/Index': 'المنشآت', 'SMEs/Create': 'إضافة منشأة', 'SMEs/Edit': 'تعديل المنشأة', 'SMEs/Show': 'ملف المنشأة', 'Referrals/Show': 'تفاصيل إحالة', 'Referrals/MfiInbox': 'إحالات التمويل', 'Mfi/Matching': 'مؤسسات التمويل المناسبة', 'Mfi/Admin': 'إدارة مؤسسات التمويل', 'Dashboard/Sme': 'لوحة المنشأة', 'Dashboard/Beacon': 'لوحة البرنامج', 'Transactions/Form': 'إضافة عملية', 'Transactions/Index': 'العمليات', 'Transactions/Show': 'تفاصيل عملية', 'Documents/Index': 'المستندات', 'Documents/Show': 'تفاصيل مستند', 'FinancialReports/Show': 'التقارير', 'Compliance/Enterprise': 'الامتثال', 'Readiness/Enterprise': 'الجاهزية', 'Readiness/Assessment': 'التقييم', 'Advisory/Index': 'المتابعة والاستشارات', 'Referrals/Enterprise': 'الإحالات', 'Offline/Index': 'العمل دون اتصال' };
            const privateCache = active ? await caches.open(cacheName(active)) : null;
            for (const savedRequest of saved) {
                const u = new URL(savedRequest.url), url = u.searchParams.get('url');
                if (!url || url.startsWith('/offline/report-data/')) continue;
                const response = await privateCache.match(savedRequest);
                if (Date.now() - Number(response?.headers.get('X-Beacon-Cached-At')) > MAX_AGE) continue;
                let title = 'صفحة محفوظة';
                if (u.searchParams.get('kind') === 'page') {
                    try { const p = await response.json(); title = (names[p.component] ?? 'مساحة العمل') + (p.props?.enterprise?.business_name ? ' — ' + p.props.enterprise.business_name : ''); } catch { continue; }
                } else if (pageMap.has(url)) continue;
                if (title !== 'صفحة محفوظة' || !pageMap.has(url)) pageMap.set(url, { url, title });
            }
            const pages = [...pageMap.values()];
            event.ports[0]?.postMessage({ ok: true, active: !!active, preparation: active ? await metadata('preparation:' + active) : null, queue, pages, syncing: !!syncing, lastSync: await metadata('lastSync'), error: await metadata('lastError'), offlineAt: await metadata('lastOfflineAt') });
        } catch (error) { event.ports[0]?.postMessage({ ok: false, error: error.message ?? 'تعذر تنفيذ الطلب على الجهاز.' }); }
    })());
});
