# Owner management console

Phase: 1

## User outcome

Owners can manage their organization’s branches, users, roles, and branch assignments from a responsive web CMS. They can also add missing entries to the shared city catalog. Superadmins receive the same tenant-management tools plus organization provisioning, cross-organization selection, and city-catalog governance.

## Roles and authorization

- `superadmin` can create organizations, edit or delete global cities, and manage branches, owners, branch admins, and customers across organizations.
- `owner` can create global cities and manage branches, branch admins, and customers only inside their authenticated organization.
- Owners cannot create another owner. Superadmins are never exposed in managed-user lists.
- `branch_admin` receives scoped read access to the CMS Order Queue (`/cms/orders`) for assigned branches; tenant, branch, user, and catalog mutation routes remain restricted to `owner` and `superadmin`.
- `customer` does not receive management navigation or route access.
- Policies and Form Requests remain authoritative; client-side filtering is only interaction guidance.

## Management surfaces

- Organizations: tenant name, normalized slug, active state, branch count, and user count.
- Cities and branches: globally shared city selection, outlet details, map-picked or keyboard-entered coordinates, free-delivery radius, excess fee per kilometer, and active state.
- Order queue (`/cms/orders`): centralized operational queue featuring metric cards (total orders, unpriced, in progress, ready for pickup, completed), multi-criteria filters (organization for superadmin, branch, status, customer/order search), and full order detail dialogs (`OrderDetailDialog`) detailing items, per-kg calculations, status history, and payment events.
- The branch dialog exposes all confirmed global cities regardless of organization. Owners may add cities, while city edit and delete controls appear only for superadmins.
- Users and access: organization, role, and one-or-more branch assignments for branch administrators.
- Tables are used for wide comparison and collapse into record cards on compact screens.
- Create and edit actions open focused dialogs. Delete opens a separate destructive dialog and requires an exact typed record value.

## Routes

- `GET|POST /cms/organizations` and `PATCH|DELETE /cms/organizations/{organization}`
- `GET|POST /cms/branches` and `PATCH|DELETE /cms/branches/{branch}`
- `POST /cms/cities` and `PATCH|DELETE /cms/cities/{city}`
- `GET|POST /cms/users` and `PATCH|DELETE /cms/users/{user}`
- `GET /cms/orders`

All routes require authenticated, verified web sessions. Frontend navigation uses generated Wayfinder helpers.

## Data integrity

- Organization slugs are normalized before uniqueness validation and persistence.
- Branch codes are uppercased before organization-scoped uniqueness validation and persistence.
- City names are globally unique within their province and do not belong to an organization.
- Any organization may assign any global city to its branch. Deleting an organization does not delete cities, and a city cannot be deleted while a branch references it.
- Demo data provides Jakarta Selatan and Bandung in the shared catalog so multi-city branch setup can be exercised.
- Non-branch-admin roles cannot carry branch assignments; branch admins require at least one branch.
- Mutations use dedicated Form Requests, typed DTO/data objects, Action classes, policies, Spatie Permission, and Spatie Activitylog.

## Optimistic behavior

- Create forms insert a pending record and edit forms replace the affected record immediately while disabling duplicate submission.
- Validation and network failures automatically roll back the pending record while preserving editable form state.
- Successful Inertia responses replace pending data with canonical server identifiers and normalized values.
- Pending cities are excluded from branch selection until the server confirms them.
- Organization changes preserve valid global city selections. Role changes clear branch assignments that are no longer valid.
- Delete dialogs close only after a successful response and keep dependency or confirmation errors visible.

## Tests

`tests/Feature/Cms/TenancyManagementTest.php` and `tests/Feature/Cms/TenantCrudTest.php` cover tenant-scoped resources, global city listing and uniqueness, cross-organization city assignment, owner create-only city access, superadmin city governance, deletion guards, exact confirmation, branch-admin assignment requirements, nullable-tenant customers, role-aware navigation, and customer denial. Vue component tests cover global city selection, modal prefill, typed destructive confirmation, validation live regions, geolocation selection, and keyboard coordinate entry.

## Current limitations

- Audit-log browsing is not yet exposed as a dedicated screen.
- Machine capacity and transactional slot locking are not implemented.
