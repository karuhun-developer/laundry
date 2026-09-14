# Order creation and immutable pricing snapshots

Phase: 1

## Scope

Authenticated customers submit a pickup request for one active branch by choosing one service and a pickup location. They do not enter weight and the server does not expose an unconfirmed laundry total. Authorized staff can still create weighted counter orders with immediate server pricing. Both paths create the initial status event and return the canonical order representation in one database transaction.

A customer pickup request accepts exactly one price option. A staff counter order accepts 1 to 20 distinct price options. Confirmed weight is submitted in integer grams and all monetary values are stored as integer rupiah.

## Authorization

- Customers create orders only for themselves. Organization-neutral customers may order from any active organization; organization-owned customers remain inside their organization.
- Branch admins must be assigned to the selected branch and must select a customer.
- Owners can create orders only inside their organization.
- Superadmins can create orders across organizations.
- A selected customer must have the customer role and belong either to the target organization or to no organization.
- Cross-organization branches, customers, price options, inactive tenants, and inactive catalog records are hidden with a not-found response.

## API

`POST /api/v1/organizations/{organization-slug}/branches/{branch}/orders`

The route requires a Sanctum-authenticated request. A staff request includes `customer_id` and `weight_grams` for every item. A customer request must omit both fields, submit exactly one `price_option_id`, and include `delivery.latitude` plus `delivery.longitude`. Unknown fields are rejected, including client-supplied totals, statuses, and snapshots.

The successful `201 Created` response includes:

- Server-generated order number and either `pickup_requested` for customers or `received` for staff.
- Branch and snapshotted customer identity.
- Provisional selected-service snapshot with zero weight and subtotal for customer pickup requests.
- Immutable catalog identity, pricing-rule snapshots, and calculation breakdown for weighted staff orders.
- Delivery fee snapshot for both paths; items subtotal and final total remain zero until customer pickup pricing is confirmed.
- Delivery distance and tariff snapshots when coordinates are supplied.
- Append-only initial status history with actor and timestamp.

The order number uses `LDR-YYYYMMDD-########`, where the final segment is the zero-padded database order ID.

## Snapshot and transaction rules

- Staff counter creation reuses the catalog quote operation so checkout cannot diverge from the public quote contract.
- Customer pickup creation validates the selected option belongs to the active branch catalog but defers its calculated snapshot until actual weight confirmation.
- Service name/code, price-option name/type/rules, requested and chargeable weight, base/excess calculations, and subtotal are copied to each order item.
- Customer coordinates, calculated distance, free radius, excess-distance tariff, and delivery fee are copied to the order when delivery is requested.
- Later catalog, branch-tariff, or customer-name changes do not rewrite the historical order.
- Order, items, initial status event, and audit activity are created atomically. A failure rolls back the complete mutation.

## Optimistic client contract

The web and Capacitor clients may show a locally pending pickup request immediately from the selected branch, service, and location. The pending record is never authoritative: on success it is replaced with the complete order resource returned by this endpoint, including the server order number, delivery snapshot, and status history. A failed request removes or rolls back the pending record and exposes the field error plus a retry action.

## Tests

- Customer pickup request with one service, required coordinates, zero total, and no confirmed pricing timestamp.
- Assigned branch-admin fixed-package order for a selected customer.
- Catalog and delivery snapshot immutability.
- Missing authentication and unassigned branch-admin denial.
- Complete policy matrix for customer, branch admin, owner, superadmin, and unprivileged users.
- Cross-tenant branch, price-option, and customer isolation.
- Inactive organization, branch, service, and price-option rejection.
- Missing, malformed, duplicate, and server-owned field validation.
- Delivery request rejection when branch coordinates are unavailable.
- Initial status event and Spatie Activitylog record persistence.

## Pricing handoff

Actual-weight confirmation, payment locking, WhatsApp price suggestions, and the transition to `weighed` are documented in the tracking and mobile-workspace feature contracts.
