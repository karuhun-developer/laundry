# Branch-scoped customer management

Status: Delivered

## User outcome

Branch administrators manage customers from the Customers tab without gaining access to another organization's directory or rewriting a profile shared with another organization. Customer accounts remain global identities, while organization membership defines directory visibility and operational association.

## Identity and membership rules

- A normalized WhatsApp phone number uniquely identifies one global customer account. A customer may have memberships in multiple organizations through `customer_organization`.
- Legacy customer associations were backfilled from the previous organization field and existing order history. New public registration does not choose or create a membership.
- Creating with a new phone creates the global customer, assigns the customer role, attaches the current organization, and returns a one-time temporary PIN with its expiry.
- Creating with a phone that already belongs to a customer-only account attaches the current organization without changing the existing name, phone, PIN, or sessions and returns no credential.
- A phone belonging to a staff or mixed-role account cannot be linked as a customer.

## Authorization and mutation boundaries

- Only a branch admin with an organization may use the directory mutations. The directory and mutation scope is always the actor's current organization. Cross-scope and protected shared-profile cases are denied as not found.
- Update accepts only `name` and/or `whatsapp_phone`. The phone is normalized and must remain globally unique. Profile updates require the customer to belong to the actor's organization and to have exactly one organization membership.
- PIN reset uses the same single-membership boundary. It creates a random six-digit temporary PIN valid for 24 hours, marks the customer to change it, and invalidates existing customer sessions.
- Detach removes only the current organization membership; it never deletes the global user. A shared customer may be detached, but detach is denied when the customer has order history in that organization.

## Routes and server structure

- `GET /api/v1/organizations/{organization:slug}/customers` lists the current organization's customer memberships.
- `POST /api/v1/mobile/customers` creates a customer or links an existing customer identity.
- `PATCH /api/v1/mobile/customers/{customer}` updates an eligible single-membership profile.
- `POST /api/v1/mobile/customers/{customer}/reset-pin` issues a one-time temporary credential for an eligible customer.
- `DELETE /api/v1/mobile/customers/{customer}` detaches the current organization membership when allowed.

The frontend imports Wayfinder-generated functions from `@/routes/` and submits them through Inertia's HTTP client. It does not construct endpoint URLs manually. Update, reset, and detach routes use global implicit `{customer}` binding rather than scoped binding; create/link has no customer route parameter. Dedicated Form Requests and policies authorize requests, while organization-scoped Actions enforce membership and mutation boundaries. `CreateOrAttachCustomer`, `UpdateCustomer`, `ResetCustomerPin`, and `DetachCustomer` own the use cases; `CustomerResource` and `CustomerCredentialResource` own response shapes.

## UI flows

- The Customers tab renders scoped directory cards with active or temporary-PIN status.
- Create and edit share a focused dialog. Create inserts a visible pending record, then reconciles the canonical resource or rolls back while preserving recoverable form state.
- A newly created customer opens a one-time credential dialog with copy action, expiry guidance, and a warning that the PIN will not be shown again. Linking an existing identity does not open this credential flow.
- Reset uses a confirmation dialog and displays the returned PIN once. Detach requires the customer's typed name before the destructive action is enabled.
- Operation generations prevent stale update, reset, or detach responses from replacing newer directory state. Create and link instead reconcile by their temporary optimistic identifier and roll back that pending record on failure.

## Pengujian dan verifikasi

- Pest pada `tests/Feature/Api/V1/CustomerManagementTest.php` memverifikasi daftar dan CRUD branch admin yang dibatasi organisasi, isolasi lintas tenant, pembuatan pelanggan dengan PIN sementara satu kali, pengaitan nomor pelanggan yang sudah ada tanpa mengubah profil, batas update/reset untuk identitas bersama, detach tanpa menghapus akun global, dan penolakan detach saat ada riwayat pesanan pada organisasi aktif.
- Vitest pada `resources/js/components/mobile/CustomerManagement.test.ts` mencakup dialog create/edit/reset/kredensial, error yang dapat diakses, PIN yang hanya ditampilkan sekali, serta konfirmasi detach dengan nama persis. `resources/js/composables/useMobileCustomerWorkspace.test.ts` mencakup rekonsiliasi create/link, rollback optimistic create, respons update/reset otoritatif, perlindungan respons update usang, dan pemulihan daftar ketika detach ditolak.
- Perintah terfokus: `php artisan test --compact tests/Feature/Api/V1/CustomerManagementTest.php` dan `bun run test -- resources/js/components/mobile/CustomerManagement.test.ts resources/js/composables/useMobileCustomerWorkspace.test.ts`.

## Auditing and privacy

- Customer creation, attachment, profile update, PIN reset, and detachment are written to the `customers` activity log.
- Audit properties may identify the customer, organization, changed fields, credential expiry, and session invalidation. The temporary PIN itself is never logged.
- One-time credential responses are not recoverable from the directory after the result dialog closes.
