# Order creation and immutable pricing snapshots

Phase: 1

## Scope

Authenticated customers and authorized staff can create an order for one active branch. The server recalculates every catalog price and optional pickup/delivery fee, stores an immutable financial snapshot, creates the initial status event, and returns the canonical order representation in one database transaction.

One order accepts 1 to 20 distinct price options. Weight is submitted in integer grams and all monetary values are stored as integer rupiah.

## Authorization

- Customers create orders only for themselves. Organization-neutral customers may order from any active organization; organization-owned customers remain inside their organization.
- Branch admins must be assigned to the selected branch and must select a customer.
- Owners can create orders only inside their organization.
- Superadmins can create orders across organizations.
- A selected customer must have the customer role and belong either to the target organization or to no organization.
- Cross-organization branches, customers, price options, inactive tenants, and inactive catalog records are hidden with a not-found response.

## API

`POST /api/v1/organizations/{organization-slug}/branches/{branch}/orders`

The route requires a Sanctum-authenticated request. A staff request includes `customer_id`; a customer request must omit it. The `items` array contains `price_option_id` and `weight_grams`. Optional delivery coordinates are submitted together as `delivery.latitude` and `delivery.longitude`. Unknown fields are rejected, including client-supplied totals, statuses, and snapshots.

The successful `201 Created` response includes:

- Server-generated order number and initial `received` status.
- Branch and snapshotted customer identity.
- Catalog identity, pricing-rule snapshots, and calculation breakdown for every item.
- Items subtotal, delivery fee, and final total.
- Delivery distance and tariff snapshots when coordinates are supplied.
- Append-only initial status history with actor and timestamp.

The order number uses `LDR-YYYYMMDD-########`, where the final segment is the zero-padded database order ID.

## Snapshot and transaction rules

- The action reuses the catalog quote operation so checkout cannot diverge from the public quote contract.
- Service name/code, price-option name/type/rules, requested and chargeable weight, base/excess calculations, and subtotal are copied to each order item.
- Customer coordinates, calculated distance, free radius, excess-distance tariff, and delivery fee are copied to the order when delivery is requested.
- Later catalog, branch-tariff, or customer-name changes do not rewrite the historical order.
- Order, items, initial status event, and audit activity are created atomically. A failure rolls back the complete mutation.

## Optimistic client contract

The web and Capacitor clients may show a locally pending order immediately by using the latest quote response. The pending record is never authoritative: on success it is replaced with the complete order resource returned by this endpoint, including the server order number, totals, snapshots, and status history. A failed request removes or rolls back the pending record and exposes the field error plus a retry action.

## Tests

- Customer weighted order with minimum charge and excess-distance delivery fee.
- Assigned branch-admin fixed-package order for a selected customer.
- Catalog and delivery snapshot immutability.
- Missing authentication and unassigned branch-admin denial.
- Complete policy matrix for customer, branch admin, owner, superadmin, and unprivileged users.
- Cross-tenant branch, price-option, and customer isolation.
- Inactive organization, branch, service, and price-option rejection.
- Missing, malformed, duplicate, and server-owned field validation.
- Delivery request rejection when branch coordinates are unavailable.
- Initial status event and Spatie Activitylog record persistence.

## Deferred

Order listing/detail, authorized status transitions, customer tracking screens, WhatsApp actions, payments, QRIS, and receipt printing are delivered in their respective later increments.
