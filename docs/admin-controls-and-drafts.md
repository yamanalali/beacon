# Access controls, currencies, offline drafts, and criteria

## Deployment

Run `php artisan migrate --force` and build the frontend with `npm ci && npm run build` using the existing deployment process. The migration imports the configured currencies and preserves existing role defaults. No existing transaction amounts, currency codes, or scoring results are rewritten.

## User administration

- Open **Administration → User invitations** to generate an activation link. Select a role and the enterprise or MFI institution where applicable. SME invitations require an enterprise; advisors may be assigned one.
- Links expire after seven days, work once, and can be revoked. Generating another invitation for the same email invalidates the previous link. Only the token hash is stored. The issuing account must remain an active Beacon administrator.
- Share the displayed link through your approved channel. Automatic invitation email delivery is not configured by this feature.
- Open **Users → Permissions** to control enterprise viewing, recording financial transactions, approving others' pending transactions, and export/download endpoints. Existing enterprise assignments and role restrictions still apply. MFI and technical administrator roles do not gain general enterprise access through an override.
- Permissions can be restored to role defaults. Administrators cannot edit their own permission profile here. A permission update ends the target user's database-backed sessions and is audited.
- **New transactions require approval** makes the user's new records pending. An authorized second user approves from the transaction page. Pending records and their payments do not contribute to reports or posted dashboard totals. These users cannot directly correct or settle posted transactions; an authorized supervisor handles those changes. Existing records are not retroactively put into review.
- Export controls cover financial report exports, audit exports, scoring-rule exports, and explicit document downloads. Viewing information still permits browser capabilities such as printing a page or saving a preview.

## Currencies

Open **Administration → Currencies** to add a three-letter code, change its name, enable or disable it, or choose the default. Choose another default before disabling the current one. Disabling affects new transactions and draft suggestions; existing records keep their currency and remain available for reports, corrections in the same currency, and subsequent payments. No currency conversion is introduced.

## Device drafts

The manual **Add transaction** form can save an unfinished entry, including invoice items, on the current browser/device. Open the form while connected; use **Save draft on device** or submit while offline. Restore a saved draft, review it, and explicitly submit when connected. There is no background submission or offline navigation/cold start support.

Drafts are scoped to the signed-in account and enterprise, capped at 20 per account/enterprise, and cleared when using the app's logout button. They are not synchronized between devices. Use a trusted device; private browsing, cleared browser data, or storage restrictions can remove or prevent local drafts.

Each draft has a stable submission UUID. Retrying the same payload returns the existing transaction without duplicating it or its payment. A reused UUID with different content is rejected: check the transaction list after an uncertain network response before starting another entry. Permissions, active currencies, and input validation are rechecked by the server on submission.

## Scoring criteria

The existing versioned scoring workflow remains in place. The editor adds a three-step guide, descriptions for assessors, a default relative weight of 1 for new questions, generated internal codes, and advanced settings for codes and missing-data treatment. Existing criterion weights and rules are preserved.

Each question shows its percentage within its own pillar. Weights remain relative: a weight of 2 has twice the influence of 1; totals do not need to equal 100. The question library loads the latest 200 saved questions. Copying creates a new question with a fresh code and no pillar/compliance link; review and select its target pillar before saving. Original and published questions remain unchanged. Use the existing calculation preview and validation before publishing a rules version.

## Faster manual entry

The manual transaction form shows the essential fields first: transaction type, amount/currency, date/category, and payment status. Invoice items, counterparty, and notes sit under **Add details (optional)**. Existing copied/corrected details and validation errors open this section automatically. Partial payment asks for the current payment only; a live exact-decimal summary shows the total, paid, and remaining balance. New entries requiring approval use **Submit for approval**.

Counterparty suggestions come from up to 100 distinct names on posted transactions within the same enterprise. Users can still type a new name. **Record a similar transaction** on a posted transaction opens a new form with its invoice details and today's browser-local date; it does not create any record until submission, copy payments or documents, or bypass permissions. Payment starts as unpaid for review. Disabled source currencies require an explicit available currency selection and amount review; no conversion is performed.

Main amounts, partial payments, and invoice numeric fields accept Arabic/Persian digits and valid thousands grouping, such as `١٬٢٣٤٫٥٠` or `1,234.50`. Ambiguous commas such as `1,5` are rejected rather than interpreted as either 1.5 or 15. Existing server limits and validation still apply.
