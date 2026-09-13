# Owner management console

Phase: 1

## User outcome

Owners can manage their organization’s cities, branches, users, roles, and branch assignments from a responsive web CMS. Superadmins receive the same tenant-management tools plus organization provisioning and cross-organization selection.

## Roles and authorization

- `superadmin` can create organizations and manage cities, branches, owners, branch admins, and customers across organizations.
- `owner` can manage cities, branches, branch admins, and customers only inside their authenticated organization.
- Owners cannot create another owner. Superadmins are never exposed in managed-user lists.
- `branch_admin` and `customer` do not receive management navigation or route access.
- Policies and Form Requests remain authoritative; client-side filtering is only interaction guidance.

## Management surfaces

- Organizations: tenant name, normalized slug, active state, branch count, and user count.
- Cities and branches: organization-scoped city selection, outlet details, coordinates, free-delivery radius, excess fee per kilometer, and active state.
- Users and access: organization, role, and one-or-more branch assignments for branch administrators.
- Tables are used for wide comparison and collapse into record cards on compact screens.

## Routes

- `GET|POST /cms/organizations`
- `GET|POST /cms/branches`
- `POST /cms/cities`
- `GET|POST /cms/users`

All routes require authenticated, verified web sessions. Frontend navigation uses generated Wayfinder helpers.

## Data integrity

- Organization slugs are normalized before uniqueness validation and persistence.
- Branch codes are uppercased before organization-scoped uniqueness validation and persistence.
- City and branch assignments must belong to the selected organization.
- Non-branch-admin roles cannot carry branch assignments; branch admins require at least one branch.
- Mutations use dedicated Form Requests, typed DTO/data objects, Action classes, policies, Spatie Permission, and Spatie Activitylog.

## Optimistic behavior

- Create forms insert a pending record immediately and disable duplicate submission while processing.
- Validation and network failures automatically roll back the pending record while preserving editable form state.
- Successful Inertia responses replace pending data with canonical server identifiers and normalized values.
- Pending cities are excluded from branch selection until the server confirms them.
- Organization and role changes clear selections that are no longer valid.

## Tests

`tests/Feature/Cms/TenancyManagementTest.php` covers tenant-scoped lists and creates, normalized uniqueness errors, branch ownership validation, branch-admin assignment requirements, owner role restrictions, superadmin capabilities, role-aware navigation, and customer denial.

## Current limitations

- Phase 1 provides create and list workflows; edit, deactivate, and delete workflows remain future CMS work.
- Audit-log browsing is not yet exposed as a dedicated screen.
- Machine capacity and transactional slot locking are not implemented.
