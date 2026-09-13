# LaundryHub Design System

Status: Phase 1 contract

Last updated: 2026-09-13

## 0. Research Log

- Live reference: Kireiwash service page inspected at 375, 768, and 1280 px. Harvested deep royal-blue trust cues, Poppins-led hierarchy, service segmentation, city/outlet grouping, medium radii, and blue-tinted depth shadows.
- Live reference: LaundryGO mobile app page inspected at 375, 768, and 1280 px. Harvested pale-blue operational surfaces, sky-blue accents, mobile-first cards, and feature taxonomy covering orders, customers, reports, roles, QRIS, printer, receipts, tracking, and WhatsApp.
- Local product reference: `../pos-kacaw` printer capability and QRIS modules inspected for future transport boundaries. No source code is copied in Phase 1.
- Direction: a **clean garment-care command center**. The atmosphere is bright, reassuring, and operational; royal blue carries trust, pale blue carries calm workspace depth, and a receipt-like order ticket is the memorable product object.
- Personas considered: customer in a hurry on a phone; branch admin working with wet hands and intermittent attention; owner comparing branches on desktop; superadmin investigating access and audit history.

## 1. Foundations

### Color tokens

| Token | Value | Use |
| --- | --- | --- |
| `ink-950` | `#0B1220` | Primary text |
| `ink-700` | `#344054` | Secondary text |
| `ink-500` | `#667085` | Muted text |
| `canvas` | `#F7FAFF` | Application background |
| `surface` | `#FFFFFF` | Primary surface |
| `surface-blue` | `#EAF4FF` | Operational grouping |
| `line` | `#D7E2F0` | Borders and separators |
| `brand-700` | `#0943A9` | Primary actions and selected state |
| `brand-600` | `#1559C7` | Hover and emphasis |
| `brand-100` | `#DDEBFF` | Soft selected background |
| `sky-400` | `#69C3F6` | Informational highlight |
| `mint-500` | `#17A673` | Success and completed state |
| `amber-500` | `#E98A15` | Warning and in-progress emphasis |
| `red-600` | `#D92D20` | Destructive and error state |

All combinations must meet WCAG 2.2 AA. Pale semantic colors are backgrounds only; semantic text uses the corresponding dark tone.

### Typography

- Display and headings: Poppins, fallback `ui-sans-serif, system-ui`.
- Body and controls: Inter, fallback `ui-sans-serif, system-ui`.
- Numeric totals: Inter with tabular numerals.
- Scale: 12, 14, 16, 18, 24, 32, and 40 px.
- Body line height: 1.5. Headings use 1.15 to 1.25.

### Spacing, shape, and depth

- Base spacing unit: 4 px. Product spacing uses 4, 8, 12, 16, 20, 24, 32, and 48 px.
- Radius: 8 px controls, 12 px compact cards, 16 px content cards, 20 px feature/ticket surfaces, and full pills only for tags or segmented controls.
- Shadow low: `0 8px 24px rgba(9, 67, 169, 0.08)`.
- Shadow raised: `0 18px 48px rgba(9, 67, 169, 0.14)`.
- Focus ring: 3 px translucent `brand-600` halo plus visible 1 px edge.

## 2. Layout and Responsive Behavior

### CMS shell

- Desktop: fixed 264 px sidebar, fixed utility header, one scrolling main content region.
- Tablet: compact 80 px navigation rail and one scrolling main region.
- Mobile: sidebar becomes a modal drawer; actions that affect the current record remain in reach.
- Content max width is 1440 px with fluid gutters. Tables switch to cards when horizontal comparison is no longer useful.

### Mobile application shell

- Bounded `100dvh` shell with one scroll owner.
- Fixed contextual header and bottom navigation.
- Content respects safe-area insets.
- No horizontal overflow at 375 px, including long names, rupiah totals, and order numbers.

### Breakpoints

- Compact: below 640 px.
- Medium: 640 to 1023 px.
- Wide: 1024 px and above.

Components respond to their container where possible; viewport breakpoints own only the outer shell.

## 3. Information Architecture

### Owner and superadmin CMS

- Overview
- Organizations (superadmin only)
- Cities and branches
- Users and access
- Services and pricing
- Orders
- Reports (progressively delivered)
- Audit activity
- Settings

### Customer mobile navigation

- Home
- Orders
- Branches
- Account

### Branch admin mobile navigation

- Queue
- New order
- Customers
- More

The role switch changes available navigation; it never hides authorization failures behind client-only logic.

## 4. Content and Voice

- Indonesian-first, concise, warm, and operational.
- Buttons use verbs: `Buat pesanan`, `Simpan tarif`, `Lanjutkan status`.
- Status text answers what happened and what happens next.
- Error messages name the field or action and explain recovery.
- Avoid playful wording during payment, destructive, or access-control actions.
- Currency is formatted as Indonesian rupiah with no invented decimal precision. Weight is shown in kg while stored as grams.

## 5. Component Primitives and States

Every primitive must be demonstrated in a development showcase before product screens compose it.

### `AppButton`

- Variants: primary, secondary, quiet, destructive.
- Sizes: compact and standard; standard touch target is at least 44 px.
- States: default, hover, focus-visible, active, loading, disabled.

### `AppField`

- Supports label, hint, prefix/suffix, validation message, and character/unit context.
- States: default, hover, focus, filled, invalid, disabled, read-only.

### `AppSelect` and `SegmentedControl`

- Keyboard navigable with a persistent selected state.
- Segmented controls are used only for 2 to 5 peer options.

### `StatusBadge`

- Semantic state uses text, icon, and color together.
- Supports received, active processing, ready, completed, cancelled, and payment states.

### `SurfaceCard`

- Variants: plain, blue-tinted, interactive, and raised.
- Interactive cards expose one clear primary action and a visible focus state.

### `OrderTicket`

- Signature component inspired by a physical laundry receipt without copying a brand asset.
- Contains order number, branch, status, total, and next action.
- Uses a dashed internal separator and paired edge notches; depth must remain subtle and legible.

### `StatusTimeline`

- Vertical on compact screens, horizontal only when every label remains readable.
- Current step is emphasized; future steps remain visible; completed steps include timestamps.

### `DataTable` and `RecordList`

- Table for wide comparison, record list/cards for compact task execution.
- Loading skeleton, empty, error, and populated states are mandatory.

### `Toast` and optimistic state

- Optimistic changes appear immediately with a pending marker.
- Success resolves quietly. Failure rolls back and exposes a retry action.
- Toasts never carry information that is unavailable elsewhere on the screen.

### `Dialog` and `Drawer`

- Focus is trapped and restored. Escape closes non-destructive dialogs.
- Destructive confirmation names the affected record.

## 6. Interaction and Motion

- Motion communicates hierarchy, mutation progress, or spatial continuity.
- Fast feedback: 120 ms. Standard transitions: 180 ms. Drawers: 240 ms.
- Easing: `cubic-bezier(0.2, 0.8, 0.2, 1)`.
- Animate only transform, opacity, and filter.
- Buttons compress to 98% scale on press when motion is allowed.
- Optimistic rows use a low-contrast pulse on the pending marker, never the full surface.
- `prefers-reduced-motion: reduce` removes non-essential movement and preserves immediate state changes.

## 7. Accessibility and Inclusive Constraints

- WCAG 2.2 AA is the minimum target.
- All controls work with keyboard and visible focus.
- Touch targets are at least 44 by 44 px.
- Status never relies on color alone.
- Forms connect labels, descriptions, and errors programmatically.
- Live regions announce async save success or failure without interrupting typing.
- Cognitive load: one primary task per mobile screen and no more than one destructive action in the primary action region.
- Branch admins may be distracted or wearing gloves; controls require generous targets and avoid fragile gestures.
- Customers on slow networks see stable skeletons and explicit retry, not blank layouts.
- Owner tables preserve zoom at 200% without hiding essential actions.

## 8. Quality, Handoff, and Accepted Debt

### Quality gates

- Primitive showcase at 375, 768, and 1280 px before product screens.
- Product screens at the same widths with empty, error, loading, long-content, keyboard, and reduced-motion checks.
- No raw color values or ad-hoc spacing inside product components; tokens are defined centrally.
- Production build and browser console must be clean.
- Lighthouse is measured on production output; accessibility regressions block release.

### Handoff evidence

- Screenshots and visual QA reports live under ignored local evidence unless explicitly approved for documentation.
- Each feature document records routes, authorization, API shape, test coverage, and known constraints.
- `CHANGELOG.md` records user-visible changes by phase.

### Accepted Phase 1 debt

- Final brand name and logo are intentionally absent; the wordmark is typographic.
- Charts are deferred until reporting requirements are implemented.
- Native Android safe-area and printer interaction can only be fully certified in Phases 2 and 3.
- Bahasa Indonesia is delivered first; localization infrastructure may precede translated content.
