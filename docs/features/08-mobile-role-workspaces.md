# Mobile role workspaces

Phase: 1

Status: Delivered

## User outcome

Customers and branch administrators use one authenticated, mobile-first Inertia workspace at `/app`. The interface keeps a fixed contextual header and bottom navigation while the main content owns scrolling, including safe-area support for Android devices.

## Customer workspace

- Create a pickup request from the selected branch, required pickup location, and one service option without choosing an organization or entering weight.
- See a waiting-for-pickup state until branch staff confirm actual weight and server pricing.
- Track active and historical orders through an expandable status timeline.
- Select a pickup point from OpenStreetMap using device geolocation, click-to-place, or a draggable pin, then view globally discovered branches ordered nearest first.
- Keep the branch dropdown available with `Gratis jemput & antar` or `Di luar radius gratis` surcharge guidance for every ranked option.
- Select a branch from either its map marker or list card; the map shows each outlet's configured free-delivery radius and a distinct selected marker.

## Branch-admin workspace

- Review orders only from assigned branches and advance each order through its allowed status transition.
- Enter the actual laundry weight for a pickup request and replace the unpriced ticket with the canonical `weighed` order.
- Record a counter order for an organization customer using the selected branch catalog.
- Browse organization customers plus neutral customers who already have an order in the organization.
- Access the account and logout surface without entering the owner CMS.

## Authorization and API surface

- `GET /app` is available only to the `customer` and `branch_admin` roles. Operational roles are redirected there from `/dashboard`; owner and superadmin accounts remain in the CMS.
- `GET /api/v1/mobile/context` resolves the authorized role. Customers receive active branches across active organizations, while branch administrators remain assignment-scoped.
- `GET /api/v1/mobile/branches/nearest` returns all coordinate-enabled active branches for customers, ordered nearest first with coverage and fee calculations.
- `GET /api/v1/mobile/orders` returns the authenticated customer's orders across organizations. Branch administrators continue using the organization-scoped order index for assigned branches.
- `GET /api/v1/organizations/{organization}/customers` is branch-admin-only and returns organization customers plus neutral customers with an existing order in that organization.
- Existing catalog, quote, order creation, pricing confirmation, status transition, tracking, and nearest-branch endpoints remain the canonical mutation and calculation surfaces.
- Browser requests use Laravel Sanctum's stateful session authentication. Form Requests, Actions, DTOs, API Resources, policies, and tenant filters remain authoritative.

## Pricing and routing rules

- The demo package includes 12 kg for Rp12.000 and charges Rp5.000 for each excess kilogram on a proportional gram basis. A measured 12,5 kg order is therefore priced at Rp14.500 by the server.
- Geolocation uses the existing Haversine nearest-branch service and the branch's free-delivery radius plus configured excess fee per started kilometer.
- The Android manifest grants coarse and fine location access; Capacitor requests runtime permission only after the customer taps the current-location action.
- Customers do not receive a laundry-price estimate during pickup request creation. The server calculates and snapshots final pricing only after branch staff submit actual weight.
- Branch-admin counter orders retain the immediate server quote flow.

## Optimistic behavior

- New orders appear immediately with a pending marker while the API request is in flight.
- Actual-weight confirmation marks the ticket pending, then reconciles the canonical priced order or restores the previous unpriced order.
- A pricing `409` refreshes the canonical order before another attempt.
- Branch status changes update the visible ticket and timeline immediately.
- Successful responses replace optimistic records with canonical API resources.
- Validation, authorization, or network failures restore the previous state and display the server error.
- Superseded context, branch, quote, and location requests are cancelled or ignored so stale responses cannot replace active state.
- Totals, receipts, and payment/QRIS actions stay hidden until pricing is confirmed. Successful pricing may show the configured WhatsApp weight-and-total message for explicit manual sending.
- Catalog, order mutation, pricing, payment, receipt, and WhatsApp routes derive their organization slug from the selected branch or canonical order instead of customer input.

## Verification

- Pest feature tests cover mobile-route role access, global customer branch discovery, cross-organization customer order filtering, branch-admin scoping, customer-directory boundaries, and cross-tenant denial.
- Vitest covers geolocation and map-point selection, nearest-first branch options, coverage labels, marker selection, pickup-request composition, actual-weight forwarding, optimistic pricing reconciliation and rollback, mobile navigation, status badges, and order-ticket rendering.
- Pest protects the Android manifest location-permission contract.
- Browser end-to-end verification is performed once, after Pest, Vitest, static analysis, type checking, and the production build pass.
- Playwright and raw Chromium CDP are not used.

## Deferred scope

Machine-capacity scheduling and slot locking remain scheduled for a later phase.
