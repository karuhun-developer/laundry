# Phase 4: Reporting and Scale

Status: In progress

## Outcome

Owners can compare branch performance and the platform can move from development SQLite to production MySQL without domain rewrites.

## Scope

- Revenue, volume, turnaround, cancellation, and branch comparison reports.
- Date, city, branch, service, and status filters.
- CSV exports and saved report views.
- MySQL query profiling, indexes, retention policy, monitoring, and backup runbooks.

## Constraints

- Financial reporting remains operational, not statutory accounting.

## Delivered slice: operations reporting

- `GET /dashboard` renders the owner and superadmin reporting workspace through Inertia. Customers and branch admins continue to redirect to the mobile workspace, and actors without a reporting role receive `403`.
- Owners are locked to their organization on the server. Superadmins may report across every organization or select one organization.
- Filters cover an inclusive order-created date range plus organization, shared global city, branch, service, and current order status. Organization is never accepted from an owner request.
- Branch and service validation is scoped to the effective organization. Branch validation also follows the selected city, while the city catalog itself remains globally shared and independent from organizations.
- Paid revenue sums payments whose current status is `paid`. Order volume and cancellation rate use matching orders. Turnaround measures order creation to the first `completed` status event and excludes orders without one.
- Branches inside the selected scope remain visible when they have zero matching orders. The responsive interface uses a comparison table on wide screens and branch cards on smaller screens.
- `GET /dashboard/export` streams a UTF-8 CSV with a BOM, summary metrics, branch rows, and the exact same applied filters and authorization as the dashboard.
- Inertia filter visits preserve page position and state. CSV links use canonical backend-applied filters instead of unsaved form values.

## Verification

- Pest covers report roles, owner tenant isolation, global city options, combined filters, calculations, zero-order branches, and CSV isolation.
- Vitest covers query serialization, duration formatting, and organization/city-dependent option filtering.
- PHPStan, strict Vue TypeScript checks, formatting, and the production build remain required release checks.
- Browser end-to-end verification remains deferred to the final release gate.

## Remaining

- Saved report views.
- MySQL query profiling and index hardening against representative production volume.
- Retention controls, operational monitoring, and backup/restore runbooks.
