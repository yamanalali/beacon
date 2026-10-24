# Offline operation

## Deploy

Use HTTPS (localhost is supported for development), run `composer install`, `php artisan migrate --force`, `npm ci`, and `npm run build`, then deploy the public files as well as the PHP changes. `/sw.js`, `/offline-core.js`, `/offline-reports.js` and `/offline.html` must be served from the application origin without rewriting them to the login page. Serve `/sw.js` without a long immutable cache lifetime. Its scope is the application root. Vite development/HMR mode does not install the worker; use a production build when testing offline behavior.

## Use

1. Sign in while connected on a trusted device. The app installs its assets and prepares the current workspace automatically. Open **الصفحات والطلبات المحفوظة**, or `/offline`, to see actual readiness and the saved-page inventory.
2. Before leaving a connection, choose **تجهيز الصفحات وتحديثها**. Preparation discovers accessible enterprise sections, forms and record pages from the current workspace, with a bounded crawl. Open any additional pages, date ranges or documents that you need. This does not download the entire database; the saved-page inventory shows what is available.
3. Offline forms keep the normal interface. Supported saves become explicitly pending operations, including binary attachments. Nothing is reported as accepted or posted until the server acknowledges it.
4. With the application open, reconnecting automatically starts synchronization. Focus, a periodic retry and **المزامنة الآن** also trigger it. Browsers supporting Background Sync may also synchronize while the page is closed; other browsers require the app to be opened. An expired session pauses synchronization until the same account signs in again.
5. Validation, deleted records, lost permissions and changed records remain in the outbox with readable explanations. A conflict stops later operations to preserve order. View the preserved values and download attachments before discarding an invalid/conflicting request and entering it again from the current server form. Do not manually submit a second copy of a request still waiting in the outbox.

## Supported writes

- Create or edit an enterprise.
- Create financial transactions, correct transactions, record settlements.
- Upload documents (including a receipt-upload request), edit document metadata, archive documents.
- Add advisory activities and follow-ups; complete follow-ups.
- Update enterprise compliance records.
- Start readiness assessments and save answers to existing assessments.
- Create referral drafts; update status and record outcomes.

The server's existing policies and validation apply again during synchronization. Actions referencing records created offline cannot use their new server IDs until those records are synchronized.

## Reading and reports

The app shell and production page chunks are precached. Authorized, full page responses have account-scoped HTML and Inertia snapshots, so saved pages can reopen on a hard refresh. Partial Inertia responses, authentication pages, account/security pages, invitations and operations tooling are not cached as workspace pages. Previously opened document previews/downloads are cached within the device budget.

Financial report preparation saves a separately authorized snapshot of posted transactions and settlements for each prepared enterprise. All seven existing report types can be calculated locally with new dates/bases using integer minor units, with currencies kept separate. Pending approvals, superseded transactions and unsynchronized outbox changes are excluded. The snapshot date is visible. Local CSV and browser printing/PDF work without the server; native XLSX export still requires a connection. A snapshot exceeding 20,000 transactions is marked incomplete and is never used for local totals. Later server changes cannot be known until refreshed.

## Online requirements

A first login, account/password/MFA and access changes, system backups, server AI/OCR/transcription, final approvals/publication, consent and external sharing need a live connection. A queued receipt can be uploaded/processed when it reaches the server. The offline layer does not impersonate those services or bypass approvals.

## Durability and isolation

Outbox requests, including file bytes, live in IndexedDB. Saving is atomic, capped at 100 operations / 50 MiB across accounts. Private caches are account-scoped, expire after seven days, and are bounded to 240 entries / approximately 100 MiB; individual cached responses are limited to 20 MiB. The shell remains available when older cached pages are evicted. Browser eviction, clearing site storage, private browsing and losing the device can remove local data; this is not a server backup. Use a trusted browser profile and synchronize regularly.

Logout removes private caches, pending operations and the existing manual drafts. An offline logout also records a required server logout, completed on reconnect before another protected navigation. Account switches remove the prior account's outbox/cache and stale tabs cannot read or submit against a different active offline account. An expired session preserves the outbox for reauthentication rather than silently discarding it. Local content is protected by the browser profile, not a separate encrypted offline vault or offline PIN.

Every supported online request receives its operation key before transmission. The same key survives a lost response and is used on replay. Server receipts are unique per user and committed in the same database transaction as the business writes. A key cannot be reused with different input. Receipts are retained to prevent late duplicates. Record versions are checked on replay under row locks; acknowledged versions are carried forward to subsequent queued edits without masking unrelated server changes. Requests use a fresh session CSRF token, not a persisted expired token. No outbox payload is deleted until the server acknowledges the matching operation key.

## Verification

- `php artisan test`: 613 passed, one existing real Arabic Tesseract integration skip.
- `npm test`: 273 passed, including IndexedDB capacity/isolation, binary round trips, CSRF replacement, route policy and exact local financial reports.
- `npx tsc --noEmit` and `npm run build`.
- `npm run test:offline`: real headless browser acceptance using a disposable SQLite database and demo users. Set `BEACON_BROWSER_EXECUTABLE` to a Chromium/Chrome binary; `BEACON_PHP` optionally selects PHP. The workflow uses the Chrome installed on the GitHub Ubuntu runner. Checks cover offline hard reload, persistent transaction queue, binary attachment recovery, report filters/CSV, automatic reconnect sync, exactly-once server records, offline sign-out and session termination on reconnect.
