# Demo Role Authentication

Status: Implemented

## Outcome

Development environments provide deterministic login accounts for every supported role. The login screen can fill an account with one tap so customer, branch-admin, owner, and superadmin flows can be reviewed without looking through seed code.

## Accounts

All demo accounts use the password `password`.

| Role | Email | Scope |
| --- | --- | --- |
| Customer | `customer@laundryhub.test` | Customer mobile experience |
| Branch admin | `branch@laundryhub.test` | Cabang Kemang |
| Owner | `owner@laundryhub.test` | LaundryHub Demo organization |
| Superadmin | `superadmin@laundryhub.test` | Platform-wide |

## Safety and repeatability

- `DatabaseSeeder` calls an idempotent demo-data seeder.
- Existing demo rows are updated by stable natural keys instead of duplicated.
- Demo account shortcuts are controlled by `DEMO_ACCOUNTS_ENABLED` and default to enabled only in the local environment.
- Passwords are stored through Laravel's hashed model cast.

## Verification

- Feature tests verify every password and assigned role.
- Branch-admin membership is verified against `KMG01`.
- Running the complete seeder twice produces one copy of each demo record.
- The Fortify login page exposes all four shortcuts only when demo accounts are enabled.
