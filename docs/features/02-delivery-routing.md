# Nearest-branch delivery routing

Phase: 1

## Scope

Each branch can store coordinates, a free pickup/delivery radius, and a rupiah tariff for every started kilometer beyond that radius. Customers can query the nearest active branches inside one organization before creating an order.

The initial implementation calculates Haversine distance in PHP. This keeps development on SQLite behaviorally compatible with MySQL and avoids binding the API contract to a database-specific spatial extension.

## Fee calculation

1. Calculate the great-circle distance from the branch to the customer in integer meters.
2. Subtract the branch's free-delivery radius.
3. If the result is zero or negative, the delivery fee is zero.
4. Otherwise, divide the excess meters by 1,000, round up, and multiply by the branch's rupiah-per-kilometer tariff.

Example: a customer 11.12 km from a branch with a 10 km free radius and a Rp3.000/km excess tariff pays `ceil(1.12) × Rp3.000 = Rp6.000`.

## API

`GET /api/v1/organizations/{organization-slug}/branches/nearest`

Query parameters:

- `latitude`: required, -90 through 90.
- `longitude`: required, -180 through 180.
- `limit`: optional, 1 through 20; defaults to 5.

The public response is organization-scoped and includes branch identity, city, coordinates, distance in meters, whether the customer is inside the free radius, the configured tariff, and the calculated fee in integer rupiah. Results are sorted nearest first.

Inactive organizations, inactive branches, branches from other organizations, and branches without complete coordinates are not exposed.

## Administration

Branch creation accepts paired latitude/longitude values, `free_delivery_radius_meters`, and `delivery_fee_per_km_rupiah`. Coordinates are optional, but automatic routing ignores a branch until both are configured. The default free radius is 10 km and the default excess tariff is Rp0 until the owner configures it.

## Tests

- Known-point Haversine distance in integer meters.
- Free pickup/delivery inside the configured radius.
- Per-started-kilometer rounding beyond the free radius.
- Nearest-first API ordering and result limits.
- Tenant, active-state, and coordinate filtering.
- Coordinate boundary validation.

## Deferred capacity routing

Machine-ready routing and slot locking are intentionally deferred. Correct implementation requires machine inventory, service compatibility, cycle duration, maintenance state, reservation expiry, and transactional concurrency rules. Those models will be designed as a separate feature instead of guessing availability from branch activity.
