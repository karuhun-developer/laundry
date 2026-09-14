# Order tracking and status transitions

Phase: 1

## Scope

Authenticated customers can read their own canonical order and complete status timeline. Authorized branch staff, owners, and superadmins can read orders in scope and append the next valid lifecycle status. Every successful transition updates the order, appends a status event, and writes an audit activity in one transaction.

## Authorization

- Customers can view only orders where they are the recorded customer and cannot change status.
- Branch admins require the `manage orders` permission to view and the `update order status` permission to transition an order. They remain restricted to assigned branches.
- Owners can view and transition orders only inside their organization.
- Superadmins can view and transition orders across organizations.
- Unauthenticated requests return `401`; authenticated out-of-scope requests return `403`.
- Organization, branch, and order route bindings are nested. A mismatched organization, branch, or order returns `404` before authorization and does not reveal cross-tenant records.

## API

`GET /api/v1/organizations/{organization-slug}/branches/{branch}/orders/{order}`

Returns the canonical order resource with branch, customer snapshot, item and pricing snapshots, delivery snapshot, current status, and the complete append-only timeline ordered from oldest to newest.

`POST /api/v1/organizations/{organization-slug}/branches/{branch}/orders/{order}/status-events`

The authenticated staff payload is:

```json
{
  "status": "washing",
  "note": "Optional operational note"
}
```

`status` is required. `note` is optional, trimmed, and limited to 500 characters. Unknown fields are rejected. A successful response returns the same canonical order resource as the detail endpoint, including the newly appended event.

`POST /api/v1/organizations/{organization-slug}/branches/{branch}/orders/{order}/pricing`

Assigned branch staff submit the selected `price_option_id` and actual `weight_grams` for an unpriced `pickup_requested` order. The server locks the order and item, verifies the selected option, calculates the canonical price, stores immutable item and total snapshots, sets `priced_at`, advances the order to `weighed`, and appends timeline and audit records atomically. Repeated, mismatched, or out-of-state confirmation returns `409 Conflict`.

## Transition rules

The staff counter lifecycle advances exactly one step at a time:

`received → weighed → washing → drying → ironing → ready_for_pickup → completed`

The customer pickup lifecycle enters `weighed` through successful pricing confirmation:

`pickup_requested → weighed → washing → drying → ironing → ready_for_pickup → completed`

- Any non-terminal status can transition to `cancelled`.
- `completed` and `cancelled` are terminal.
- Duplicate, skipped, backward, and terminal-state transitions return `409 Conflict` with code `INVALID_ORDER_STATUS_TRANSITION`.
- The transition action locks the order row before checking its current state. Concurrent requests therefore evaluate against the latest committed status.
- Invalid transitions do not update the order, append history, or write transition activity.

## Timeline and audit rules

- Status events are append-only and store the status, actor ID, actor-name snapshot, optional note, and timestamp.
- The order's current status is a projection of the latest successful transition.
- Every accepted transition writes a Spatie Activitylog record with the previous status, new status, note, actor, and order subject.
- Order status, timeline event, and audit record are committed atomically.

## Optimistic client contract

The web and Capacitor clients may display the selected next status immediately with a pending marker. While pending, the client keeps the previous canonical order and timeline available for rollback.

- Success replaces the complete local order and timeline with the API response.
- Validation, authorization, conflict, or network failure restores the previous status and timeline and offers retry.
- A `409` prompts the client to fetch the detail endpoint before another transition because another actor may have advanced the order.
- Actual-weight confirmation follows the same optimistic pending, canonical replacement, rollback, and `409` refresh rules.
- The server timeline is always authoritative; clients never invent actor data, timestamps, or durable status events.

## Tests

- Customer ownership, branch assignment, owner tenant scope, superadmin access, unauthenticated denial, and nested-binding isolation.
- Complete normal progression and cancellation from every active state.
- Exhaustive enum transition matrix for skipped, backward, duplicate, and terminal transitions.
- Request validation for required and known statuses, note length, and unknown fields.
- Canonical response shape and ordered timeline.
- Atomic status event and Spatie Activitylog persistence.
- Failed-transition rollback with no order, timeline, or audit side effect.
- Actual-weight pricing authorization, validation, canonical calculation, conflict handling, payment lock, timeline, and audit persistence.

## Payment and communication gates

Payment and QRIS creation are rejected until `priced_at` is present. After pricing succeeds, the branch-admin workspace requests the configured `weighed` WhatsApp suggestion so staff can explicitly send the confirmed weight and total.
