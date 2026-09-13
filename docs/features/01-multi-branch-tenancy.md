# Multi-branch tenancy and access

Phase: 1

## Scope

Organizations are the tenant boundary for branches, owners, branch admins, services, and orders. Customers remain organization-neutral so an account can receive orders from different laundry businesses.
Cities are shared platform master data without organization ownership. Each name and province pair is globally unique, and every organization may assign any global city to a branch.

## Authorization

- `superadmin`: platform-wide organization, branch, and user management.
- `owner`: branch and user management limited to their organization.
- `branch_admin`: assigned through the `branch_user` pivot and receives order permissions.
- `customer`: no CMS management permissions.

Spatie Permission owns roles and permissions. Laravel policies enforce model-level tenant checks. Request validation scopes tenant-owned foreign keys to the actor's organization while accepting any existing global city for a branch.
Owners may list and create cities so a missing area does not block branch setup. Only superadmins may edit or delete a city because those changes affect every tenant, and deletion is rejected while any branch still references the city.

## Routes

- `GET|POST /cms/organizations` and `PATCH|DELETE /cms/organizations/{organization}`
- `GET|POST /cms/branches` and `PATCH|DELETE /cms/branches/{branch}`
- `POST /cms/cities` and `PATCH|DELETE /cms/cities/{city}`
- `GET|POST /cms/users` and `PATCH|DELETE /cms/users/{user}`

All routes require authenticated and verified web sessions.

## Media and audit

Branches expose a single-file `logo` media collection through Spatie Media Library. Organization, city, and branch mutations are recorded by Spatie Activitylog; user role and branch assignment is logged under the `access` channel.

## Tests

`tests/Feature/Cms/TenancyManagementTest.php` and `tests/Feature/Cms/TenantCrudTest.php` cover tenant-scoped resources, globally shared city lists and uniqueness, cross-organization city assignment, owner create-only city access, superadmin city governance, protected deletion, exact confirmation, dependency conflicts, nullable-tenant customers, role boundaries, and cross-tenant denial.
