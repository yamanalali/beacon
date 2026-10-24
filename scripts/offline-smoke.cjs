/* Real-browser acceptance test. Always uses a disposable SQLite database and demo users. */
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn, spawnSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const { randomUUID } = require('node:crypto');
const root = path.resolve(__dirname, '..');
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'beacon-offline-test-'));
const php = process.env.BEACON_PHP || 'php';
const password = randomUUID();
const db = path.join(temporary, 'browser.sqlite');
fs.writeFileSync(db, '');
const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: db, DEMO_PASSWORD: password, SESSION_DRIVER: 'database', CACHE_STORE: 'array' };
function artisan(...args) {
    const result = spawnSync(php, ['artisan', ...args], { cwd: root, env, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stdout + result.stderr);
}
const assert = (condition, message) => { if (!condition) throw new Error(message); };
(async () => {
    artisan('migrate', '--force'); artisan('db:seed', '--class=DemoSeeder', '--force');
    const port = process.env.BEACON_TEST_PORT || '8123';
    const origin = 'http://127.0.0.1:' + port;
    const server = spawn(php, ['-S', '127.0.0.1:' + port, '-t', path.join(root, 'public'), path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(root, 'public'), env, stdio: ['ignore', 'ignore', 'pipe'] });
    let browser;
    const fatal = [];
    server.stderr.on('data', chunk => { if (String(chunk).includes('Fatal')) fatal.push(String(chunk)); });
    try {
        browser = await chromium.launch({ executablePath: process.env.BEACON_BROWSER_EXECUTABLE, args: process.env.BEACON_BROWSER_ARGS ? JSON.parse(process.env.BEACON_BROWSER_ARGS) : ['--no-sandbox', '--disable-dev-shm-usage'], headless: true });
        const context = await browser.newContext({ baseURL: origin, timezoneId: 'UTC', viewport: { width: 1280, height: 900 } });
        const page = await context.newPage(), errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/login');
        await page.locator('#email').fill('sme@beacon.test'); await page.locator('#password').fill(password); await page.locator('button[type=submit]').click();
        await page.waitForURL(url => url.pathname === '/dashboard/sme');
        await page.waitForFunction(() => !!navigator.serviceWorker.controller, { timeout: 60000 });
        const command = (type, payload = {}) => page.evaluate(({ type, payload }) => new Promise((resolve, reject) => {
            const channel = new MessageChannel(), timer = setTimeout(() => reject(new Error('Worker timeout')), 120000);
            channel.port1.onmessage = event => { clearTimeout(timer); channel.port1.close(); resolve(event.data); };
            navigator.serviceWorker.controller.postMessage({ type, ...payload }, [channel.port2]);
        }), { type, payload });
        let state;
        for (let attempt = 0; attempt < 100; attempt++) {
            state = await command('STATUS'); if (state.active && state.pages.length > 10) break;
            await page.waitForTimeout(300);
        }
        assert(state.active, 'Offline preparation was not activated');
        const saved = state.pages.find(p => p.url.match(/\/enterprises\/[A-Za-z0-9-]+/));
        assert(saved, 'No enterprise was prepared');
        const enterprise = saved.url.match(/\/enterprises\/[A-Za-z0-9-]+/)[0];
        const manual = enterprise + '/transactions/create';
        await command('PREPARE', { urls: [manual] });
        await page.goto(manual); await page.locator('#amount').waitFor();
        await context.setOffline(true); await page.reload(); await page.locator('#amount').waitFor();
        console.log('PASS offline application hard reload');
        await page.locator('#amount').fill('123.45'); await page.locator('#category').selectOption({ index: 1 });
        await page.locator('details summary').filter({ hasText: 'إضافة تفاصيل' }).click(); await page.locator('#counterparty').fill('Offline browser acceptance');
        await page.locator('button[type=submit]').click(); await page.waitForURL(url => url.pathname === '/offline');
        state = await command('STATUS'); assert(state.queue.length === 1, 'Transaction was not queued exactly once');
        const transactionKey = state.queue[0].id;
        await page.reload(); state = await command('STATUS'); assert(state.queue.length === 1, 'Outbox did not survive hard reload');
        console.log('PASS offline transaction and persistent outbox');
        await page.goto(enterprise + '/documents'); await page.locator('#document_file').waitFor();
        const pdf = '%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF';
        await page.locator('#document_file').setInputFiles({ name: 'offline-acceptance.pdf', mimeType: 'application/pdf', buffer: Buffer.from(pdf) });
        await page.locator('#document_title').fill('Offline attachment acceptance');
        await page.locator('form').filter({ has: page.locator('#document_file') }).locator('button[type=submit]').click(); await page.waitForURL(url => url.pathname === '/offline');
        state = await command('STATUS'); assert(state.queue.length === 2, 'Upload was not queued');
        await page.getByRole('button', { name: 'عرض البيانات والمرفقات' }).nth(1).click();
        const pendingDownload = page.waitForEvent('download'); await page.getByRole('button', { name: 'تنزيل المرفق', exact: true }).click();
        const file = await pendingDownload; assert(fs.readFileSync(await file.path(), 'utf8') === pdf, 'Attachment bytes were not preserved');
        console.log('PASS offline binary attachment storage and recovery');
        await page.goto(enterprise + '/reports'); await page.locator('#from').waitFor();
        await page.locator('#from').fill('2026-01-01'); await page.locator('button[type=submit]').click();
        await page.waitForURL(url => url.searchParams.get('from') === '2026-01-01');
        const csvDownload = page.waitForEvent('download'); await page.getByRole('button', { name: 'تنزيل CSV من النسخة المحفوظة' }).click();
        const csv = fs.readFileSync(await (await csvDownload).path(), 'utf8'); assert(csv.startsWith('\uFEFF') && csv.includes('\r\n'), 'Offline CSV is malformed');
        console.log('PASS offline report filters and local CSV export');
        await context.setOffline(false);
        for (let attempt = 0; attempt < 120; attempt++) { state = await command('STATUS'); if (!state.queue.length) break; await page.waitForTimeout(300); }
        await page.goto('/offline'); state = await command('STATUS');
        assert(state.queue.length === 0 && !state.error, 'Outbox failed to synchronize: ' + JSON.stringify(state.queue));
        console.log('PASS reconnect and automatic synchronization');
        const verification = spawnSync(php, ['artisan', 'tinker', '--execute', `echo json_encode([\\App\\Models\\FinancialTransaction::where('counterparty','Offline browser acceptance')->count(), \\Illuminate\\Support\\Facades\\DB::table('offline_operations')->where('operation_key','${transactionKey}')->count(), \\App\\Models\\Document::where('title','Offline attachment acceptance')->count()]);`], { cwd: root, env, encoding: 'utf8' });
        assert(verification.status === 0 && verification.stdout.includes('[1,1,1]'), 'Expected exactly one transaction, receipt and upload: ' + verification.stdout + verification.stderr);
        await command('SYNC'); // No duplicate writes on another synchronization.
        console.log('PASS server acknowledgement and exactly-once business records');
        await context.setOffline(true); await page.goto('/offline');
        await page.locator('.app-user-menu summary:visible').first().click(); await page.getByRole('button', { name: 'تسجيل الخروج', exact: true }).first().click();
        await page.waitForURL(url => url.pathname === '/offline.html');
        state = await command('STATUS'); assert(!state.active && state.queue.length === 0, 'Sign-out did not clear private data');
        await context.setOffline(false); await page.goto('/dashboard/sme'); await page.waitForURL(url => url.pathname === '/login');
        console.log('PASS offline sign-out clears data and terminates the session on reconnect');
        assert(!errors.length && !fatal.length, 'Runtime errors: ' + [...errors, ...fatal].join('\n'));
        console.log('PASS no browser or PHP runtime errors');
    } finally {
        if (browser) await browser.close(); server.kill('SIGTERM'); fs.rmSync(temporary, { recursive: true, force: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
