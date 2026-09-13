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
- Cities and branches: organization-scoped city selection, outlet details, map-picked or keyboard-entered coordinates, free-delivery radius, excess fee per kilometer, and active state.
- The branch dialog explains that city choices follow the selected organization; superadmins can see each city's owner in the city list.
- Users and access: organization, role, and one-or-more branch assignments for branch administrators.
- Tables are used for wide comparison and collapse into record cards on compact screens.
- Create and edit actions open focused dialogs. Delete opens a separate destructive dialog and requires an exact typed record value.

## Routes

- `GET|POST /cms/organizations` and `PATCH|DELETE /cms/organizations/{organization}`
- `GET|POST /cms/branches` and `PATCH|DELETE /cms/branches/{branch}`
- `POST /cms/cities` and `PATCH|DELETE /cms/cities/{city}`
- `GET|POST /cms/users` and `PATCH|DELETE /cms/users/{user}`

All routes require authenticated, verified web sessions. Frontend navigation uses generated Wayfinder helpers.

## Data integrity

- Organization slugs are normalized before uniqueness validation and persistence.
- Branch codes are uppercased before organization-scoped uniqueness validation and persistence.
- City and branch assignments must belong to the selected organization.
- Demo data gives LaundryHub Demo both Jakarta Selatan and Bandung so multi-city branch setup can be exercised without crossing tenant boundaries.
- Non-branch-admin roles cannot carry branch assignments; branch admins require at least one branch.
- Mutations use dedicated Form Requests, typed DTO/data objects, Action classes, policies, Spatie Permission, and Spatie Activitylog.

## Optimistic behavior

- Create forms insert a pending record and edit forms replace the affected record immediately while disabling duplicate submission.
- Validation and network failures automatically roll back the pending record while preserving editable form state.
- Successful Inertia responses replace pending data with canonical server identifiers and normalized values.
- Pending cities are excluded from branch selection until the server confirms them.
- Organization and role changes clear selections that are no longer valid.
- Delete dialogs close only after a successful response and keep dependency or confirmation errors visible.

## Tests

`tests/Feature/Cms/TenancyManagementTest.php` and `tests/Feature/Cms/TenantCrudTest.php` cover tenant-scoped lists, creates, updates, deletion guards, exact confirmation, uniqueness errors, branch ownership validation, branch-admin assignment requirements, nullable-tenant customers, owner role restrictions, superadmin capabilities, role-aware navigation, and customer denial. Vue component tests cover modal prefill, typed destructive confirmation, validation live regions, geolocation selection, and keyboard coordinate entry.

## Current limitations

- Audit-log browsing is not yet exposed as a dedicated screen.
- Machine capacity and transactional slot locking are not implemented.
