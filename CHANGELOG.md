# Changelog

All notable changes to LaundryHub are documented here. The project follows phase-based delivery and uses atomic Git commits for each feature or independently reversible update.

## [Unreleased]

- Changed GitHub Release titles to exactly match their Git tags while continuing to source descriptions from the matching changelog section.
- Changed customer pickup coordinates to a shared OpenStreetMap picker with current-location centering, click-to-place, and a draggable pin.
- Fixed Android current-location discovery by declaring coarse and fine location permissions in the application manifest.

## [1.0.4] - 2026-09-14

- Fixed Android resource linking by removing backup-rule references that had no matching XML resources while keeping application backup disabled.

## [1.0.3] - 2026-09-14

- Fixed Android release quality gates by building the production frontend manifest before running Pest.

## [1.0.2] - 2026-09-14

- Fixed clean Android release builds by generating Wayfinder route and action types before frontend quality checks.

## [1.0.1] - 2026-09-14

- Fixed the Android release workflow by installing and configuring the Android SDK before invoking `sdkmanager`.

## [1.0.0] - 2026-09-14

### Phase 1: Core operations

- Added the initial product requirements, delivery roadmap, and design-system contract.
- Added organization-scoped cities, branches, users, roles, permissions, audit logs, and branch media collections.
- Added organization-scoped nearest-branch discovery using Haversine distance, configurable free-delivery radii, and per-started-kilometer excess fees.
- Added organization-owned service catalogs, branch-specific fixed-package and per-kilogram prices, and deterministic gram-based quote APIs.
- Added authenticated order creation for customers and authorized staff with tenant isolation, immutable catalog and delivery snapshots, initial status history, and audited server-calculated totals.
- Added authenticated order detail tracking and authorized, row-locked status transitions with append-only timelines, audit records, tenant isolation, and optimistic reconciliation responses.
- Added the responsive owner/superadmin management console for organizations, cities, branches, users, roles, and branch access with tenant-scoped optimistic creation.
- Changed organization, city, branch, and user management to modal create/edit workflows with optimistic reconciliation.
- Changed cities from organization-owned records to a shared global catalog, with global name/province uniqueness, cross-organization branch selection, owner creation access, and superadmin-only edit/delete access.
- Added reusable delete dialogs that require exact typed confirmation and keep server-side dependency errors visible without native confirmation prompts.
- Added accessible branch coordinate selection with OpenStreetMap tiles, device geolocation, click-to-place, a draggable outlet marker, and labeled latitude/longitude inputs.
- Raised compact CMS action and dialog-close controls to the 44 px minimum interaction target.
- Added Bandung to the LaundryHub Demo city catalog.
- Fixed blank map coordinates being interpreted as a selected point at latitude/longitude zero.
- Added Vue component tests for CMS dialogs, validation announcements, optional edit passwords, and location selection.
- Added branch delivery configuration for coordinates, free pickup/delivery radius, and per-kilometer excess fees.
- Changed organization slugs to be generated automatically from names with collision-safe suffixes through Spatie Laravel Sluggable v3, while keeping branch-code duplicate validation as a field error.
- Added idempotent demo accounts for customer, branch-admin, owner, and superadmin roles with one-tap local login selection.
- Changed authentication pages to use the responsive split layout.
- Added the authenticated mobile workspace for customer order entry, tracking, nearest-branch discovery, and account access.
- Added the branch-admin mobile workspace for branch queues, optimistic status transitions, counter order entry, customer lookup, and account access.
- Added session-authenticated mobile context, order-list, and customer-directory APIs with role and tenant authorization.
- Added optimistic order creation and status transitions with canonical server reconciliation and automatic rollback.
- Changed mobile data loading to cancel superseded requests and ignore stale tenant responses during organization or branch switching.
- Fixed delivery coordinates becoming active before nearest-branch fees were available, preventing a temporary Rp0 delivery estimate.
- Security: restricted branch-admin customer directories to organization customers and neutral customers with an existing order in that organization.
- Changed the demo 12 kg package to Rp12.000 plus Rp5.000 per excess kilogram, including decimal-weight pricing such as 12,5 kg for Rp14.500.
- Added Pest and Vitest coverage for the Phase 1 mobile workspaces; browser end-to-end verification remains the final release gate.

### Phase 2: Payments and receipts

- Added server-rendered, scannable SVG data URIs to dynamic QRIS payment responses while preserving the raw QRIS payload.
- Added branch-admin cash and QRIS payment controls with partial-payment support, payment history, and paid/outstanding order summaries.
- Added optimistic payment creation and reconciliation with complete rollback, stale-tenant guards, and canonical refresh after state conflicts.
- Added canonical digital receipt loading and ESC/POS reprint actions from each branch-admin order ticket.
- Added Android Bluetooth SPP and USB printer discovery, device-local profiles, 58 mm and 80 mm receipt rendering, and test printing.
- Added Vitest coverage for payment rollback, QRIS reconciliation, receipt actions, and printer behavior, plus Pest coverage for the mobile payment resource contract.

### Phase 3: Communication and Android

- Added organization and branch-scoped WhatsApp templates with branch precedence, validated variables, modal CMS workflows, optimistic reconciliation, and typed deletion confirmation.
- Added normalized WhatsApp phone management for users and immutable customer phone snapshots on orders.
- Added status-aware WhatsApp suggestions with RFC 3986 encoded `wa.me` URLs, explicit message preview, and manual open actions after successful order transitions.
- Security: restricted native and browser external-link transport to exact `https://wa.me` URLs without subdomains, credentials, alternate schemes, or custom ports.
- Added LaundryHub launcher, adaptive-icon, and portrait/landscape splash assets for Android.
- Added Android signing and physical-device release guidance.
- Added Pest and Vitest coverage for tenant authorization, template precedence and validation, phone snapshots, suggestion generation, optimistic rollback, dialog behavior, and external-link validation.
- Added tag-gated Android CI/CD that validates release metadata, runs Pest and Vitest, builds signed APK/AAB artifacts, publishes checksums, and creates or updates the matching GitHub Release.

### Phase 4: Reporting and scale

- Added an owner and superadmin operations dashboard for paid revenue, order volume, turnaround time, cancellations, and branch comparison.
- Added date, global city, organization, branch, service, and order-status filters with tenant-safe option validation.
- Added tenant-isolated UTF-8 CSV exports that use the dashboard's applied filters and metric definitions.
- Added Pest and Vitest coverage for report authorization, calculations, filters, exports, query serialization, and responsive option filtering.

## Release policy

- `Added` for new behavior.
- `Changed` for behavior changes.
- `Fixed` for defect corrections.
- `Security` for authorization, privacy, and dependency security changes.
- Every Android release tag must use `vMAJOR.MINOR.PATCH` and have a non-empty matching `## [MAJOR.MINOR.PATCH] - YYYY-MM-DD` section before the tag is pushed.
- Move the release's completed entries out of `[Unreleased]` into that versioned section; its contents become the GitHub Release notes.
