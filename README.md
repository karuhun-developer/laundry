# LaundryHub

LaundryHub is a multi-branch laundry operations platform built with Laravel, Inertia, Vue 3, TypeScript, Tailwind CSS, and Capacitor. The product is delivered in documented phases; see PRD.md, DESIGN.md, and docs/features/README.md.

## Requirements

- PHP 8.3 or newer
- Composer 2
- Bun 1.2 or newer
- SQLite for local development, or MySQL for production-compatible testing

## Local setup

~~~bash
composer run setup
composer run dev
~~~

The development application is available at http://localhost:8000.

## Quality gates

~~~bash
composer run test
bun run check
bun run types:check
bun run build
~~~

Application use cases belong in Action classes and receive typed DTOs. Controllers use dedicated Form Requests, and API responses use Resources. Authorization is enforced by policies and Spatie Permission.

## Repository workflow

Each independently reversible feature is committed atomically. User-visible changes are recorded in CHANGELOG.md, and each feature maintains its contract under docs/features.

Signed Android APK/AAB releases are built from stable semantic-version tags. Before pushing a release tag, configure the `PRODUCTION_APP_URL` GitHub Actions variable plus the `ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, and `ANDROID_KEY_PASSWORD` secrets. The full changelog format and release procedure are documented in [docs/android-release.md](docs/android-release.md).
