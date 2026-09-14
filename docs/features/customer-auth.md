# Customer authentication

Status: Delivered

## User outcome

Customers register and sign in with their WhatsApp phone number and a six-digit PIN, while staff continue to use the separate email/password and passkey-capable flow. Entry destinations are resolved from the authenticated role rather than from one shared dashboard.

## Identity and normalization

- Customer phone numbers are global identities and are stored in Indonesian `62...` form.
- Formatting characters are removed before validation. An `08...` number becomes `628...`; `+62...` becomes `62...`; an existing `62...` value remains unchanged.
- The stored number must match `^628[1-9][0-9]{7,11}$`. It is globally unique.
- Public registration collects `name`, `whatsapp_phone`, `pin`, and `pin_confirmation`. It creates the customer role with nullable email/password credentials, no legacy `organization_id`, no organization membership, `must_change_pin` disabled, and an initial session version.

## Login modes and destinations

- `GET /login` renders the Inertia authentication page with separate customer and staff modes. Its SEO metadata is emitted by the Blade application shell without SSR.
- `POST /login/customer` authenticates a normalized phone number and six-digit PIN. Invalid customer credentials use a generic response.
- Fortify `POST /login` authenticates staff by email and password and rejects customer accounts. Passkey login is staff-only.
- `GET /register` and `POST /register` provide public customer registration without an organization selector.
- Customers and branch admins resolve to `/app`; owners and superadmins resolve to `/cms`. `/` and `/dashboard` are compatibility redirects that apply the same role decision. Logout returns to `/login`.

Frontend navigation and submissions use generated Wayfinder route functions rather than manually assembled URLs.

## Temporary and changed PINs

- A newly created customer may receive a random six-digit temporary PIN through the authorized branch-admin credential response. A reset produces the same kind of credential.
- A temporary PIN expires after 24 hours. After expiry it cannot authenticate, and before expiry the account is marked to change it. The Account tab displays the required-change notice and PIN form.
- `PUT /api/v1/mobile/account/pin` requires the current PIN, a different new six-digit PIN, and confirmation. Success clears the temporary expiry and mandatory-change marker.
- PIN change increments the customer's session version, invalidates other sessions, and updates the current session to the new version. An administrative reset increments the version and invalidates all existing customer sessions.

## Session and security boundaries

- Customer login stores the account session version. Web and stateful API middleware log out a customer when the stored version is absent or no longer matches the account.
- Customer login is limited to five failed attempts with a 60-second decay, keyed by normalized phone number and IP address. Staff Fortify login is limited to five attempts per minute, and passkey login to ten attempts per minute.
- PINs are hashed and are never stored or logged in plain text. Customer credential errors do not reveal whether a phone number exists.
- The login page is the only indexable application route. Blade emits its canonical, robots, Open Graph, Twitter, and WebApplication JSON-LD metadata; other application routes are noindex/nofollow and Inertia SSR remains disabled.

## API and implementation boundaries

- Fortify's `CreateNewUser` action validates public registration directly with Laravel's `Validator`; dedicated Form Requests validate customer login and PIN changes. Authentication and PIN mutations remain server-authoritative.
- Customer-only middleware enforces session-version freshness for both web and API traffic. Role middleware and policies remain authoritative for destination and action access.
- No demo customer credentials are repeated here; deployment-specific demo shortcuts remain controlled by existing environment configuration.

## Pengujian dan verifikasi

- Pest pada `tests/Feature/Auth/RegistrationTest.php` memverifikasi registrasi pelanggan, normalisasi dan duplikasi nomor, PIN enam digit, tujuan `/app`, serta ketiadaan keanggotaan organisasi saat registrasi publik. `tests/Feature/Auth/CustomerAuthenticationTest.php` mencakup pemisahan login pelanggan/staf, kredensial generik, throttling, passkey khusus staf, dan pengalihan berbasis peran.
- `tests/Feature/Api/V1/CustomerManagementTest.php` mengunci penerbitan PIN sementara satu kali dengan masa berlaku 24 jam, sedangkan `tests/Feature/Api/V1/UpdateCustomerPinTest.php` mencakup penggantian PIN wajib, kenaikan versi sesi, penghapusan sesi lain, serta penolakan sesi pelanggan dengan versi yang hilang atau tidak cocok.
- Vitest pada `resources/js/pages/auth/AuthPages.test.ts` memverifikasi mode login, formulir registrasi, dan rute Wayfinder; `resources/js/components/mobile/MobileAccount.test.ts` memverifikasi pemberitahuan PIN sementara wajib diganti, validasi enam digit, dan respons UI perubahan PIN.
- Perintah terfokus: `php artisan test --compact tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/CustomerAuthenticationTest.php tests/Feature/Api/V1/UpdateCustomerPinTest.php tests/Feature/Api/V1/CustomerManagementTest.php` dan `bun run test -- resources/js/pages/auth/AuthPages.test.ts resources/js/components/mobile/MobileAccount.test.ts`.
