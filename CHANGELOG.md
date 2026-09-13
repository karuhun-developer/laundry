# Changelog

All notable changes to LaundryHub are documented here. The project follows phase-based delivery and uses atomic Git commits for each feature or independently reversible update.

## [Unreleased]

### Phase 1: Core operations

- Added the initial product requirements, delivery roadmap, and design-system contract.
- Added organization-scoped cities, branches, users, roles, permissions, audit logs, and branch media collections.
- Added organization-scoped nearest-branch discovery using Haversine distance, configurable free-delivery radii, and per-started-kilometer excess fees.
- Added organization-owned service catalogs, branch-specific fixed-package and per-kilogram prices, and deterministic gram-based quote APIs.
- Added authenticated order creation for customers and authorized staff with tenant isolation, immutable catalog and delivery snapshots, initial status history, and audited server-calculated totals.

## Release policy

- `Added` for new behavior.
- `Changed` for behavior changes.
- `Fixed` for defect corrections.
- `Security` for authorization, privacy, and dependency security changes.
