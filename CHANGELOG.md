# Changelog

All notable changes to LaundryHub are documented here. The project follows phase-based delivery and uses atomic Git commits for each feature or independently reversible update.

## [Unreleased]

## [1.1.1] - 2026-09-17

- Applied the Stitch-inspired slate, cyan, and mint visual system to CMS, mobile, login, and registration screens without changing backend behavior.
- Made light mode the default and only appearance, including the initial page render and previously saved dark-mode preferences.
- Simplified the customer home screen into shortcuts and pickup information, keeping active and recent order details in the Orders tab.
- Moved installed and available-version changelogs into a dedicated modal with a single labeled close control.
- Standardized the X Tutup controls in customer create, edit, and PIN-reset dialogs, and added a save icon to the new-PIN action.
- Included the original .stitch design references for future visual consistency.

## [1.1.0] - 2026-09-17

- Redesigned CMS, mobile, and public order-tracking interfaces with a cohesive visual system.
- Added home shortcuts for faster access to common workflows.
- Refined mobile home screens with green bento layouts, soft gradients, and lightweight glass navigation.
- Added customer order creation from both Home and Orders, and renamed the branch-admin account tab to Akun.
- Restored one-tap order details and payment dialogs, with direct access to verification and billing actions.
- Fixed pickup address dialogs appearing behind the order composer while preserving form input and keyboard focus.
- Improved order-detail hierarchy to make key status, payment, and fulfillment information easier to scan.
- Aligned customer modal layouts and controls for a more consistent experience.
- Added customer Chat Cabang buttons using the existing native-aware WhatsApp opener and removed redundant branch phone rows from admin order views.
- Added admin-only WhatsApp previews from saved order-status history, retaining the current server template and clearly dating earlier history.
- Refreshed Android launcher icons and splash-screen branding.

## [1.0.20] - 2026-09-16

- Merged digital receipts into signed public order-tracking pages (`/pesanan/{order_number}`), preserving valid legacy `/struk/{order}` links through signed redirects.
- Added high-visibility payment status badge, total amount due, and remaining balance to the public order page.
- Added dynamic QRIS generation, download capability, and payment gateway selection (Virtual Account & E-Wallet) directly on the public order tracking page for unpaid orders.
- Added digital receipt breakdown and print button for fully settled orders.
- Kept unpriced orders distinct from fully paid orders and preserved tracking and QRIS access during gateway outages.
- Made public payment selection atomic and repeat-safe, restoring the previous QRIS payment when gateway creation fails.
- Blocked method changes while a gateway payment is pending so subsequent successful webhooks remain reconcilable; QRIS can be selected again after confirmed failure or cancellation.
- Required actual APK binary access in update-token diagnostics, with accurate support for fine-grained and installation tokens.

## [1.0.19] - 2026-09-16

- Added feature for branch administrators to share dynamic QRIS and online gateway payment details directly to customer WhatsApp, including signed public order-tracking links.
- Updated branch order tickets to display "Lihat QRIS pembayaran" when a pending QRIS payment exists.
- Added `php artisan app:check-update-token` command to inspect GitHub Releases API token status, active OAuth scopes, and asset accessibility.
- Hardened `AppUpdateService` local changelog parsing and added safe fallback handling during update checks.

## [1.0.18] - 2026-09-15

- Fixed Android QRIS downloads by saving the generated SVG through the native system document picker, while preserving browser downloads and showing clear save feedback.
- Fixed signed Android update downloads so GitHub returns the APK binary instead of release-asset metadata.

## [1.0.17] - 2026-09-15

- Prevented unexpected update-service or logging failures from returning HTTP 500, with update checks and downloads routed through dedicated application actions.
- Requested Android notification permission when the mobile workspace first opens and registered the device immediately after permission is granted.

## [1.0.16] - 2026-09-15

- Fixed Android update checks returning HTTP 500 when the configured cache store is unavailable, while preserving direct GitHub checks and controlled failure responses.

## [1.0.15] - 2026-09-15

- Added permanent signed public order-tracking links so customers can monitor an order in the browser without signing in.
- Cached GitHub release checks for 15 minutes with a seven-day last-known-good fallback, safer diagnostics for token, access, rate-limit, and connectivity failures, and a configurable cache duration.
- Simplified the customer order list to a compact text search and removed the date filters from the mobile interface.

## [1.0.14] - 2026-09-15

- Added Android account-screen version details, current-version changelog, update checks, and checksum-verified APK installation through signed server-proxied downloads.
- Added clear paid/unpaid badges to branch order queues and moved payment and receipt actions directly below each order's payment summary.
- Added seven-day signed public digital-receipt links with WhatsApp sharing and Open Graph/Twitter metadata for rich link previews.

## [1.0.13] - 2026-09-15

- Improved customer pickup addresses with automatic primary-address selection, current-location defaults, clearer free-radius pricing, and duplicate-pickup protection that identifies the active order.
- Added inclusive date and text search for customer orders, a searchable branch-customer picker, and clearer modal controls.
- Added foreground-only customer order polling with immediate foreground and Firebase refreshes, overlap and stale-response protection, and silent network-failure handling.
- Refreshed payment details before displaying actions, added customer WhatsApp payment-verification requests, and normalized branch WhatsApp numbers while preserving clear invalid-number errors.
- Kept digital receipts available to customers while limiting printer controls and setup guidance to branch administrators.
- Added idempotent `payment_verified` Firebase notifications after committed manual or gateway payment transitions.
- Fixed Android WhatsApp links by opening the same validated `https://wa.me/...` URL used by browsers through an HTTPS view intent without false-negative handler checks.

## [1.0.12] - 2026-09-15

- Added organization-wide package and per-kilogram price options that apply automatically to every current and future branch.
- Kept branch-specific pricing available alongside global options, with duplicate-name validation isolated to each service and scope.
- Updated CMS catalog management, public branch catalogs, quotes, counter orders, and pickup pricing confirmation to support global options.
- Added regression coverage for global package creation without branches, future-branch availability, tenant isolation, quoting, and order pricing.

## [1.0.11] - 2026-09-15

- Added a Canvas and `jsQR` fallback so CMS branch management can decode QRIS images in browsers without native `BarcodeDetector` support.
- Added visible success and failure toasts for CMS create, update, and delete actions.
- Fixed branch deletion confirmation to require the exact branch name expected by server validation.
- Replaced the remaining Laravel mark on the public landing page and shared application logo with the LaundryHub washing-machine identity.

## [1.0.10] - 2026-09-15

- Added native Firebase Cloud Messaging registration, encrypted device-token storage, foreground and notification-tap refresh behavior, and account-level notification controls.
- Added queued pickup, pricing, and order-status notifications scoped to assigned branch administrators or the exact order customer, with automatic cleanup of invalid Firebase registrations.
- Added a global refresh action throughout the mobile workspace and extended authenticated sessions to a 30-day idle lifetime while preserving logout and customer PIN session invalidation.
- Changed the application palette, landing page, browser icons, and mobile surfaces to a cohesive nature-green visual system.
- Added required Firebase Android configuration through the protected `android-release` GitHub Actions environment and documented server credentials, queue workers, CI setup, and physical-device verification.

## [1.0.9] - 2026-09-15

- Added a public LaundryHub landing page with a fixed light background and Blade-owned title, canonical, robots, Open Graph, Twitter, favicon, and JSON-LD metadata while keeping Inertia SSR disabled and redirecting authenticated users by role.
- Added a customer pickup-address book with current-location defaults, map adjustment, default-address selection, persisted recipient/contact details, and immutable pickup-address snapshots on orders.
- Added server-side protection against duplicate active customer pickup requests and disabled the customer request action while the latest pickup is still awaiting branch handling.
- Changed the branch-admin Queue into the single operational order surface with debounced tenant-scoped search and a focused new-order dialog.
- Changed customer records to remain shared global identities without a detach action, while organization membership continues to protect profile and PIN mutations across tenants.
- Added visible success and failure toasts for mobile customer, order, payment, PIN, address, and printer mutations.
- Improved Bluetooth and USB printer discovery so an empty result is distinguished from a native transport failure.
- Fixed customer pickup serialization and address validation so a selected persisted pickup point creates one request without a misleading follow-up error.
- Added regression coverage for customer address management, saved-address nearest-branch selection, queue search and order dialog behavior, public landing SEO, dynamic QRIS, message-template scope and seeding, customer authentication, and shared-customer protections.

## [1.0.8] - 2026-09-15

- Added customer self-service dynamic QRIS payments for the exact outstanding order balance, including scannable SVG display and download.
- Added branch QRIS configuration in CMS with image decoding, manual payload fallback, EMV/CRC validation, encrypted storage, and activation status in the branch directory.
- Changed WhatsApp template management so superadmins manage organization-wide defaults while owners see inherited templates per branch and create or update branch-specific overrides when editing.
- Fixed owner template controls so organization-wide scope is unavailable and inherited organization templates cannot be deleted by owners.
- Fixed customer pickup requests so selected delivery coordinates are included in the serialized order request.
- Added regression coverage for dynamic QRIS authorization and rendering, QRIS branch configuration, customer pickup coordinates, and inherited WhatsApp template behavior.

## [1.0.7] - 2026-09-14

- Added normalized customer WhatsApp-phone and six-digit-PIN identity, nullable staff credentials for customer accounts, and global customer organization memberships with legacy membership backfill.
- Added separate customer and staff login paths, role-aware `/app` and `/cms` destinations, customer PIN change/reset flows, and session-version invalidation.
- Added a Blade-emitted `/login` SEO head contract, a blue-background branded favicon, and disabled Inertia SSR.
- Added branch-scoped customer directory creation/linking, eligible profile updates and PIN resets, membership-only detach, and one-time 24-hour temporary PIN disclosure.
- Isolated Queue, New order, Customers, and More content while keeping complete WhatsApp suggestion and destination behavior in Queue.
- Added customer-only automatic nearest-branch discovery with Capacitor Geolocation 8.2.2, native/browser fallbacks, stale-response and manual-selection protection, nearest sorting, automatic selection and catalog reload, delivery-fee guidance, and the Home branch-list shortcut.
- Added regression coverage for customer authentication, session invalidation, customer management, mobile tab isolation, geolocation, and nearest-branch ranking.
- Integrated Capacitor Geolocation 8.2.2 into the customer-only automatic location flow while reusing the existing Android coarse and fine location declarations; signed Android APK/AAB compilation remains in semantic-version-tag-triggered CI/CD and was not required locally for this documentation release.

## [1.0.6] - 2026-09-14

- Fixed the Android release quality gate by applying the Biome-required import ordering and formatting that blocked `v1.0.5` before artifact publication.
- Added optional Paywuz payment gateway support for direct QRIS and bank virtual-account methods, with provider-signed idempotent webhook reconciliation.
- Added local QRIS and bank logos to the online payment-method picker, with a generic card fallback for unsupported provider codes.
- Kept cash and branch-managed QRIS available when the optional Paywuz integration is disabled or incomplete.
- Added owner and superadmin CMS management for services, fixed-weight packages, and per-kilogram pricing with tenant-safe modal CRUD, optimistic updates, and typed deletion confirmation.
- Changed customer checkout into a pickup request: customers choose one service and pickup location without entering weight or seeing an unconfirmed laundry total.
- Added branch-admin actual-weight confirmation with atomic server pricing, a `weighed` timeline event, optimistic reconciliation, conflict refresh, and audit logging.
- Locked totals, cash/QRIS payment controls, and receipt pricing until a pickup request has been priced.
- Added `{weight}` and `{total}` WhatsApp variables and a price-message suggestion after successful pricing confirmation.
- Added default organization-level WhatsApp templates for every order status, including pickup-request and weighed notifications.
- Changed the customer mobile workspace to hide organization selection and discover active branches across organizations, while preserving branch- and order-derived tenant routing for API mutations.
- Added nearest-first customer branch options with explicit free pickup/delivery coverage or estimated excess-distance fees.
- Added selectable outlet markers and free-delivery radius overlays to the customer pickup map, including a distinct selected-branch marker.
- Changed GitHub Release titles to exactly match their Git tags while continuing to source descriptions from the matching changelog section.
- Changed customer pickup coordinates to a shared OpenStreetMap picker with current-location centering, click-to-place, and a draggable pin.
- Fixed Android current-location discovery by declaring coarse and fine location permissions in the application manifest.
- Fixed Android Bluetooth printer discovery by requesting scan and connect access at runtime.
- Added legacy storage permissions for file downloads on older Android versions while retaining scoped-storage compatibility.
- Added USB printer enumeration and per-device access permission through Android USB host APIs.

## [1.0.5] - 2026-09-14

- Added optional Paywuz payment gateway support for direct QRIS and bank virtual-account methods, with provider-signed idempotent webhook reconciliation.
- Added local QRIS and bank logos to the online payment-method picker, with a generic card fallback for unsupported provider codes.
- Kept cash and branch-managed QRIS available when the optional Paywuz integration is disabled or incomplete.
- Added owner and superadmin CMS management for services, fixed-weight packages, and per-kilogram pricing with tenant-safe modal CRUD, optimistic updates, and typed deletion confirmation.
- Changed customer checkout into a pickup request: customers choose one service and pickup location without entering weight or seeing an unconfirmed laundry total.
- Added branch-admin actual-weight confirmation with atomic server pricing, a `weighed` timeline event, optimistic reconciliation, conflict refresh, and audit logging.
- Locked totals, cash/QRIS payment controls, and receipt pricing until a pickup request has been priced.
- Added `{weight}` and `{total}` WhatsApp variables and a price-message suggestion after successful pricing confirmation.
- Added default organization-level WhatsApp templates for every order status, including pickup-request and weighed notifications.
- Changed the customer mobile workspace to hide organization selection and discover active branches across organizations, while preserving branch- and order-derived tenant routing for API mutations.
- Added nearest-first customer branch options with explicit free pickup/delivery coverage or estimated excess-distance fees.
- Added selectable outlet markers and free-delivery radius overlays to the customer pickup map, including a distinct selected-branch marker.
- Changed GitHub Release titles to exactly match their Git tags while continuing to source descriptions from the matching changelog section.
- Changed customer pickup coordinates to a shared OpenStreetMap picker with current-location centering, click-to-place, and a draggable pin.
- Fixed Android current-location discovery by declaring coarse and fine location permissions in the application manifest.
- Fixed Android Bluetooth printer discovery by requesting scan and connect access at runtime.
- Added legacy storage permissions for file downloads on older Android versions while retaining scoped-storage compatibility.
- Added USB printer enumeration and per-device access permission through Android USB host APIs.

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
