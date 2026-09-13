# Phase 2: Payments and Receipts

Status: In progress

## Outcome

Branch admins can accept a dynamic QRIS payment, record its state, and print or share a consistent receipt.

## Scope

- Dynamic QRIS amount insertion and CRC16-CCITT recalculation.
- Strict QRIS payload tests, including malformed payload rejection.
- Scannable 360 px SVG QR images exposed as base64 data URIs on payment resources.
- Payment state and audit history.
- ESC/POS receipt rendering.
- Bluetooth SPP and USB printer profiles through a Capacitor transport boundary.
- Printer discovery, connection, test print, and failure recovery.

## Delivered API contract

QRIS payment resources expose both `qris_payload` and `qris_image_data_uri`. The image field is a server-rendered SVG data URI and is `null` for non-QRIS payments. Payment creation and reconciliation responses share this resource contract.

## Verification

- Pest covers dynamic payload creation, SVG image serialization, cash nullability, reconciliation, authorization, and invalid state transitions.
- Browser and Android device end-to-end checks remain deferred to the final release gate.

## Constraints

- Production provider callbacks require the chosen acquirer's contract.
- Printer certification requires representative Android devices and printer models.
