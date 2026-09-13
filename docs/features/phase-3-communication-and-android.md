# Phase 3: Communication and Android

Status: Delivered

## Outcome

Owners can manage tenant-safe WhatsApp templates, branch admins receive an explicit WhatsApp suggestion after a successful order transition, and the Capacitor Android shell opens only validated `https://wa.me` links.

## Scope

- Organization-level and branch-level message templates.
- Modal CMS create/edit workflows with optimistic reconciliation and typed deletion confirmation.
- Customer WhatsApp phone storage and immutable order phone snapshots.
- Supported variables with insertion-at-cursor, live preview, and server validation.
- URL-safe `wa.me` generation after successful order status changes.
- Explicit preview and open actions without silent background sending.
- A native Capacitor external-link bridge restricted to the exact `https://wa.me` origin.
- LaundryHub launcher icons, adaptive icon resources, splash assets, and an Android release guide.

## Roles and authorization

- Owners manage templates only for their organization.
- Superadmins manage templates for any organization.
- Branch admins can request suggestions only for orders in assigned branches after an authorized transition.
- Customers do not manage templates or trigger staff communication actions.
- Template policies, nested route binding, and branch assignment checks prevent cross-tenant access.

## Template rules

- A template belongs to one organization and optionally one branch.
- An active branch template overrides the active organization template for the same order status.
- Only one template may exist for a scope and status combination.
- Supported variables are `{customer_name}`, `{name}`, `{order_number}`, `{status}`, `{branch_name}`, and `{tracking_url}`.
- Unknown variables and malformed braces are rejected by a dedicated validation rule.
- Deletion requires the exact value `{scope_key}:{order_status}`, for example `branch:12:ready_for_pickup`.
- Indonesian mobile numbers are normalized to the `62` country-code form. Invalid or missing numbers return an unavailable suggestion instead of a broken link.

## Data contract

- `users.whatsapp_phone` stores the current normalized customer contact.
- `orders.customer_whatsapp_phone_snapshot` preserves the destination used by that order even if the user profile changes later.
- Suggestions return a discriminated available/unavailable resource. Available responses include the normalized phone, rendered message, template identifier, and RFC 3986 encoded `wa.me` URL.
- Template creation, update, and deletion are recorded through Spatie Activitylog.

## Route surface

- `GET /cms/message-templates` renders the owner/superadmin management page.
- `POST /cms/message-templates` creates a scoped template.
- `PATCH /cms/message-templates/{messageTemplate}` updates a scoped template.
- `DELETE /cms/message-templates/{messageTemplate}` deletes a template after typed confirmation.
- `GET /api/v1/organizations/{organization}/branches/{branch}/orders/{order}/whatsapp-suggestion` returns the current status suggestion.

## Mobile interaction contract

- Status transitions update optimistically and roll back to the canonical order when the API rejects the transition.
- The suggestion request runs only after the transition succeeds. A failed or rolled-back transition never requests or opens WhatsApp.
- The preview dialog shows the exact destination and message. WhatsApp opens only after the branch admin presses `Buka WhatsApp`.
- The latest successful suggestion can be reopened from the order workspace. Switching tenant/order data clears stale suggestions.
- Browser mode delegates to `window.open` only after an explicit action. Native mode delegates to the Capacitor plugin, which rejects HTTP, subdomains, credentials, custom ports, and non-`wa.me` hosts.

## Verification

- Pest covers tenant authorization, scope precedence, variable validation, deletion confirmation, phone normalization, immutable snapshots, unavailable suggestions, and URL encoding.
- Vitest covers CMS template prefill and variable insertion, optimistic transition success/rollback, stale suggestion clearing, dialog behavior, browser delegation, native delegation, and the external-link allowlist.
- Strict TypeScript, PHPStan, Pint, Biome, production bundling, and Capacitor sync are release gates.
- Android launcher and splash assets were inspected directly at generated resource sizes. Browser end-to-end, emulator, and Gradle/device builds remain final release checks.

## Constraints

- This phase does not automate WhatsApp delivery or use unofficial WhatsApp APIs.
- No offline synchronization is introduced.
- The native allowlist intentionally supports only `https://wa.me`; other external links require a separate reviewed capability.
- A physical Android release still requires a private signing key, representative device checks, and printer certification.
