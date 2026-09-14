# Customer branch location and nearest routing

Status: Delivered

## User outcome

After the customer workspace is ready, LaundryHub attempts to locate the customer, rank eligible branches nearest first, select the nearest branch, and load its catalog. Location failure never blocks manual pickup-point or branch selection, and branch-admin sessions do not trigger automatic geolocation.

## Platform contract

- The mobile application uses `@capacitor/geolocation` version `8.2.2`.
- Android declares `ACCESS_COARSE_LOCATION` and `ACCESS_FINE_LOCATION`, plus optional `android.hardware.location.gps` capability.
- Native detection uses `Capacitor.isNativePlatform()`. Native flow checks permissions, requests `location` permission when needed, accepts precise or coarse grants, and then calls the native current-position API.
- Browser flow uses `navigator.geolocation.getCurrentPosition`. Both seams request high accuracy with a 10-second timeout and 60-second maximum age.
- Missing APIs, denied permission, disabled GPS, timeout, invalid coordinates, and unavailable positions resolve as no automatic location. The explicit current-location control may show a recoverable inline error; automatic failure remains non-blocking.

## Automatic and manual flow

- Automatic discovery starts only for the customer role and only after workspace, context, organization, and catalog loading have completed. Branch admins never receive the automatic permission request.
- A valid position updates the shared pickup coordinates and requests ranked branches. The result is filtered to branches in the current workspace context and sorted by `distance_meters`.
- The nearest ranked branch is selected automatically and its catalog is reloaded. The pickup coordinates remain in workspace state and are submitted with the order.
- Workspace, nearest-branch, branch-selection, and catalog generations reject stale responses. A manual branch selection advances the selection generation, so a late automatic nearest result cannot overwrite the customer's choice.
- If automation fails, the customer can still use map click, draggable pin, latitude/longitude fields, `Gunakan lokasi saya`, `Hitung dari titik ini`, branch cards, and the branch dropdown.
- The Home shortcut `Lihat daftar cabang` opens the Branches tab.

## Ordering and delivery fee

- The nearest service uses Haversine distance and rounds the result to integer meters. Ranked branches are shown nearest first.
- `excessMeters = max(0, distance - freeRadius)`. Chargeable kilometers are `ceil(excessMeters / 1,000)`, and the delivery fee is that value multiplied by the configured rupiah-per-kilometer tariff. The fee is zero inside the free radius.
- Branch cards and options expose distance, free radius, per-kilometer tariff, free/outside-radius state, and the estimated excess fee. Branches without calculable coordinates remain manually selectable after ranked branches when present in the current context.

## API and client boundaries

- `GET /api/v1/mobile/branches/nearest` accepts customer latitude and longitude and returns eligible active branches with canonical distance, coverage, and fee values.
- Frontend route identities come from generated Wayfinder functions. Server-side tenant and role authorization, validation, Haversine calculation, sorting, and fee calculation remain authoritative.

## Pengujian dan verifikasi

- Pest pada `tests/Feature/Api/V1/NearestBranchTest.php` memverifikasi autentikasi pelanggan, cabang aktif lintas organisasi, validasi koordinat, urutan terdekat, dan quote ongkir. `tests/Unit/DeliveryRouteCalculatorTest.php` mengunci jarak Haversine, radius gratis, dan pembulatan kilometer berlebih; `tests/Unit/AndroidLocationPermissionsTest.php` memeriksa deklarasi coarse/fine location dan GPS opsional pada manifest.
- Vitest pada `resources/js/composables/useGeolocation.test.ts` mencakup seam native Capacitor, fallback browser, penolakan izin, GPS/timeout yang tidak tersedia, dan koordinat tidak valid. `resources/js/composables/useMobileWorkspace.test.ts` mencakup auto-selection khusus pelanggan setelah readiness, pengecualian branch admin, fallback manual, perlindungan pilihan manual dan respons workspace usang, pengurutan, reload katalog, serta label/fee. `resources/js/components/mobile/MobileBranchDiscovery.test.ts` memverifikasi kontrol titik manual dan presentasi cakupan, sedangkan `resources/js/pages/mobile/App.test.ts` mengunci shortcut Home `Lihat daftar cabang`.
- Perintah terfokus: `php artisan test --compact tests/Feature/Api/V1/NearestBranchTest.php tests/Unit/DeliveryRouteCalculatorTest.php tests/Unit/AndroidLocationPermissionsTest.php` dan `bun run test -- resources/js/composables/useGeolocation.test.ts resources/js/composables/useMobileWorkspace.test.ts resources/js/components/mobile/MobileBranchDiscovery.test.ts resources/js/pages/mobile/App.test.ts`.
- Kompilasi Android tidak diverifikasi secara lokal. APK/AAB bertanda tangan dibangun dan diverifikasi hanya oleh `.github/workflows/android-release.yml` ketika push tag versi semantik berpola `v[0-9]*.[0-9]*.[0-9]*` memicu CI/CD.

## Android release boundary

Android compilation is performed only by the semantic-version-tag-triggered release workflow. CI/CD installs dependencies, builds the frontend, syncs Capacitor, runs the configured quality gates, and then produces signed Gradle APK/AAB artifacts. Phase 6 documentation does not require or claim a local Android, Gradle, or APK build.
