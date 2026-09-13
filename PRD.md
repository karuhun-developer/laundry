# LaundryHub Product Requirements Document

Status: Active

Owner: Product and Engineering

Last updated: 2026-09-13

## Product summary

LaundryHub is a multi-branch laundry operations platform for Indonesian laundry businesses. One organization can operate many branches across multiple cities while customers use a mobile-first application to place and track orders. Branch staff use the same application with role-appropriate controls, while owners and superadmins use a web CMS for governance, reporting, and configuration.

The product is API-first. The web CMS and Capacitor Android application consume the same Laravel API. Development uses SQLite, while all schema and queries must remain compatible with MySQL.

## Goals

- Let one organization manage branches across many cities without mixing branch-level data.
- Let a customer choose a branch, service, package, or per-kilogram price and follow order progress.
- Recommend the nearest eligible branch and show any pickup/delivery surcharge before checkout.
- Let branch admins create, price, update, and complete orders quickly from an Android-sized screen.
- Give owners and superadmins a CMS for branches, users, catalogs, and operational visibility.
- Keep every privileged action auditable.
- Keep UI feedback immediate through optimistic updates while the API remains the source of truth.

## Personas and roles

### Customer

- Registers and signs in from the mobile surface.
- Selects a city, branch, service, and pricing option.
- Views order totals and status history.
- Receives an actionable WhatsApp link when a configured message is available.

### Branch admin

- Works only within assigned branches.
- Creates customer orders and records measured weight.
- Advances an order through the configured laundry workflow.
- Prints receipts and presents dynamic QRIS in later phases.

### Owner

- Manages their organization, cities, branches, branch staff, catalogs, and reports.
- Can view all organization data but cannot access another organization.

### Superadmin

- Manages all organizations and platform users.
- Can inspect audit activity and platform health.

## Core domain

- Organization: tenant boundary and owner scope.
- City: geographic grouping for branch discovery and reporting.
- Branch: operational unit with contact details, coordinates, free-delivery radius, and excess-distance tariff.
- User: authenticated actor with global or organization/branch-scoped roles.
- Service: laundry service category, for example wash-and-fold or dry cleaning.
- Price option: fixed package or per-kilogram price attached to a service and branch.
- Order: branch-owned customer transaction with immutable calculated pricing snapshots.
- Order item: selected price option, quantity or measured weight, and calculated subtotal.
- Order status event: append-only status history with actor and timestamp.
- Payment: QRIS or cash payment record introduced in Phase 2.
- Message template: WhatsApp template with validated variables introduced in Phase 3.

## Pricing rules

- A fixed package defines a package weight, package price, and optional excess per-kilogram price.
- A per-kilogram option defines a price per kilogram and optional minimum chargeable weight.
- Weight is stored as integer grams; money is stored as integer rupiah.
- Calculation never uses binary floating-point values.
- The order stores a pricing snapshot so future catalog changes do not rewrite historical totals.
- Package example: a 12 kg package at Rp12.000 with 500 g excess is charged according to the package excess rule configured for that option.

## Delivery coverage rules

- Each branch may store latitude and longitude coordinates. Branches without coordinates are excluded from automatic routing.
- Customers receive nearest-branch recommendations only from active branches in the selected organization.
- Distance uses the Haversine formula in application code for identical SQLite and MySQL behavior. A spatial database optimization may be introduced later without changing the API contract.
- Pickup and delivery are free up to the branch's configured radius. The initial default radius is 10,000 meters.
- Distance beyond the free radius is charged per started kilometer: `ceil(excess meters / 1,000) × configured rupiah per km`.
- The final order stores the selected branch, customer coordinates, calculated distance, free radius, per-kilometer tariff, and delivery-fee snapshot.

## Order lifecycle

The Phase 1 default workflow is:

1. Received
2. Weighed
3. Washing
4. Drying
5. Ironing
6. Ready for pickup
7. Completed

Cancelled is a terminal exception state. Every transition is authorized, recorded, and shown in the customer timeline.

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
- Multi-organization, city, branch, and branch-membership domain.
- Coordinate-based nearest-branch discovery with configurable free-delivery radius and excess-distance pricing.
- Service catalog with package and per-kilogram pricing.
- Order creation, deterministic total calculation, and status tracking.
- Owner/superadmin CMS and mobile-first customer/branch-admin surfaces.
- Seeded demonstration data and documented API endpoints.

Exit criteria:

- An owner can manage branches and catalogs within their organization.
- A branch admin can create an order and advance its status.
- A customer can see the order total and full status timeline.
- A customer can see nearest eligible branches and a deterministic pickup/delivery quote.
- Cross-organization access is denied by automated tests.
- Tests, PHPStan, TypeScript checks, production build, API smoke checks, and browser QA pass.

### Phase 2: Payments and receipts

Deliverables:

- Dynamic QRIS payload generation with CRC16-CCITT tests.
- Payment state and reconciliation workflow.
- Bluetooth SPP and USB thermal printer capability through a Capacitor plugin.
- ESC/POS receipt templates, printer profiles, test print, and digital receipt.

### Phase 3: Communication and Android distribution

Deliverables:

- Configurable WhatsApp `wa.me` templates.
- Validated template variables such as `{customer_name}`, `{order_number}`, and `{status}`.
- Trigger suggestions on status changes without background WhatsApp automation.
- Capacitor Android project, permissions, signed build documentation, and device QA.

### Phase 4: Reporting and scale

Deliverables:

- Revenue, order volume, turnaround time, and branch comparison reports.
- Exports, saved filters, and owner dashboards.
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
- Whether customers self-create orders or submit pickup requests in the first production rollout.
