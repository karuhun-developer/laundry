# Changelog

All notable changes to LaundryHub are documented here. The project follows phase-based delivery and uses atomic Git commits for each feature or independently reversible update.

## [Unreleased]

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
- Fixed organization slug and branch code uniqueness validation so normalized duplicates return field errors instead of database constraint failures.
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

## Release policy

- `Added` for new behavior.
- `Changed` for behavior changes.
- `Fixed` for defect corrections.
- `Security` for authorization, privacy, and dependency security changes.
