# Phase 1: Core Operations

Status: In progress

Target: a runnable, tested vertical slice for owner, branch admin, and customer.

## Included features

- Project foundation and development tooling.
- Authentication and role-aware access.
- Organization, branch, and branch membership management with a shared global city catalog.
- Service catalog with fixed-package and per-kilogram prices.
- Order creation with immutable pricing snapshots.
- Authorized order status transitions and customer timeline.
- Owner/superadmin CMS plus mobile-first customer and branch-admin surfaces.

## Authorization

- Superadmins can access every organization.
- Owners are restricted to their organization.
- Owners can add global cities; only superadmins can edit or delete them.
- Branch admins are restricted to assigned branches.
- Customers can access only their own orders and public branch/catalog data.
- Server policies are authoritative; navigation visibility is convenience only.

## API and UI contract

- JSON endpoints use versioned `/api/v1` routes and API Resources.
- Inertia CMS routes use web authentication and the same application actions as the API.
- Mutations return the canonical server representation for optimistic reconciliation.
- Error responses include a stable code, human-readable Indonesian message, and field errors when applicable.

## Testing contract

- Unit tests lock pricing calculations and status transition rules.
- Feature tests lock authorization, tenant isolation, and shared city-catalog boundaries.
- Vue component tests cover CMS modal interactions, global city selection, destructive confirmation, form prefill, and geolocation updates.
- Phase 1 does not use Playwright; interactive UI checks use Vitest/Vue Test Utils plus focused manual review.
- Static analysis and strict TypeScript checks are release gates.

## Completion checklist

- [x] Foundation scaffolded and dependencies configured.
- [x] Multi-branch roles and authorization delivered.
- [x] Pricing catalog delivered.
- [x] Order creation and immutable pricing snapshots delivered.
- [x] Status transitions and tracking timeline delivered.
- [x] Owner and superadmin management CMS delivered.
- [ ] Customer and branch-admin mobile surfaces delivered.
- [ ] Automated and manual QA passed.
