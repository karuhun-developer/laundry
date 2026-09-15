# Push Notifications and Mobile Sessions

Status: Delivered

Last updated: 2026-09-15

## User outcome

LaundryHub Android users can enable Firebase Cloud Messaging from the Account tab. Branch admins receive new pickup requests for their assigned branch, while customers receive pricing confirmation and order-status updates. A notification received in the foreground or opened by the user refreshes the canonical `/app` workspace.

The web session idle lifetime is 30 days so Android users are not forced to sign in every day. Explicit logout still ends the session, and customer PIN changes or administrative PIN resets invalidate older customer sessions through `session_version`.

## Firebase project

- Project ID: `laundry-695f6`
- Project number: `13736909826`
- Android application ID: `com.karuhundeveloper.laundryhub`
- Notification channel: `laundry_updates`

Two different Firebase credentials are required:

1. The Laravel server uses a Firebase Admin SDK service-account JSON file. Keep it outside the repository and configure its absolute path through `FIREBASE_CREDENTIALS`.
2. The Android build uses the Firebase Android `google-services.json` for `com.karuhundeveloper.laundryhub`. Never commit it. Supply it to CI as the Base64 secret `ANDROID_GOOGLE_SERVICES_JSON_BASE64`.

The service-account JSON does not replace `google-services.json`. Download the Android file from the Firebase project settings after the Android application has been registered with the exact application ID.

## Server configuration

Set these values in the deployed Laravel environment:

```dotenv
SESSION_LIFETIME=43200
FIREBASE_PROJECT_ID=laundry-695f6
FIREBASE_CREDENTIALS=/secure/path/laundry-695f6-firebase-adminsdk.json
QUEUE_CONNECTION=database
```

`SESSION_LIFETIME=43200` means 43,200 minutes, or 30 days of inactivity. It is an idle timeout rather than a guaranteed fixed 30-day login: each request refreshes normal session activity, logout ends the session immediately, and a customer session whose recorded version is stale receives `401` or is redirected to login.

The credential path must be readable by the PHP and queue-worker users. Do not place its JSON content in `.env`, application logs, documentation, or the repository. After changing environment values, clear cached configuration and restart long-running workers.

```bash
php artisan config:clear
php artisan queue:restart
php artisan queue:work --tries=3
```

The queue worker is required because order notifications implement `ShouldQueue`. If Firebase configuration is absent, the notification job exits without sending and the original order mutation still succeeds.

## Android CI configuration

Encode the Firebase Android configuration without committing it:

```bash
base64 -w 0 google-services.json
```

On macOS:

```bash
base64 < google-services.json | tr -d '\n'
```

Save the one-line result as the `ANDROID_GOOGLE_SERVICES_JSON_BASE64` secret in the `android-release` GitHub Actions environment. The Android release workflow decodes it into `android/app/google-services.json` before the Capacitor build and removes it in the cleanup step. Android builds and Capacitor sync belong to CI/CD for this repository.

## API and data contract

Both endpoints require an authenticated Sanctum session and use Wayfinder-generated frontend routes:

- `POST /api/v1/mobile/push-notifications/devices` with `token` and `platform` (`android` or `ios`) registers or reassigns a device.
- `DELETE /api/v1/mobile/push-notifications/devices` with `token` removes only the authenticated user's matching registration.

Registration tokens are encrypted at rest. A SHA-256 hash provides a non-secret stable lookup key, and API resources expose only the registration ID, platform, and `last_seen_at`. The raw token is never returned by the API or written to activity logs. Firebase `UNREGISTERED` and not-found responses remove the stale device record.

## Notification routing

| Event | Recipient | Trigger |
| --- | --- | --- |
| Pickup requested | Branch admins assigned to the order branch | Customer creates a pickup order |
| Pricing confirmed | Order customer | Branch staff confirms measured weight and price |
| Status updated | Order customer | Branch staff advances the laundry status |

Staff counter orders do not emit a pickup-request notification. Recipient queries are constrained to the assigned branch or exact customer identity, so another branch admin or customer does not receive the event.

## Client behavior

- Notification settings appear only in the native application.
- Already-granted permission registers automatically when `/app` mounts. Prompt permission is requested only after the user selects `Aktifkan notifikasi`.
- Permission denial and registration errors remain recoverable in Account and do not block the workspace.
- Foreground delivery shows a toast and refreshes workspace data. Tapping a system notification opens `/app` and refreshes it.
- Logout asks the API to remove the current token, calls native unregister even if the API is temporarily unreachable, and then posts the normal logout request.

## Operational validation

1. Confirm Laravel reports `SESSION_LIFETIME=43200` and the Firebase service as configured.
2. Start a queue worker and sign in on a physical Android device.
3. Enable notifications from Account and confirm one encrypted `push_notification_devices` record is created without exposing the token in the response.
4. Request pickup as a customer and confirm only admins assigned to that branch receive it.
5. Confirm measured pricing and advance order status, then confirm only that order's customer receives the notifications.
6. Tap a notification and verify `/app` shows refreshed canonical data.
7. Log out and confirm the device registration is removed.

Automated coverage lives in `tests/Feature/PushNotificationDeviceTest.php`, `tests/Feature/OrderPushNotificationTest.php`, `resources/js/composables/usePushNotifications.test.ts`, `resources/js/components/mobile/MobileAccount.test.ts`, and `resources/js/pages/mobile/App.test.ts`. Physical-device delivery still requires the Android `google-services.json`, a CI-built application, notification permission, network access, and a running Laravel queue worker.
