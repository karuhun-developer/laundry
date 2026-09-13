# Android Release Guide

LaundryHub uses Capacitor with application ID `com.karuhundeveloper.laundryhub`, Android API 24 minimum, and API 36 compile/target settings. The repository contains branded launcher, adaptive-icon, and splash resources.

## Prerequisites

- PHP dependencies and frontend dependencies installed.
- Java 21 available.
- Android SDK 36 and accepted Android licenses.
- A private upload keystore stored outside this repository. Never commit the keystore or its passwords.
- Production Laravel API available over HTTPS.

## Prepare the web bundle

Set the production application URL and build the web application before syncing Capacitor:

```bash
APP_URL=https://app.example.com bun run build
bun run android:sync
```

For a hosted web build instead of bundled assets, set `CAPACITOR_SERVER_URL` to the production HTTPS origin during sync. Cleartext HTTP is intended only for controlled local development.

## Versioning

Before every release, increment both values in `android/app/build.gradle`:

- `versionCode`: monotonically increasing integer required by Android stores.
- `versionName`: user-facing semantic release label.

## Signing

Create an upload key outside the repository if one does not already exist:

```bash
keytool -genkeypair -v -keystore /secure/path/laundryhub-upload.jks -alias laundryhub -keyalg RSA -keysize 4096 -validity 10000
```

Use Android Studio's **Build > Generate Signed App Bundle or APK** flow, select the external keystore, and generate an Android App Bundle for store distribution. Keep the keystore, alias, and passwords in the team's secret manager.

For CI, inject signing values as protected environment secrets and configure Gradle signing without writing credentials into tracked files. Release artifacts and keystores must stay ignored.

## Release verification

Run the repository quality gates before opening Android Studio:

```bash
php artisan test --compact
bun run test
bun run types:check
vendor/bin/phpstan analyse --memory-limit=1G
bun run check
bun run build
bun run android:sync
```

Then verify on representative physical Android devices:

1. Sign in as customer and branch admin.
2. Create and transition an order, preview a WhatsApp suggestion, and confirm only `https://wa.me` opens.
3. Confirm invalid or unavailable WhatsApp destinations remain inside the preview dialog.
4. Grant Bluetooth permission only when printer discovery is requested.
5. Test Bluetooth SPP and USB printing on supported 58 mm and 80 mm printers.
6. Verify QRIS display, safe-area layout, launcher icon, splash screen, app resume, and network error recovery.
7. Confirm the release build points to the production HTTPS API and contains no demo credentials or development server URL.

## Artifacts

- Prefer `.aab` for Play Store distribution.
- Retain release notes, version code/name, commit SHA, and checksums beside the CI artifact.
- Do not commit generated APK/AAB files.
