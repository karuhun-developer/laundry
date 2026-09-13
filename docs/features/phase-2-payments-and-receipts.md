# Phase 2: Payments and Receipts

Status: Planned

## Outcome

Branch admins can accept a dynamic QRIS payment, record its state, and print or share a consistent receipt.

## Scope

- Dynamic QRIS amount insertion and CRC16-CCITT recalculation.
- Strict QRIS payload tests, including malformed payload rejection.
- Payment state and audit history.
- ESC/POS receipt rendering.
- Bluetooth SPP and USB printer profiles through a Capacitor transport boundary.
- Printer discovery, connection, test print, and failure recovery.

## Constraints

- Production provider callbacks require the chosen acquirer's contract.
- Printer certification requires representative Android devices and printer models.
