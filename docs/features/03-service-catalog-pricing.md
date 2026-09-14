# Service catalog and weight pricing

Phase: 1

## Scope

Organizations own reusable laundry services. Each branch attaches its own active price options to those services, allowing the same service to have different tariffs by location. Public catalog responses expose only active organizations, branches, services, and branch-specific options.

## Pricing rules

- Weight is stored and submitted as integer grams.
- Money is stored and returned as integer rupiah.
- Per-kilogram pricing is proportional by gram and can define a minimum chargeable weight.
- Fixed packages charge the base price up to the package weight. Excess weight is proportional by gram using the configured excess per-kilogram tariff.
- An overweight package without an excess tariff is rejected with `PACKAGE_EXCESS_RATE_REQUIRED`; it is never silently charged at the base price.
- Fractional rupiah uses deterministic integer half-up rounding: `intdiv((grams × rupiah_per_kg) + 500, 1000)`.

## Authorization

Owners can create services and branch prices only for their organization. Superadmins can target any organization. Branch admins and customers cannot mutate the catalog. Public reads and quotes remain tenant-scoped, and mismatched or inactive nested records return not found.

## Routes

Management routes require authenticated, verified web sessions:

- `GET /cms/services`
- `POST /cms/services`
- `PATCH /cms/services/{service}`
- `DELETE /cms/services/{service}`
- `POST /cms/services/{service}/price-options`
- `PATCH /cms/price-options/{price-option}`
- `DELETE /cms/price-options/{price-option}`

The CMS exposes modal create/edit flows and typed deletion confirmation. Owners only see their own organization, while superadmins can filter and manage every organization. Package branch choices are restricted to the service organization. Weight inputs are shown in kilograms and converted to integer grams before persistence. Services or packages referenced by order history cannot be deleted; they can be deactivated instead.

Public API routes:

- `GET /api/v1/organizations/{organization-slug}/branches/{branch}/catalog`
- `POST /api/v1/organizations/{organization-slug}/branches/{branch}/catalog/{price-option}/quote`

The quote body contains `weight_grams`. Its canonical response includes requested and chargeable weight, base and excess components, and total rupiah. This calculation object is ready to be copied into an immutable order snapshot in the next feature.

## Tests

- Fixed package base and excess calculations.
- Rejection when an overweight package has no excess tariff.
- Per-kilogram proportional calculation, half-up rounding, and minimum chargeable weight.
- Type-specific management validation.
- Owner/superadmin permissions, branch admin/customer denial, and tenant isolation.
- Modal package input conversion from kilograms to grams and organization-safe branch filtering.
- Public active-state filtering and quote boundary validation.
