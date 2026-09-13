# Multi-branch tenancy and access

Phase: 1

## Scope

Organizations are the tenant boundary. Cities, branches, owners, and branch admins belong to one organization. Customers remain organization-neutral so an account can receive orders from different laundry businesses.

## Authorization

- `superadmin`: platform-wide organization, branch, and user management.
- `owner`: branch and user management limited to their organization.
- `branch_admin`: assigned through the `branch_user` pivot and receives order permissions.
- `customer`: no CMS management permissions.

Spatie Permission owns roles and permissions. Laravel policies enforce model-level tenant checks. Request validation also scopes foreign keys to the actor's organization to prevent cross-tenant assignment.

## Routes

- `GET|POST /cms/organizations`
- `GET|POST /cms/branches`
- `POST /cms/cities`
- `GET|POST /cms/users`

All routes require authenticated and verified web sessions.

## Media and audit

Branches expose a single-file `logo` media collection through Spatie Media Library. Organization, city, and branch mutations are recorded by Spatie Activitylog; user role and branch assignment is logged under the `access` channel.

## Tests

`tests/Feature/Cms/TenancyManagementTest.php` covers superadmin organization creation, owner-scoped city and branch creation, rejected cross-tenant branch assignment, and customer denial.
