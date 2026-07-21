# BrightBlaze Garage — UI/UX Design System

Stage 3 refinement of the BrightBlaze Garage Management System interface.

## Design Goals

- **Operational clarity**: the UI communicates status, priority, and hierarchy at a glance.
- **Consistency**: shared tokens, classes, and patterns across every page.
- **Accessibility**: keyboard-navigable, screen-reader-friendly, colour is never the sole communicator.
- **Responsive**: usable from 375 px mobile to 1440 px desktop.
- **Print-friendly**: job cards and reports produce clean A4 output.

## Brand Palette

| Token | Hex | Usage |
|---|---|---|
| `--color-primary` | `#c62828` | Primary actions (btn-bb), destructive emphasis |
| `--color-secondary` | `#e8590c` | Secondary emphasis (btn-bb-orange) |
| `--color-accent` | `#f77f00` | Decorative brand accent (icons, sidebar highlights) |
| `--color-dark` | `#262a31` | Sidebar background, shell |
| `--color-bg` | `#f4f5f7` | Page background |
| `--color-surface` | `#ffffff` | Cards, modals |
| `--color-text` | `#1f2329` | Body text |
| `--color-text-muted` | `#5b616e` | Secondary text |
| `--color-border` | `#e2e5ea` | Default borders |

Semantic colours: success `#1a7f45`, warning `#b26a00`, danger `#c62828`, info `#1461b8`.

Legacy aliases (`--bb-red`, `--bb-orange`, `--bb-dark`) are retained for backward compatibility and map to the new tokens.

## Typography

- **Font stack**: system fonts (`-apple-system`, `Segoe UI`, `Roboto`, etc.)
- **Scale**: `--text-xs` (0.75 rem) through `--text-xl` (1.5 rem)
- **Page title**: `.bb-page-title` — 1.25 rem, weight 700
- **Monospace**: `.bb-mono` for plate numbers, job numbers, codes
- **Pre-wrapped text**: `.bb-prewrap` (white-space: pre-line) for descriptions and notes

## Spacing

Base unit: 0.25 rem. Scale: `--space-1` through `--space-8`.

Content max-width: `--content-max-width: 1280px`.
Form max-width: `--form-max-width: 760px` (applied via `.bb-form-narrow`).

## Radii & Shadows

- `--radius-sm`: 4 px (badges, small controls)
- `--radius-md`: 6 px (buttons, inputs, cards)
- `--radius-lg`: 8 px (cards, login)
- `--shadow-sm`: subtle card elevation
- `--shadow-md`: login card, sidebar mobile

## Button Hierarchy

| Class | Purpose |
|---|---|
| `.btn-bb` | Single primary action per context (red) |
| `.btn-bb-orange` | Secondary emphasis (orange) — used sparingly |
| `.btn-outline-secondary` | Tertiary / navigation actions |
| `.btn-outline-primary` | Edit actions in tables |
| `.btn-outline-danger` | Destructive actions in tables |

One `.btn-bb` per page/panel. Workflow actions are visually separated from save actions.

## Forms

- Labels: `.form-label` with 600 weight
- Required markers: `<span class="bb-required" aria-hidden="true">*</span>`
- Form width: `.bb-form-narrow` (max 760 px)
- Focus: blue ring (`--color-focus`) with offset
- Disabled/readonly: muted background

## Tables

- Header: uppercase, small, muted text on surface-muted background
- Numeric columns: `.bb-num` (right-aligned, tabular-nums)
- Action cells: `.bb-actions` (right-aligned, nowrap, inline forms)
- Hover: surface-muted highlight
- Responsive: always wrapped in `.table-responsive`

## Badges

### Status

| Class | Meaning |
|---|---|
| `.bb-status-pending` | Neutral, muted |
| `.bb-status-assigned` | Info blue |
| `.bb-status-in-progress` | Warning amber |
| `.bb-status-completed` | Success green |
| `.bb-status-cancelled` | Strikethrough, muted |

### Priority

| Class | Meaning |
|---|---|
| `.bb-priority-low` | Neutral |
| `.bb-priority-medium` | Warning |
| `.bb-priority-high` | Danger |

All badges use soft fills with borders — colour is never the sole communicator.

### User State

- `.bb-state-active` — success soft
- `.bb-state-inactive` — neutral with border

## Navigation

### Sidebar (Desktop)

- Fixed 240 px, dark background
- Active item: left border accent + subtle background
- Focus-visible: white outline

### Mobile (< 992 px)

- Off-canvas sidebar with slide-in transition
- Backdrop overlay (click or Escape to close)
- Top bar with hamburger toggle
- `aria-expanded` and `aria-controls` on toggle button

## Responsive Breakpoints

| Width | Layout |
|---|---|
| 375–430 px | Single column, stacked cards, full-width tables |
| 768 px | Two-column forms, sidebar still off-canvas |
| 992 px+ | Sidebar visible, multi-column layouts |
| 1366–1440 px | Content capped at 1280 px |

## Accessibility

- One `<h1>` per page (`.bb-page-title`)
- Heading hierarchy: h1 > h2 (card sections) > h3+
- Decorative icons: `aria-hidden="true"`
- Icon-only buttons: `aria-label` with descriptive text
- Form labels: explicit `for`/`id` associations
- Search inputs: `.visually-hidden` labels when icon provides context
- Alerts: `role="alert"` for errors, `role="note"` for informational
- Tables: `<thead>` with `<th>`, `.bb-num` for numeric alignment
- Focus: visible 2 px outline on all interactive elements
- No positive `tabindex` values
- No colour-only status communication (badges have text + borders)

## Reduced Motion

`@media (prefers-reduced-motion: reduce)` disables all transitions and animations.

## Print

`@media print`:
- Sidebar, top bar, backdrop, buttons, and dismissible alerts hidden
- White background, black text
- Cards: minimal border, no shadow, `break-inside: avoid`
- Links: black, no underline, no URL expansion

## Known Limitations

- No dark mode (not required for current operational use)
- No formal WCAG certification (design follows WCAG 2.1 AA guidelines)
- Print output tested for A4; letter size may have minor spacing differences
- Mobile navigation requires JavaScript (no CSS-only fallback)
- Browser support: modern evergreen browsers (Chrome, Firefox, Safari, Edge)
