# Phase 2: Payments and Receipts

Status: Delivered

## Outcome

Branch admins can record cash, present a dynamic QRIS payment, reconcile its state, and print a canonical receipt from the Android application.

## Scope

- Dynamic QRIS amount insertion and CRC16-CCITT recalculation.
- Strict QRIS payload tests, including malformed payload rejection.
- Scannable 360 px SVG QR images exposed as base64 data URIs on payment resources.
- Payment state and audit history.
- Optional Paywuz integration for direct QRIS and direct bank virtual-account methods.
- ESC/POS receipt rendering.
- Bluetooth SPP and USB printer profiles through a Capacitor transport boundary.
- Printer discovery, connection, test print, and failure recovery.

## Roles and authorization

- Branch admins can create payments, reconcile pending payments, share payment details to customer WhatsApp, load receipts, and print orders in assigned branches.
- Owners and superadmins retain API visibility within their policy scope, while the mobile payment controls remain optimized for branch operations.
- Customers receive paid and outstanding totals through their canonical order resource but cannot mutate payment state.
- Nested organization, branch, order, and payment bindings prevent cross-tenant or cross-branch access.

## Payment rules

- Cash payments are recorded as paid immediately; QRIS payments begin as pending and retain the generated raw payload and SVG data URI.
- Paywuz is optional. Cash and branch-managed QRIS remain available when the gateway is disabled or its credentials are incomplete.
- Paywuz method codes must select QRIS or a bank virtual account directly; provider meta-methods `VA` and `ALL` are rejected.
- Provider-managed payment states can only be reconciled by an HMAC-signed, idempotent Paywuz webhook. Branch admins cannot mark these payments manually.
- Full and partial payments are accepted only up to the current outstanding amount.
- Paid totals include only payments in the paid state. Failed, cancelled, and refunded records remain in history without reducing the outstanding amount.
- QRIS reconciliation appends an audited status event. Invalid or stale transitions return a conflict instead of overwriting the canonical state.
- The mobile client applies payment mutations optimistically. Any failure restores the complete previous order and summary; conflicts fetch a fresh canonical order before another mutation.

## API surface

- `POST /api/v1/organizations/{organization}/branches/{branch}/orders/{order}/payments` records cash or creates a dynamic QRIS payment.
- `POST /api/v1/organizations/{organization}/branches/{branch}/orders/{order}/payments/{payment}/status-events` reconciles an allowed payment transition.
- `GET /api/v1/organizations/{organization}/branches/{branch}/orders/{order}/receipt` returns the canonical digital receipt snapshot.
- `GET /api/v1/payment-gateway-methods` lists configured direct gateway methods.
- `POST /api/v1/payment-gateways/paywuz/webhook` accepts Paywuz callbacks after signature, event, delivery, payment identity, method, and amount verification.
- Order resources include payment history, paid/outstanding summary, and branch QRIS capability.

## Delivered API contract

QRIS payment resources expose both `qris_payload` and `qris_image_data_uri`. The image field is a server-rendered SVG data URI and is `null` for non-QRIS payments. Payment creation and reconciliation responses share this resource contract.

Paywuz QRIS values use the same scannable QR presentation and can be downloaded from the app. Direct virtual-account payments expose a copyable payment number. The method picker uses locally stored QRIS and bank logos, with a generic card icon when the provider returns an unmapped code.

The Android printer boundary supports Bluetooth SPP and USB device discovery. A saved profile contains connection type, native device identifier, display name, and paper width. The Vue layer sends encoded ESC/POS bytes through the Capacitor plugin and never implements an offline order cache.

## Mobile interaction contract

- Branch-admin order tickets expose `Pembayaran & struk`, or `Lihat QRIS pembayaran` when a pending QRIS payment exists.
- Branch admins can share dynamic QRIS and gateway payment details directly to customer WhatsApp with a signed public order-tracking link.
- Pending QRIS takes precedence over payment entry so duplicate pending QR codes cannot be created.
- Async outcomes are announced in a polite live region and controls remain disabled during the active mutation.
- The digital receipt is fetched on demand before rendering or printing.
- Printer configuration lives in the branch-admin account surface and remains local to that device.

## Verification

- Pest covers dynamic payload creation, SVG image serialization, cash nullability, reconciliation, authorization, and invalid state transitions.
- Vitest covers optimistic payment insertion, success reconciliation, rollback, conflict refresh failure, QRIS rendering, receipt actions, printer discovery, profile persistence, and print dispatch.
- Strict Vue TypeScript checks, production bundling, PHPStan, and Capacitor sync are release gates.
- Browser and Android device end-to-end checks remain deferred to the final release gate.

## Constraints

- Paywuz secrets are supplied only through environment variables and must never be committed. Configure `PAYWUZ_ENABLED`, `PAYWUZ_API_KEY`, `PAYWUZ_BASE_URL`, `PAYWUZ_CONNECT_TIMEOUT_SECONDS`, `PAYWUZ_TIMEOUT_SECONDS`, and `PAYWUZ_METHOD_CACHE_SECONDS` in the deployment environment.
- Printer certification requires representative Android devices and printer models.
- Printer profiles intentionally use device-local storage and do not synchronize between users or devices.
