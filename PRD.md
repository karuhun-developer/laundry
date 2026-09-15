# LaundryHub Product Requirements Document

Status: Active

Owner: Product and Engineering

Last updated: 2026-09-15

## Product summary

LaundryHub is a multi-branch laundry operations platform for Indonesian laundry businesses. One organization can operate many branches across multiple cities while customers use a mobile-first application to place and track orders. Branch staff use the same application with role-appropriate controls, while owners and superadmins use a web CMS for governance, reporting, and configuration.

The product is API-first. The web CMS and Capacitor Android application consume the same Laravel API. Development uses SQLite, while all schema and queries must remain compatible with MySQL.

## Goals

- Let one organization manage branches across many cities without mixing branch-level data.
- Let a customer choose a pickup point, branch, and one laundry service without estimating the laundry weight.
- Recommend the nearest eligible branch and show whether pickup/delivery is free or incurs a distance surcharge.
- Let branch admins create, price, update, and complete orders quickly from an Android-sized screen.
- Give owners and superadmins a CMS for branches, users, catalogs, and operational visibility.
- Keep every privileged action auditable.
- Keep UI feedback immediate through optimistic updates while the API remains the source of truth.

## Personas and roles

### Customer

- Registers and signs in from the mobile surface with a normalized WhatsApp phone number and six-digit PIN.
- Saves one or more pickup addresses, selects or adjusts the pickup point, chooses from globally discovered branches ordered nearest first, then selects one service option.
- Does not select or need to understand an organization; tenant routing is derived internally from the selected branch and existing order.
- Submits a pickup request without entering weight or accepting an estimated laundry total.
- Views the confirmed weight, total, payment options, and status history after branch staff price the order.
- Receives an actionable WhatsApp link when a configured message is available.

### Branch admin

- Works only within assigned branches.
- Manages the current organization's shared customer directory by creating or linking global customer identities, updating eligible single-organization profiles, and resetting temporary PINs. Customer identities are never detached from the mobile directory.
- Creates customer orders and records measured weight.
- Advances an order through the configured laundry workflow.
- Sends the rendered price message through an explicit WhatsApp action and presents dynamic QRIS after pricing is confirmed.
- Prints receipts and records QRIS or cash payments.

### Owner

- Manages their organization, branches, branch staff, catalogs, and reports.
- Can add a missing city to the shared city catalog, but cannot edit or delete existing global cities.
- Can view all organization data but cannot access another organization.

### Superadmin

- Manages all organizations and platform users.
- Governs edits and deletion of the shared city catalog.
- Can inspect audit activity and platform health.

## Core domain

- Organization: tenant boundary and owner scope.
- City: globally shared geographic master data for branch discovery and reporting, unique by name and province.
- Branch: operational unit with contact details, coordinates, free-delivery radius, and excess-distance tariff.
- User: authenticated actor. Customer identity is global by normalized phone number, while customer access to an organization is represented by an explicit membership rather than an organization selector at registration.
- Customer organization membership: link between a global customer identity and an organization, used to scope branch-admin customer management without duplicating the customer's account.
- Service: laundry service category, for example wash-and-fold or dry cleaning.
- Price option: fixed package or per-kilogram price attached to a service, either organization-wide for every branch or restricted to one branch.
- Order: branch-owned customer transaction with immutable calculated pricing snapshots.
- Order item: selected price option, quantity or measured weight, and calculated subtotal.
- Order status event: append-only status history with actor and timestamp.
- Payment: QRIS or cash payment record introduced in Phase 2.
- Message template: WhatsApp template with validated variables introduced in Phase 3.

## Identity and entry flow

- `/login` separates customer and staff authentication modes. Customers use a normalized WhatsApp phone number and six-digit PIN; staff use email and password, with passkeys restricted to staff accounts.
- Public customer registration collects name, WhatsApp phone number, PIN, and PIN confirmation. It creates a global customer identity without choosing or attaching an organization.
- Phone inputs in `08...`, `+62...`, and `62...` forms normalize to the stored Indonesian `62...` form before uniqueness and credential checks.
- A branch admin may issue a one-time temporary PIN when creating a new customer or resetting an eligible customer's PIN. The PIN expires after 24 hours and the account is marked to change it.
- Changing or resetting a customer PIN advances the account's session version so stale customer web and API sessions are invalidated.
- Guest browsers may view the public landing page at `/`. Authenticated customers and branch admins are redirected to `/app`, while owners and superadmins are redirected to `/cms`; `/dashboard` remains a compatibility redirect. Native Android entry bypasses the landing page and opens `/app`.
- Landing SEO metadata is emitted entirely by the Blade shell and Inertia SSR remains disabled. All authenticated and authentication surfaces are `noindex,nofollow`.
- The branch-admin mobile shell keeps Queue, Customers, and More as isolated work areas. Queue owns search, order status work, WhatsApp order actions, and the new-order dialog; identity, organization, printer/device settings, and logout stay in More.

## Pricing rules

- A fixed package defines a package weight, package price, and optional excess per-kilogram price.
- A per-kilogram option defines a price per kilogram and optional minimum chargeable weight.
- A price option without a branch applies to every current and future branch in the service organization. A branch-specific option remains available only at that branch.
- Weight is stored as integer grams; money is stored as integer rupiah.
- Calculation never uses binary floating-point values.
- The order stores a pricing snapshot so future catalog changes do not rewrite historical totals.
- Package example: a 12 kg package at Rp12.000 with 500 g excess is charged according to the package excess rule configured for that option.
- Pricing calculations use integer grams and rupiah. Fractional rupiah is rounded half-up, and overweight packages without an excess tariff cannot be quoted.

## Delivery coverage rules

- Each branch may store latitude and longitude coordinates. Branches without coordinates are excluded from automatic routing.
- Customers receive nearest-branch recommendations across active branches in every active organization. Branches without coordinates are listed after ranked branches and are excluded from distance calculation.
- After customer workspace, organization context, and catalog loading are ready, the mobile surface first uses the customer's selected or default saved address. Only customers without a saved pickup point trigger automatic current-location discovery. Branch-admin sessions never trigger this automatic request.
- Native Android uses Capacitor geolocation while browsers use the platform geolocation API. Permission denial, disabled GPS, timeout, or unavailable coordinates remain non-blocking because map placement, coordinate entry, branch cards, and the branch selector remain available.
- The nearest ranked branch is selected automatically and its catalog is loaded. Request generations prevent late workspace, location, nearest-branch, or catalog responses from replacing newer state, and a manual branch selection cannot be overwritten by a late automatic result.
- Distance uses the Haversine formula in application code for identical SQLite and MySQL behavior. A spatial database optimization may be introduced later without changing the API contract.
- Pickup and delivery are free up to the branch's configured radius. The initial default radius is 10,000 meters.
- Distance beyond the free radius is charged per started kilometer: `ceil(excess meters / 1,000) × configured rupiah per km`.
- The branch dropdown remains available, ordered nearest to farthest after a pickup point is calculated, and labels each ranked branch as free or outside the free radius with an estimated surcharge.
- The pickup map shows outlet markers and configured free-radius overlays; selecting a marker selects the matching branch.
- The final order stores the selected branch, customer coordinates, recipient/contact/address snapshot, calculated distance, free radius, per-kilometer tariff, and delivery-fee snapshot.

## Order lifecycle

The default customer pickup workflow is:

1. Pickup requested
2. Weighed and priced
3. Washing
4. Drying
5. Ironing
6. Ready for pickup
7. Completed

Cancelled is a terminal exception state. Every transition is authorized, recorded, and shown in the customer timeline.

Customer orders start at `pickup_requested` with one selected service, pickup/delivery snapshots, zero financial totals, and no confirmed weight. Branch-admin counter orders retain immediate server pricing and start at `received`. Every order receives a server-generated `LDR-YYYYMMDD-########` number and an initial status event in the same transaction. Clients cannot submit authoritative totals, statuses, or snapshot values.

A customer cannot create another pickup request while an existing non-terminal pickup request is still active. The client disables the action using canonical order state, and the server enforces the same invariant transactionally.

After pickup, assigned branch staff submit the actual weight through the pricing-confirmation endpoint. The server locks the order, recalculates the selected price option, replaces the provisional zero-value item snapshot, stores `priced_at`, advances the order to `weighed`, and appends timeline and audit records atomically. Payment and QRIS creation remain unavailable until this confirmation succeeds.

Normal transitions advance exactly one step. Any non-terminal status may transition to `cancelled`; `completed` and `cancelled` cannot transition again. Duplicate, skipped, backward, and terminal transitions are rejected as state conflicts. The server locks the current order row, updates its status, appends the actor-snapshotted event, and writes audit activity atomically.

Customers can read only their own order timeline. Assigned branch admins, organization owners, and superadmins can read and transition orders in their authorization scope. Nested organization, branch, and order bindings hide cross-tenant records.

## Non-functional requirements

- Strict TypeScript with no `any`, ignored errors, or unchecked boundary data.
- PHPStan at the strictest practical Laravel level.
- Controller validation uses dedicated Form Request classes only.
- Application use cases live in Action classes and receive typed DTOs.
- API output uses Laravel API Resources.
- Authorization uses policies plus Spatie Permission roles and permissions.
- Media uses Spatie Media Library.
- Privileged changes use Spatie Activitylog.
- Feature and unit tests cover every delivered behavioral boundary.
- WCAG 2.2 AA target, keyboard support, 44 px touch targets, and reduced-motion support.
- No offline data mode in the Android application. Failed optimistic mutations roll back visibly.

## Phase roadmap

### Phase 1: Core operations

Deliverables:

- Laravel, Inertia, Vue 3, TypeScript, Tailwind, and Laravel Boost foundation.
- Authentication and role-aware navigation.
- Multi-organization branch and branch-membership domain backed by a shared global city catalog.
- Coordinate-based nearest-branch discovery with configurable free-delivery radius and excess-distance pricing.
- Customer branch discovery across active organizations without exposing an organization selector.
- Service catalog with organization-wide or branch-specific package and per-kilogram pricing.
- Order creation, deterministic total calculation, and status tracking.
- Owner/superadmin CMS and mobile-first customer/branch-admin surfaces.
- Owner/superadmin CMS for organizations, cities, branches, users, roles, and branch assignments.
- Modal create/edit workflows and typed-confirmation deletion for organizations, cities, branches, and managed users.
- Optimistic CMS mutation states that reconcile from canonical Inertia responses and roll back on validation or network failure.
- Accessible outlet coordinate selection with device geolocation, click-to-place, draggable markers, and keyboard-editable latitude/longitude fields.
- Seeded demonstration data and documented API endpoints.
- LaundryHub Demo seed data includes Jakarta Selatan and Bandung in the global city catalog.
- Deterministic demo credentials and role shortcuts on the local login screen.
- Responsive split authentication shell with product context on wide screens and a focused single-column form on compact screens.
- Role-separated customer phone/PIN and staff email/password entry with role-aware destinations and customer session invalidation.
- Branch-scoped customer management backed by shared global customer identities and organization memberships, without customer detach.
- Customer pickup-address management with selected/default address routing and immutable order snapshots.
- Isolated Queue, Customers, and More tabs for branch-admin work, with new-order creation in a Queue dialog and tenant-scoped Queue search.

Exit criteria:

- An owner can manage branches and catalogs within their organization.
- A branch admin can create a counter order, confirm the actual weight of a pickup request, and advance its status.
- A customer can submit a pickup request without weight and see the confirmed total and full status timeline after pricing.
- A customer can see nearest eligible branches and a deterministic pickup/delivery quote.
- Cross-organization access is denied by automated tests.
- Pest and Vitest tests, PHPStan, TypeScript checks, production build, API smoke checks, and manual UI review pass.

### Phase 2: Payments and receipts

Deliverables:

- Dynamic QRIS payload generation with CRC16-CCITT tests.
- Scannable server-rendered QRIS SVG images while retaining the raw payment payload.
- Audited cash and QRIS payment state with optimistic reconciliation and canonical conflict refresh.
- Bluetooth SPP and USB thermal printer capability through a Capacitor plugin.
- ESC/POS receipt templates, device-local printer profiles, discovery, test print, digital receipt, and reprint.

Exit criteria:

- A branch admin can record a full or partial cash payment and generate a dynamic QRIS payment.
- A pending QRIS payment can be reconciled to paid or failed without leaving stale optimistic state.
- A branch admin can load the canonical digital receipt and print it through a saved Bluetooth or USB profile.
- Customers and branch admins see the canonical paid and outstanding totals on every order ticket.
- Pest, Vitest, PHPStan, strict TypeScript checks, production build, and Capacitor sync pass.
- Browser and representative Android printer/device checks remain the final release gate.

### Phase 3: Communication and Android distribution

Deliverables:

- Configurable WhatsApp `wa.me` templates.
- Validated template variables such as `{customer_name}`, `{order_number}`, `{status}`, `{weight}`, and `{total}`.
- Organization defaults with branch-level overrides for each order status.
- Customer WhatsApp phone storage and an immutable phone snapshot on every order.
- Trigger suggestions after successful pricing confirmation or status changes without background WhatsApp automation.
- Explicit destination/message preview with a user-initiated open action.
- A Capacitor Android bridge restricted to exact `https://wa.me` URLs.
- LaundryHub launcher/adaptive icons, splash assets, signed build documentation, and device QA checklist.

Exit criteria:

- Owners and superadmins can manage templates in modal workflows with optimistic reconciliation and typed deletion confirmation.
- Branch-specific templates take precedence over organization templates without crossing tenant boundaries.
- Failed pricing confirmations and order transitions roll back and never request or open a WhatsApp suggestion.
- Native and browser transports open WhatsApp only after explicit confirmation and reject non-allowlisted URLs.
- Pest, Vitest, PHPStan, strict TypeScript checks, production build, and Capacitor sync pass.
- Browser end-to-end, emulator, signed Gradle build, and physical-device checks remain the final release gate.

### Phase 4: Reporting and scale

Status: In progress

Delivered:

- Owner and superadmin dashboards for paid revenue, order volume, turnaround time, cancellations, and branch comparison.
- Date, global city, organization, branch, service, and status filters with owner tenant locking.
- UTF-8 CSV export using the exact applied dashboard filters.

Remaining deliverables:

- Saved report views.
- Operational monitoring, retention controls, and scale hardening for MySQL.

## Explicitly out of scope for Phase 1

- Offline-first synchronization.
- Automated WhatsApp Business API delivery.
- Production QRIS acquirer integration.
- Printer discovery or native Android packaging.
- Machine-capacity calendars and transactional machine-slot locking.
- Multi-stop courier route optimization, live driver tracking, payroll, inventory, or franchise royalty accounting.

## Success metrics

- Branch admin can create a normal weighted order in under 60 seconds.
- Customer can identify current status and next expected step within 10 seconds.
- Owner can identify branch, user, and catalog ownership without spreadsheet reconciliation.
- No unauthorized tenant or branch data appears in API or UI tests.

## Open product decisions

- Final product and company name. `LaundryHub` is a working name.
- QRIS provider and settlement callback contract.
- Exact receipt printer models used for device certification.
