# Mobile role workspaces

Phase: 1

Status: Delivered

## User outcome

Customers and branch administrators use one authenticated, mobile-first Inertia workspace at `/app`. The interface keeps a fixed contextual header and bottom navigation while the main content owns scrolling, including safe-area support for Android devices.

## Customer workspace

- Create a laundry order from the selected organization, branch, service package, weight, and optional pickup location.
- See the server-backed estimate before submitting, including decimal weights and package excess pricing.
- Track active and historical orders through an expandable status timeline.
- Find the nearest active branch with device geolocation and view pickup/delivery fee estimates.
- Switch between available organizations and branches when the customer is not tenant-bound.

## Branch-admin workspace

- Review orders only from assigned branches and advance each order through its allowed status transition.
- Record a counter order for an organization customer using the selected branch catalog.
- Browse organization customers plus neutral customers who already have an order in the organization.
- Access the account and logout surface without entering the owner CMS.

## Authorization and API surface

- `GET /app` is available only to the `customer` and `branch_admin` roles. Operational roles are redirected there from `/dashboard`; owner and superadmin accounts remain in the CMS.
- `GET /api/v1/mobile/context` resolves the authorized role, organization options, and active branch options.
- `GET /api/v1/organizations/{organization}/orders` returns only the authenticated customer's orders or orders from branches assigned to the branch administrator.
- `GET /api/v1/organizations/{organization}/customers` is branch-admin-only and returns organization customers plus neutral customers with an existing order in that organization.
- Existing catalog, quote, order creation, status transition, tracking, and nearest-branch endpoints remain the canonical mutation and calculation surfaces.
- Browser requests use Laravel Sanctum's stateful session authentication. Form Requests, Actions, DTOs, API Resources, policies, and tenant filters remain authoritative.

## Pricing and routing rules

- The demo package includes 12 kg for Rp12.000 and charges Rp5.000 for each excess kilogram on a proportional gram basis. A 12,5 kg order therefore estimates to Rp14.500.
- Geolocation uses the existing Haversine nearest-branch service and the branch's free-delivery radius plus configured excess fee per started kilometer.
- The server calculates and snapshots final pricing; client estimates are guidance only.

## Optimistic behavior

- New orders appear immediately with a pending marker while the API request is in flight.
- Branch status changes update the visible ticket and timeline immediately.
- Successful responses replace optimistic records with canonical API resources.
- Validation, authorization, or network failures restore the previous state and display the server error.
- Superseded organization, branch, quote, and location requests are cancelled or ignored so stale responses cannot replace the active tenant state.

## Verification

- Pest feature tests cover mobile-route role access, session-authenticated APIs, role-specific context, order filtering, customer-directory boundaries, and cross-tenant denial.
- Vitest covers geolocation behavior, optimistic update reconciliation and rollback, mobile navigation, status badges, and order-ticket rendering.
- Browser end-to-end verification is performed once, after Pest, Vitest, static analysis, type checking, and the production build pass.
- Playwright and raw Chromium CDP are not used.

## Deferred scope

Dynamic QRIS, thermal/Bluetooth printing, configurable WhatsApp templates, and the packaged Capacitor Android shell remain scheduled for later phases. The Phase 1 web workspace is API-connected and structured for those integrations.
