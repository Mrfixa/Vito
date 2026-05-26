---
name: testing-vito-transformation
description: Test VITO transformation code across Laravel backend and Flutter apps. Use when verifying PHP controllers, Dart screens, security fixes, or code pattern compliance.
---

# Testing VITO Transformation

## Environment Setup

### PHP (for Laravel backend syntax checks)
```bash
sudo apt-get install -y php-cli php-mbstring php-xml php-bcmath php-curl
```

### Flutter SDK (for Dart analysis)
```bash
cd /home/ubuntu && curl -fsSL https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_3.24.4-stable.tar.xz -o flutter.tar.xz && tar xf flutter.tar.xz && rm flutter.tar.xz
export PATH="/home/ubuntu/flutter/bin:$PATH"
flutter config --no-analytics
```

### Known Issue: flutter_lints version conflict
The repo's `pubspec.yaml` depends on `flutter_lints ^6.0.0` which requires Dart SDK ^3.8.0, but Flutter 3.24.4 ships with Dart 3.5.4. This means `flutter pub get` will fail. This is a pre-existing issue. You can still run `dart analyze` on individual files — import resolution errors are expected, but logic errors in your code will still surface.

## Testing Procedures

### 1. PHP Syntax Check (All Backend Files)
```bash
find drivemond-admin-new-install-3.1/Modules -name "*.php" -not -path "*/vendor/*" -exec php -l {} \;
```
Pass: All files report "No syntax errors detected"

### 2. Dart Analysis (Individual Files)
```bash
export PATH="/home/ubuntu/flutter/bin:$PATH"
dart analyze path/to/file.dart
```
Note: Ignore `uri_does_not_exist` errors (package resolution) and `override_on_non_overriding_member` warnings (framework type resolution). Focus on logic errors like `undefined_identifier` for local symbols.

### 3. Security Pattern Checks
- **Token revocation**: Must be AFTER PIN verification in auth controllers
- **Webhook idempotency**: Must use `lockForUpdate()` + status check before processing
- **Webhook auth**: Must reject when webhook secret is not configured
- **Wallet operations**: Must use `userAccount()` relationship, not direct `User` model
- **Amount conversion**: Must use `round()` for float-to-cents
- **Status transitions**: Must validate allowed state transitions

### 4. Code Pattern Compliance
- **Atomic operations**: All acceptance endpoints must use `DB::transaction()` + `lockForUpdate()`
- **i18n**: All user-visible strings must use `.tr` suffix (GetX)
- **Haptic feedback**: New CTAs must include `HapticFeedback.mediumImpact()` or `.heavyImpact()`
- **Offline support**: New screens should have offline banner with `wifi_off` icon
- **Empty states**: New list/grid screens should have empty state with icon + descriptive text

## Repo Structure
- **Laravel backend**: `drivemond-admin-new-install-3.1/` (17 modules in `Modules/`)
- **Driver Flutter app**: `drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1/`
- **User Flutter app**: `drivemond-user-app-3.1/HexaRide-User-app-release-3.1/`

## Limitations
- No Android emulator available — UI testing requires physical device or emulator setup
- `flutter build apk` requires full dependency resolution (blocked by flutter_lints issue)
- Laravel functional tests require database setup (`php artisan migrate`)

## Devin Secrets Needed
No secrets required for static analysis testing. For functional testing:
- Database credentials (for Laravel migration testing)
- Stripe test keys (for payment integration testing)
