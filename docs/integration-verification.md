# Branch integration and interface verification

Date: 25 October 2026 (Europe/Istanbul).

## Integrated branches

- `feature/color-refresh` (`a1a9a56`): retained the shared colour palette and background refresh.
- `fix/draft-empty-fields` (`4d0038b`): integrated its history. Current main already contains the required empty invoice date/currency validation, placeholders and suggestion badges. Conflict resolution retains newer main features: browser speech, voice date defaults with a visible hint, invoice prefixes and line items.
- `fix/error-pages-503-debug` (`2b460b9`): retained the debug-only 503 handling and error-page regressions.

No branches were deleted and no history was rewritten.

## Changes

- Audit details display labeled rows, including readable before/after values, boolean values and roles. CSV audit context also uses readable lines. Secret filtering remains on the server and immutable stored evidence is unchanged.
- Rule files use ordinary upload/download controls; nobody has to type structured data. Invalid, unreadable and oversized imports receive a plain-language message.
- Rule mutations reject all failing HTTP statuses and redirects to login. Internal server exception messages are not displayed for server failures.
- AI and voice requests reject HTML, empty responses and unusable response bodies instead of opening a broken review or reporting success.
- Notification counts reconcile optimistic updates with refreshed server data without subtracting the same notification twice.
- Payment entry accepts Arabic/Persian digits and correctly grouped amounts using the existing transaction normalizer. Ambiguous commas remain validation errors. Default payment dates use the browser's local day.
- Production page discovery excludes test files, keeping test runners and mocks out of shipped assets.
- Composer now declares BCMath, already used by financial calculations. Provision `ext-bcmath` before deployment; Composer fails early when it is missing.
- Vitest was upgraded to 5.0.5 to address reported dependency advisories. Use Node 24 for the development checks (minimum supported Node 22.12).
- GitHub Actions checks PHP tests, frontend tests, TypeScript, production build and absence of test modules in the production manifest.

## Validation

- PHP: **605 passed, 1 skipped**, 4,757 assertions; includes role authorization, tenant isolation, financial workflows, uploads, administration and error pages.
- Frontend: **252 passed** across 32 files after upgrading Vitest.
- TypeScript: `npx tsc --noEmit`.
- Production build: `npm run build`; no `.test.` modules in the manifest.
- Composer manifest/lock validation, changed PHP file formatting, Git whitespace checks, route cache and Blade compilation.
- Dependency audits: no reported advisories from Composer; npm reports zero vulnerabilities after the upgrade.

## Verification limits

The real Arabic table-invoice OCR integration test requires Tesseract with Arabic and English language data and was skipped here. The local browser smoke test could not run because Chromium's download returned an invalid archive. Component tests and Laravel HTTP tests ran, but real browser/device interactions, production infrastructure, performance and third-party services still require acceptance testing. Passing these checks is not a guarantee that no undiscovered bugs exist.
