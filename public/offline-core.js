// Shared by the module service worker and its regression tests. No UI renders raw payloads.
export const DB_NAME = 'beacon-offline-v1';
export const MAX_ENTRIES = 100;
export const MAX_BYTES = 50 * 1024 * 1024;
export const MAX_AGE = 7 * 24 * 60 * 60 * 1000;
const ID = '[A-Za-z0-9-]+';
const rules = [
    ['POST', '^/enterprises$', 'إضافة منشأة'],
    ['PUT', `^/enterprises/${ID}$`, 'تعديل منشأة'],
    ['POST', `^/enterprises/${ID}/transactions$`, 'تسجيل عملية مالية'],
    ['POST', `^/transactions/${ID}/correct$`, 'تصحيح عملية مالية'],
    ['POST', `^/transactions/${ID}/settlements$`, 'تسجيل دفعة'],
    ['POST', `^/enterprises/${ID}/documents$`, 'رفع مستند'],
    ['PUT', `^/documents/${ID}$`, 'تعديل مستند'],
    ['POST', `^/documents/${ID}/archive$`, 'أرشفة مستند'],
    ['POST', `^/enterprises/${ID}/advisory$`, 'إضافة نشاط استشاري'],
    ['POST', `^/enterprises/${ID}/follow-ups$`, 'إضافة متابعة'],
    ['POST', `^/follow-ups/${ID}/complete$`, 'إكمال متابعة'],
    ['PUT', `^/enterprises/${ID}/compliance/${ID}$`, 'تحديث الامتثال'],
    ['POST', `^/enterprises/${ID}/readiness$`, 'بدء تقييم'],
    ['PUT', `^/readiness-assessments/${ID}/results/${ID}$`, 'حفظ إجابة تقييم'],
    ['POST', `^/enterprises/${ID}/referrals$`, 'إنشاء مسودة إحالة'],
    ['POST', `^/referrals/${ID}/status$`, 'تحديث حالة إحالة'],
    ['POST', `^/referrals/${ID}/outcome$`, 'تسجيل نتيجة إحالة'],
];
export function mutation(method, path) {
    return rules.find(([m, p]) => m === method.toUpperCase() && new RegExp(p).test(path))?.[2] ?? null;
}
export function cacheable(path) {
    if (/\/(?:export|download|preview)(?:\/|$)/.test(path)) return /^\/(?:enterprises|documents)\//.test(path);
    return path === '/' || path === '/offline' || /^\/(?:dashboard|enterprises|transactions|documents|financial-reports|reports|readiness-assessments|referrals|mfi|advisory|follow-ups)(?:\/|$)/.test(path)
        || /^\/admin\/(?:readiness|compliance|mfis)(?:\/|$)/.test(path) || /^\/ai\/pages\/knowledge(?:\/|$)/.test(path) || path === '/notifications/page';
}
export function openDatabase(factory = indexedDB) {
    return new Promise((resolve, reject) => {
        const req = factory.open(DB_NAME, 1);
        req.onupgradeneeded = () => {
            const db = req.result;
            db.createObjectStore('entries', { keyPath: 'id' }).createIndex('scope', 'scope');
            db.createObjectStore('meta', { keyPath: 'key' });
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}
export async function data(store, action, value, factory) {
    const db = await openDatabase(factory);
    try {
        return await new Promise((resolve, reject) => {
            const tx = db.transaction(store, ['get', 'getAll'].includes(action) ? 'readonly' : 'readwrite');
            const request = tx.objectStore(store)[action](value);
            tx.oncomplete = () => resolve(request.result);
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error ?? new Error('تعذر حفظ البيانات على الجهاز.'));
        });
    } finally { db.close(); }
}
export const metadata = async key => (await data('meta', 'get', key))?.value;
export const setMetadata = (key, value) => data('meta', 'put', { key, value });
export async function entries(scope, factory) {
    return (await data('entries', 'getAll', undefined, factory)).filter(e => e.scope === scope).sort((a, b) => (a.sequence ?? a.createdAt) - (b.sequence ?? b.createdAt));
}
export async function enqueue(entry, factory) {
    const db = await openDatabase(factory);
    try {
        await new Promise((resolve, reject) => {
            const tx = db.transaction('entries', 'readwrite');
            const store = tx.objectStore('entries');
            const all = store.getAll();
            let reason;
            all.onsuccess = () => {
                const rows = all.result.filter(row => row.id !== entry.id);
                const bytes = rows.reduce((sum, row) => sum + row.body.byteLength, entry.body.byteLength);
                if (rows.length >= MAX_ENTRIES || bytes > MAX_BYTES) {
                    reason = new Error('مساحة الانتظار ممتلئة. زامن العمليات أو احذف عملية غير مطلوبة أولاً.');
                    tx.abort();
                } else {
                    const existing = all.result.find(row => row.id === entry.id);
                    const sequence = existing?.sequence ?? (Math.max(0, ...all.result.map(row => row.sequence ?? 0)) + 1);
                    store.put({ ...entry, sequence });
                }
            };
            tx.oncomplete = resolve;
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(reason ?? tx.error);
        });
    } finally { db.close(); }
}
export async function clearScope(scope) {
    for (const entry of await entries(scope)) await data('entries', 'delete', entry.id);
}
export async function replayRequest(entry, csrf) {
    const headers = new Headers(entry.headers);
    for (const name of ['X-Inertia', 'X-Inertia-Version', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN', 'Content-Length']) headers.delete(name);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('X-CSRF-TOKEN', csrf);
    headers.set('X-Beacon-Operation-Key', entry.id);
    headers.set('X-Beacon-Offline-Scope', entry.scope);
    headers.set('X-Beacon-Offline-Replay', '1');
    headers.delete('X-Beacon-Offline-Versions');
    const versions = JSON.stringify(entry.versions ?? {});
    const type = headers.get('Content-Type') ?? '';
    let body = entry.body;
    if (type.includes('multipart/form-data')) {
        body = await new Response(body, { headers: { 'Content-Type': type } }).formData();
        body.delete('_token'); body.set('_beacon_versions', versions);
        headers.delete('Content-Type'); // Let the browser create a new multipart boundary.
    } else if (type.includes('application/x-www-form-urlencoded')) {
        body = new URLSearchParams(new TextDecoder().decode(body)); body.delete('_token'); body.set('_beacon_versions', versions);
    } else if (type.includes('application/json')) {
        const values = JSON.parse(new TextDecoder().decode(body)); delete values._token; values._beacon_versions = versions; body = JSON.stringify(values);
    }
    return { method: entry.method, headers, body, credentials: 'same-origin', redirect: 'follow', referrer: entry.source ?? '/' };
}
export function syncState(status) {
    if ([401, 419].includes(status)) return 'auth';
    if ([403, 404, 409, 422].includes(status)) return 'review';
    return 'pending';
}

const fieldLabels = { type: 'نوع العملية', amount: 'المبلغ', currency: 'العملة', category: 'التصنيف', payment_status: 'حالة الدفع', paid_amount: 'المبلغ المدفوع', transaction_date: 'تاريخ العملية', settlement_date: 'تاريخ الدفعة', counterparty: 'الجهة', description: 'الوصف', note: 'ملاحظة', notes: 'ملاحظات', title: 'العنوان', business_name: 'اسم المنشأة', location: 'الموقع', target_date: 'موعد المتابعة', status: 'الحالة', outcome: 'النتيجة', action_needed: 'الإجراء المطلوب', correction_reason: 'سبب التصحيح', items: 'البنود', quantity: 'الكمية', unit_price: 'سعر الوحدة', total: 'الإجمالي', file: 'المستند' };
export async function entryDetails(entry) {
    const type = entry.headers['content-type'] ?? '';
    const request = new Response(entry.body, { headers: { 'Content-Type': type } });
    const rows = [];
    const add = (name, value, label = '') => {
        if (['_token', '_method', 'client_draft_id'].includes(name)) return;
        label = label || fieldLabels[name] || `بيان ${rows.length + 1}`;
        if (value instanceof Blob) { rows.push({ name, label, file: true, value: value.name ?? 'مرفق', size: value.size }); return; }
        if (value && typeof value === 'object') {
            for (const [key, child] of Object.entries(value)) add(key, child, label + ' / ' + (fieldLabels[key] ?? (/^\d+$/.test(key) ? 'البند ' + (Number(key) + 1) : 'تفصيل')));
        } else {
            const choices = name === 'category' ? entry.formOptions?.categories?.[entry.transactionType] : entry.formOptions?.[name === 'type' ? 'types' : name === 'currency' ? 'currencies' : name === 'payment_status' ? 'payment_statuses' : ''];
            rows.push({ name, label, file: false, value: choices?.find(c => c.value === value)?.label ?? (value === true ? 'نعم' : value === false ? 'لا' : value === null || value === '' ? 'غير محدد' : String(value)) });
        }
    };
    if (type.includes('json')) { const values = await request.json(); entry.transactionType = values.type; for (const [name, value] of Object.entries(values)) add(name, value); }
    else { const values = await request.formData(); entry.transactionType = values.get('type'); for (const [name, value] of values) add(name, value, fieldLabels[name.replace(/\[.*$/, '')]); }
    return rows;
}
