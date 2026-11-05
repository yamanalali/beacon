import fs from "fs";

const path = "resources/css/app.css";
let css = fs.readFileSync(path, "utf8");
const start = css.indexOf("/* SME home — hierarchy:");
const end = css.indexOf("@media (min-width: 768px) and (max-width: 991.98px) {");
if (start < 0 || end < 0) {
    console.error("markers", start, end);
    process.exit(1);
}

// Find end of that media block
const afterMedia = css.indexOf("}", end);
const mediaEnd = css.indexOf("}", afterMedia + 1); // closing of media query — fragile
// Better: find the full media block
const mediaStart = end;
const mediaClose = css.indexOf("\n}", mediaStart);
// that finds first } inside - need the block's closing
let depth = 0;
let i = mediaStart;
let mediaBlockEnd = -1;
for (; i < css.length; i++) {
    if (css[i] === "{") depth++;
    else if (css[i] === "}") {
        depth--;
        if (depth === 0) {
            mediaBlockEnd = i + 1;
            break;
        }
    }
}
if (mediaBlockEnd < 0) {
    console.error("media end not found");
    process.exit(1);
}

const insert = `/* SME home — hierarchy: net → دخل/مصروف → سجّل → حركات → حالة. Width auto, desktop up to ~1040px. */
.sme-home {
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: auto;
    max-width: 1040px;
    margin-inline: auto;
    padding-inline: 16px;
    overflow-x: clip;
    padding-block-end: 88px;
}
.sme-demo-banner {
    margin: 0;
    padding: 8px 12px;
    border-radius: 12px;
    border: 1px solid var(--app-border);
    background: color-mix(in srgb, var(--app-primary-soft) 70%, var(--app-surface));
    color: var(--app-text-muted);
    font-size: 13px;
    text-align: center;
}
.sme-home__grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.sme-hero {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
    border-radius: 18px;
    border: 1px solid var(--app-border);
    background: var(--app-surface);
}
.sme-hero__eyebrow {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--app-text-muted);
}
.sme-currency-toggle {
    display: inline-flex;
    align-self: center;
    gap: 4px;
    padding: 4px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--app-primary) 6%, var(--app-surface));
}
.sme-currency-toggle button {
    min-width: 48px;
    min-height: 40px;
    padding: 6px 14px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--app-text-muted);
    font-size: 15px;
    font-weight: 700;
}
.sme-currency-toggle button.is-active {
    background: var(--app-surface);
    color: var(--app-primary);
    box-shadow: 0 1px 2px color-mix(in srgb, var(--app-text) 8%, transparent);
}
.sme-hero__label {
    margin: 0;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    color: var(--app-text-muted);
}
.sme-hero__net {
    margin: 0;
    text-align: center;
    font-size: clamp(2.1rem, 8vw, 2.5rem);
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.sme-hero.is-up .sme-hero__net { color: var(--app-success); }
.sme-hero.is-down .sme-hero__net { color: var(--app-danger); }
.sme-hero__meta {
    margin: 0;
    text-align: center;
    font-size: 14px;
    color: var(--app-text-muted);
}
.sme-hero__split {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-block-start: 4px;
}
.sme-hero__stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px;
    border-radius: 14px;
    border: 1px solid var(--app-border);
    background: color-mix(in srgb, var(--app-surface) 92%, white);
    text-align: start;
}
.sme-hero__stat span {
    font-size: 14px;
    color: var(--app-text-muted);
    font-weight: 600;
}
.sme-hero__stat strong {
    font-size: clamp(1.2rem, 4.2vw, 1.4rem);
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
    overflow-wrap: anywhere;
}
.sme-hero__stat small { font-size: 12px; color: var(--app-text-muted); }
.sme-hero__stat.is-in strong { color: var(--app-success); }
.sme-hero__stat.is-out strong { color: var(--app-warning); }

.sme-primary {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.sme-primary__hint {
    margin: 0;
    font-size: 14px;
    color: var(--app-text-muted);
    font-weight: 600;
}
.sme-primary--panel {
    /* mobile: plain strip under hero */
}
.sme-primary__btn,
.sme-primary .btn {
    min-height: 56px;
    border-radius: 16px;
    font-size: 18px;
    font-weight: 700;
}

.sme-fab {
    position: fixed;
    z-index: 1040;
    inset-inline: 0;
    bottom: calc(var(--app-bottom-nav-space) + 8px);
    display: flex;
    justify-content: center;
    pointer-events: none;
}
.sme-fab__btn {
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 56px;
    padding: 0 22px;
    border: 0;
    border-radius: 999px;
    background: var(--app-primary);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    box-shadow: 0 8px 24px color-mix(in srgb, var(--app-primary) 35%, transparent);
}
.sme-fab__btn .bi { font-size: 1.25rem; }
@media (min-width: 992px) {
    .sme-fab { bottom: 28px; }
}

.sme-skel {
    border-radius: 16px;
    background: linear-gradient(90deg, var(--app-border) 0%, color-mix(in srgb, var(--app-surface) 70%, white) 50%, var(--app-border) 100%);
    background-size: 200% 100%;
    animation: sme-skel 1.2s ease-in-out infinite;
}
.sme-skel--hero { height: 220px; }
.sme-skel--btn { height: 56px; }
.sme-skel--list { height: 220px; }
.sme-skel--chips { height: 64px; }
@keyframes sme-skel {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}
.sme-home--loading {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: auto;
    max-width: 1040px;
    margin-inline: auto;
    padding-inline: 16px;
}

.sme-block { display: flex; flex-direction: column; gap: 8px; }
.sme-block__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-inline: 2px;
}
.sme-block__head h2 { margin: 0; font-size: 16px; font-weight: 700; }
.sme-block__link {
    font-size: 14px;
    font-weight: 600;
    color: var(--app-primary);
    text-decoration: none;
    min-height: 48px;
    display: inline-flex;
    align-items: center;
}
.sme-alerts { display: flex; flex-direction: column; gap: 8px; }
.sme-alert {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 48px;
    padding: 10px 12px;
    border: 1px solid var(--app-border);
    border-radius: 14px;
    background: var(--app-surface);
    color: inherit;
    text-decoration: none;
}
a.sme-alert:hover { border-color: var(--app-sage-300); color: inherit; }
.sme-alert--danger { border-inline-start: 3px solid var(--app-danger); background: color-mix(in srgb, var(--app-danger-soft) 55%, var(--app-surface)); }
.sme-alert--warning { border-inline-start: 3px solid var(--app-warning); }
.sme-alert--info { border-inline-start: 3px solid var(--app-blue); }
.sme-alert__icon { color: var(--app-text-muted); font-size: 1rem; }
.sme-alert__title { flex: 1; font-weight: 600; font-size: 14px; }

.sme-tx-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--app-border);
    border-radius: 16px;
    background: var(--app-surface);
    overflow: hidden;
}
.sme-tx {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 64px;
    padding: 12px 14px;
    color: inherit;
    text-decoration: none;
    border-bottom: 1px solid var(--app-border);
}
.sme-tx-list li:last-child .sme-tx { border-bottom: 0; }
a.sme-tx:active { background: var(--app-surface-alt); }
.sme-tx__icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    flex-shrink: 0;
    background: var(--app-ink-50);
    color: var(--app-text-muted);
    font-size: 1rem;
}
.sme-tx__icon.is-in { background: var(--app-primary-soft); color: var(--app-success); }
.sme-tx__icon.is-out { background: var(--app-amber-soft); color: var(--app-warning); }
.sme-tx__copy { flex: 1; min-width: 0; }
.sme-tx__title {
    display: block;
    font-weight: 700;
    font-size: 16px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sme-tx__meta {
    display: block;
    font-size: 14px;
    color: var(--app-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sme-tx__amount {
    font-weight: 700;
    font-size: 16px;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.sme-tx__amount.is-in { color: var(--app-success); }
.sme-tx__amount.is-out { color: var(--app-warning); }

.sme-feed__empty {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    border: 1px solid var(--app-border);
    border-radius: 16px;
    background: var(--app-surface);
}

.sme-status {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}
.sme-status__chip {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    min-width: 0;
    min-height: 48px;
    padding: 10px 8px;
    border: 1px solid var(--app-border);
    border-radius: 14px;
    background: var(--app-surface);
    color: inherit;
    text-decoration: none;
}
.sme-status__chip-label {
    font-size: 12px;
    color: var(--app-text-muted);
    font-weight: 600;
}
.sme-status__chip strong {
    font-size: 14px;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

@media (min-width: 992px) {
    .sme-home {
        gap: 24px;
        padding-inline: 0;
        padding-block-end: 24px;
        width: auto;
        max-width: 1040px;
    }
    .sme-home__grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 20px;
        align-items: start;
    }
    .sme-hero {
        grid-column: 1 / span 8;
        padding: 20px;
    }
    .sme-primary--panel {
        grid-column: 9 / span 4;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 12px;
        min-height: 100%;
        padding: 20px 16px;
        border: 1px solid var(--app-border);
        border-radius: 18px;
        background: var(--app-surface);
    }
    .sme-primary__btn,
    .sme-primary .btn {
        min-height: 64px;
    }
    .sme-home__alerts {
        grid-column: 1 / -1;
    }
    .sme-feed {
        grid-column: 1 / span 8;
    }
    .sme-home__side {
        grid-column: 9 / span 4;
        position: sticky;
        top: 96px;
    }
    .sme-status {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .sme-status__chip {
        min-height: 56px;
        padding: 12px 14px;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .sme-hero__net { font-size: 2.5rem; }
    .sme-hero__stat strong { font-size: 1.35rem; }
    .sme-tx { min-height: 68px; padding: 14px 16px; }
}

@media (min-width: 768px) and (max-width: 991.98px) {
    .sme-status { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .sme-tx { min-height: 64px; padding: 12px 14px; }
}
`;

css = css.slice(0, start) + insert + css.slice(mediaBlockEnd);
fs.writeFileSync(path, css);
console.log("ok", start, mediaBlockEnd);
