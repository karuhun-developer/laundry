# Android Release Guide

LaundryHub uses Capacitor with application ID `com.karuhundeveloper.laundryhub`, Android API 24 minimum, and API 36 compile/target settings. The repository contains branded launcher, adaptive-icon, and splash resources.

## GitHub Actions configuration

Configure these values in **GitHub repository settings > Secrets and variables > Actions** before pushing a release tag.

Repository variable:

- `PRODUCTION_APP_URL`: the hosted Laravel base URL over HTTPS without a trailing `/app`. The production repository uses `https://laundry.karuhundeveloper.com`. The workflow uses `${PRODUCTION_APP_URL}/app` as the Android server URL, and the application uses same-origin API routes, so a separate API base URL is not required.

Repository secrets:

- `ANDROID_KEYSTORE_BASE64`: the upload keystore encoded as one-line Base64.
- `ANDROID_KEYSTORE_PASSWORD`: the keystore password.
- `ANDROID_KEY_ALIAS`: the upload key alias.
- `ANDROID_KEY_PASSWORD`: the upload key password.

Generate the Base64 value without committing it:

```bash
base64 -w 0 /secure/path/laundryhub-upload.jks
```

On macOS, use `base64 < /secure/path/laundryhub-upload.jks | tr -d '\n'`. Keep the keystore and all four values in the team's secret manager.

## Prepare the web bundle

For local development, set the application URL before syncing Capacitor:

```bash
APP_URL=https://laundry.karuhundeveloper.com CAPACITOR_SERVER_URL=https://laundry.karuhundeveloper.com/app bun run build
CAPACITOR_SERVER_URL=https://laundry.karuhundeveloper.com/app bun run android:sync
```

For a hosted web build instead of bundled assets, set `CAPACITOR_SERVER_URL` to the production HTTPS origin during sync. Cleartext HTTP is intended only for controlled local development.

## Release changelog

Before creating a tag, move the completed entries from `[Unreleased]` into a non-empty version section in `CHANGELOG.md`:

```markdown
## [1.2.0] - 2026-09-13

### Added

- Describe the released behavior.
```

The tag and changelog version must match exactly. The workflow derives `versionName` from the tag and calculates `versionCode` as `MAJOR * 1,000,000 + MINOR * 1,000 + PATCH`. Minor and patch values must not exceed 999.

## Create a release

Create an upload key outside the repository if one does not already exist:

```bash
keytool -genkeypair -v -keystore /secure/path/laundryhub-upload.jks -alias laundryhub -keyalg RSA -keysize 4096 -validity 10000
```

Commit and push the changelog first, then create and push an annotated stable semantic-version tag:

```bash
git tag -a v1.2.0 -m 'LaundryHub 1.2.0'
git push origin v1.2.0
```

Only a matching `vMAJOR.MINOR.PATCH` tag starts `.github/workflows/android-release.yml`. The workflow runs PHP and frontend quality gates, builds signed APK and AAB files, generates SHA-256 checksums, and publishes all three files to the matching GitHub Release. It never commits the keystore or generated artifacts.

Do not move or reuse a published tag. If a release needs a correction, update `[Unreleased]`, create the next patch section, and push a new patch tag.

## Release verification

The tag workflow runs these repository quality gates before Gradle:

```bash
php artisan test --compact
bun run test
bun run types:check
vendor/bin/phpstan analyse --memory-limit=1G
bun run check
bun run build
bun run android:sync
```

The release fails before Gradle when its changelog entry, production URL, or signing configuration is missing or invalid.

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
