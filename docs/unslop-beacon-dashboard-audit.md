# Unslop report — Beacon platform shell

**Skill:** [unslop](https://github.com/heydamir/unslop) by Damir Sokolovsky (CC BY 4.0)  
**Mode:** Full unslop (nine passes)  
**Scope:** Shared design system (`resources/css/app.css`), shell layouts, `StatCard`, `SectionCard`, `PageHeader`, `EmptyState`, programme dashboard

---

## Direction

- **Product.** Arabic-first SME programme desk for Beacon advisors, admins, and enterprises — profiles, books, compliance, readiness, referrals.
- **Character.** Calm, exact, trustworthy.
- **World.** Quiet programme operations desk — ink neutrals, paper-white surfaces, Beacon green only as accent.
- **Moments.** KPI figures, status badges, and the green section accent bar.
- **Signature.** 4px green accent bar on section headers.

---

## Audit (signs → fix)

| Sign | Where | Pass |
|------|--------|------|
| Green canvas wash `#e5efea` | `--app-background` | Color |
| Sidebar hue gradient into green | `--app-sidebar-gradient` | Color |
| Soft multi-layer / glow shadows | cards, menus, FAB | Color / Details |
| Auth decorative concentric rings | `.app-auth-story::after` | Visuals |
| Rainbow stat icon tiles | `.app-stat--*` + icons | Icons / Details |
| Decorative empty-state tilt | `.app-empty__icon` rotate | Icons |
| Soft primary tiles on module/quick links | `.app-module-card`, `.app-quick-link__icon` | Icons |
| Off-grid spacing (22, 26, 19, 13, 3, 7…) | shell, sidebar, topbar, cards | Spacing |
| Sidebar 266 / 234 px | `.app-sidebar` | Spacing / Layout |
| Weight 700–800 on chrome | headers, nav active, SME hero | Type |
| Tracked uppercase `BEACON` Latin | `.app-brand-name [lang=en]` | Type / Copy |
| Generic eyebrow «مساحة العمل» | `PageHeader` | Copy |
| Mint overview banner fill | `.app-overview-banner` | Color |
| Active nav mint fill + shadow | `.app-side-link.is-active` | Color |
| Infinite skeleton shimmer | `.sme-skel` | Motion |
| Pill FAB with green glow | `.sme-fab__btn` | Details / Color |
| Nested radii 10/16/22 + many one-offs | tokens + components | Details |

---

## Passes applied

### 1. Spacing
Snapped page, header, sidebar, topbar, nav, section, and stat padding/gaps to the 4px grid. Sidebar width **260 / 240**. Nav row height denser (**36**). Content padding **24 / 32 / 48**.

### 2. Color
Canvas → neutral `#f3f5f5`. Surfaces stay white / cool grey. Accent remains Beacon `#176b60`. Sidebar uses a **mono** tone shift only. Active nav is a quiet surface step (no mint glow). Overview banner is white + hairline. Shadows reduced to small neutral hairlines.

### 3. Icons
Removed decorative icons from `StatCard`. Empty / module / quick-link icons use muted ink, one size. Status badges keep semantic icons (meaning).

### 4. Copy
Removed filler page eyebrow «مساحة العمل». Latin brand mark no longer tracked-uppercase shout.

### 5. Type
UI weights capped at **600** (faces still ship 700 for optional emphasis). Hierarchy via size and muted color. Tabular nums kept on figures.

### 6. Layout
KPI columns capped (`col-xl-3`). Programme overview stacks with `gap-3`. Desktop shell proportions kept (sidebar + fluid content ≤1440).

### 7. Details
Radius tokens **8 / 12 / 16** with nested consistency. Stat cards = label + number (+ optional amber surface for overdue). FAB is a quiet rounded rect, not a glowing pill.

### 8. Visuals
Removed auth story ring decoration. No generated filler art (dashboard/app). Signature green bar retained on sections.

### 9. Motion
Removed infinite skeleton shimmer (static ink placeholder). Transitions stay named color/border/opacity only.

---

## Checklist

- [x] Direction written; passes follow it
- [x] Shared chrome on 4px scale
- [x] One accent; neutral surfaces
- [x] No hue-gradient chrome, no auth rings, no glow FAB
- [x] No emoji; Bootstrap Icons only
- [x] Weights 400–600 in UI rules
- [x] No greeting / filler eyebrow
- [x] Nested radii via tokens
- [x] No looping skeleton motion
- [x] Template check: green accent bar + Arabic programme language remain Beacon-specific

---

## Files touched

- `resources/css/app.css`
- `resources/js/Components/Common/StatCard.tsx`
- `resources/js/Components/Common/PageHeader.tsx`
- `resources/js/Pages/Dashboard/Beacon.tsx`
- `docs/ui-design-system.md`
- `docs/unslop-beacon-dashboard-audit.md`
