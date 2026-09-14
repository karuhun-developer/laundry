# LaundryHub Design System

Status: Active product contract

Last updated: 2026-09-14

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
- Map marker shadow: `0 4px 14px rgba(15, 23, 42, 0.35)`, exposed as `--laundry-map-marker-shadow`.
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

### Location and branch discovery

- Customers select their pickup point on an OpenStreetMap surface before calculating nearby branches.
- Ask for device location only when the customer chooses `Gunakan lokasi saya`; the returned point centers the map pin and immediately calculates nearby branches.
- Clicking the map or dragging its pin adjusts the pickup point before the customer chooses `Hitung dari titik ini`; labeled latitude and longitude fields remain the keyboard-accessible fallback.
- Nearest-branch cards show branch name, locality, distance, free-delivery coverage, and any extra fee before selection.
- Never imply GPS precision beyond the returned meter distance. Use Indonesian labels such as `Gratis antar-jemput` or `Biaya jarak Rp6.000`.
- Location denial and empty coverage states keep map placement and coordinate entry available instead of blocking order creation.
- Machine availability is not shown until capacity scheduling and slot-locking semantics are implemented.

### Outlet location editor

- Branch create and edit dialogs select coordinates on an OpenStreetMap surface, with labeled latitude and longitude inputs as the keyboard-accessible manual path.
- The Lokasi saya action requests browser/device geolocation only after an explicit user action.
- Clicking the map moves the outlet pin; the same pin is draggable for fine adjustment.
- Selected coordinates remain visible, editable, synchronized with the marker, and submitted through the existing typed branch form.
- Permission denial or unavailable geolocation keeps manual map placement usable and exposes a polite inline error.

### Service catalog and pricing

- Catalog content is grouped by service, then by the selected branch's active price options.
- Fixed packages show the included weight, base price, and excess tariff when configured.
- Per-kilogram options show the kilogram tariff and minimum chargeable weight when configured.
- Quote interactions display requested weight, chargeable weight, base/excess breakdown, and total rupiah before order confirmation.
- An overweight package without an excess tariff presents a blocking configuration message instead of an estimated total.

### Order creation and optimistic reconciliation

- Checkout shows the latest server quote, selected branch, delivery fee, and final estimate before confirmation.
- After submission, the client may insert a pending order ticket immediately, but marks it as pending and never treats its local number, total, or status as authoritative.
- A successful `201 Created` response replaces the pending ticket with the canonical order resource, including the server order number, immutable pricing snapshots, and initial timeline event.
- A failed mutation rolls the pending ticket back, preserves the user's editable input, and exposes the field error plus a retry action.
- Duplicate submission controls remain disabled while the same create request is pending.

### Owner CMS management and optimistic reconciliation

- Organization, city, branch, and user creation and editing happen in focused dialogs instead of permanent sidebar forms.
- Create and edit mutations insert or replace a visibly pending record immediately while the request is active.
- Pending cities are informational only and cannot be selected by a branch form until the server confirms their canonical identifier.
- City choices come from one shared global catalog and remain available regardless of the selected branch organization.
- Owners may add a missing city. Only superadmins receive city edit and delete controls because those mutations affect every organization.
- Success replaces optimistic records through the canonical Inertia page response. Failure removes them and keeps entered form values available for correction.
- Switching organization preserves a valid global city selection. Switching away from branch-admin clears branch assignments.
- Delete dialogs require the exact organization name, city name, branch code, or user email before enabling the destructive action.
- Dependency conflicts and validation errors keep the delete dialog open so the actor can recover without losing context.
- Empty tenant and location states explain the prerequisite action instead of presenting a disabled control without context.
- Desktop comparison tables become record cards before they would require horizontal page scrolling. Long names, email addresses, slugs, and addresses truncate or wrap inside their own surface.

### Reporting dashboard

- Filters appear in one bordered surface above the metrics. Date fields lead, superadmins receive an organization selector, and the shared global city selector never depends on an organization selection.
- Owner requests are locked to the authenticated organization even though the organization field is absent from the interface. Branch and service options narrow to the effective organization, while branch options also narrow to the selected city.
- Four summary cards show paid revenue, order count, average turnaround, and cancellation count with rate. Paid revenue includes only payments whose current status is paid.
- Turnaround measures order creation to the first completed status event. Orders without a completed event are excluded from the average, and a missing average is labeled `Belum tersedia`.
- Branches without matching orders remain visible with zero metrics so an empty operational result is distinguishable from an excluded branch.
- Wide screens use a comparison table with tabular numerals. Below the wide breakpoint, each branch becomes a compact card and the page never relies on horizontal scrolling.
- CSV download uses the last successfully applied filters, includes a UTF-8 BOM for spreadsheet compatibility, and preserves the same metric definitions and tenant boundary as the dashboard.

### Order tracking and optimistic transitions

- The order detail shows the current status before the timeline, with oldest-to-newest events including actor, timestamp, and optional note.
- Customers receive a read-only timeline. Authorized staff see only the valid next action plus cancellation while the order is active.
- After staff confirms a transition, the selected status appears immediately with a pending marker while the previous canonical order remains available for rollback.
- Success replaces the entire local order and timeline with the canonical API response. Failure restores both, explains the validation, access, network, or state-conflict cause, and exposes retry where safe.
- A state conflict triggers a fresh detail fetch before another transition. Locally generated event timestamps or actor identities are never displayed as durable history.
- Completed and cancelled orders replace transition controls with a terminal-state summary.

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

### `LocationPicker`

- Shared by the CMS outlet editor and customer pickup-point flow.
- Uses OpenStreetMap tiles with click-to-place and a draggable pin, while keeping labeled latitude and longitude fields available for keyboard entry.
- Device geolocation is requested only after an explicit action and moves the pin to the returned point.
- Permission errors remain inline and do not disable manual map placement.

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
- Destructive confirmation names the affected record and requires an exact typed value; native JavaScript confirmation dialogs are not used.

### Demo login selector

- Authentication uses the split layout on large screens and collapses to a focused single-column form on compact screens.
- Authentication surfaces use the `brand-*`, `surface-*`, and `ink-*` tokens with Poppins headings and Inter controls.
- Authentication is intentionally scoped to the light LaundryHub palette so system dark-mode preferences cannot reduce contrast on the white form surface.
- Default auth inputs, buttons, links, and password visibility controls expose a minimum 44 px interaction target and retain natural DOM tab order.
- Local development shows four compact role buttons above the login form.
- Selecting a role only fills the email and shared demo password; the user still submits the normal Fortify form.
- Production hides demo credentials through environment configuration.

### QRIS payment surface

- Dynamic QRIS payments expose a 360 px SVG as a base64 data URI so the same scannable artifact works in the web and Capacitor surfaces.
- The QR image sits on an opaque white field with the renderer's four-module quiet zone intact.
- Cash payments and unavailable QRIS states do not render an empty QR placeholder.
- The raw QRIS payload remains an API field for diagnostics and provider handoff, but the customer-facing surface uses the generated image.

### Payment reconciliation and receipt surface

- Order tickets always show canonical paid and outstanding totals; payment controls are available only to authorized branch admins.
- A pending QRIS payment occupies one focused panel with the scannable code and explicit `Tandai lunas` and `Tandai gagal` actions.
- Cash and QRIS mutations update the order summary immediately, show a pending marker, and restore the complete canonical order when the API rejects or conflicts.
- Conflict responses reload the order before another payment mutation; refresh failures keep the previous canonical order and expose both failure causes.
- Digital receipts are loaded from the server immediately before viewing or printing so catalog and payment history are not reconstructed from stale client state.
- Printer profiles are stored only on the current device and include connection type, device identity, display name, and 58 mm or 80 mm paper width.
- Bluetooth and USB discovery, test print, and receipt print expose visible progress and recoverable inline errors. Browser surfaces explain that native printer transport requires the Android app.

### WhatsApp template and suggestion surface

- Template management uses the same focused modal pattern as other CMS records. Branch overrides remain visibly distinct from organization defaults.
- Variable chips insert at the current text cursor, while a nearby live preview substitutes realistic non-sensitive sample values.
- Template deletion requires the exact scope/status key and never uses a native JavaScript confirmation dialog.
- A successful order transition may reveal a WhatsApp preview dialog; a failed or rolled-back transition never reveals one.
- The dialog shows the destination and complete rendered message before exposing `Buka WhatsApp`. No page load, transition, or suggestion fetch opens an external application automatically.
- Unavailable templates and invalid customer numbers remain recoverable inline states instead of disabled controls without explanation.

### Android shell and external links

- The launcher uses the brand-700 field with a high-contrast washing-machine mark, sky-blue water, and mint completion accent. Adaptive foreground artwork stays inside the Android safe zone.
- Splash resources use the canvas and surface-blue palette in both portrait and landscape orientations.
- The native external-link bridge accepts only exact `https://wa.me` destinations without credentials or custom ports. All other external navigation is rejected before an Android intent is created.
- External applications open only from an explicit user action. The web fallback follows the same validation contract before calling `window.open`.

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
- CMS interaction regression coverage uses Vitest and Vue Test Utils; Laravel behavior uses Pest. Playwright is not part of the Phase 1 test workflow.
- Lighthouse is measured on production output; accessibility regressions block release.

### Handoff evidence

- Screenshots and visual QA reports live under ignored local evidence unless explicitly approved for documentation.
- Each feature document records routes, authorization, API shape, test coverage, and known constraints.
- `CHANGELOG.md` records user-visible changes by phase.

### Accepted product debt

- `LaundryHub` remains a working product name, while the Android shell now carries a project-specific launcher and splash identity.
- Charts are deferred until reporting requirements are implemented.
- Native Android safe-area, external-link, printer, and release-signing behavior still requires representative physical-device certification.
- Bahasa Indonesia is delivered first; localization infrastructure may precede translated content.
