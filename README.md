# VITO — Ride-Hailing, Delivery & Marketplace Platform

[![VITO CI](https://github.com/Mrfixa/Vito/actions/workflows/vito-ci.yml/badge.svg)](https://github.com/Mrfixa/Vito/actions/workflows/vito-ci.yml)

VITO is a full-stack ride-hailing platform built on Laravel (admin backend) and Flutter (user & driver mobile apps). It extends the DriveMond base with QR-based invitation flow, PIN authentication, VitoMart marketplace, Stripe wallet, and atomic ride acceptance.

## Architecture

```
Vito/
├── drivemond-admin-new-install-3.1/   # Laravel 12 admin backend (PHP 8.2+)
│   ├── Modules/                       # Modular architecture (nwidart/laravel-modules)
│   │   ├── AuthManagement/            # QR tokens, PIN login/register
│   │   ├── TripManagement/            # Rides, parcels, VitoMart
│   │   ├── Gateways/                  # Stripe PaymentIntent, webhooks
│   │   └── ...                        # 13+ modules
│   └── tests/Feature/VitoFlowTest.php # 25 PHPUnit tests covering all VITO flows
├── drivemond-user-app-3.1/            # Flutter user app (GetX state management)
├── drivemond-driver-app-3.1/          # Flutter driver app
├── landing/                           # Standalone landing page (vanilla HTML)
└── .github/workflows/vito-ci.yml      # CI pipeline
```

## Core Flows

1. **QR Invitation** — Admin/driver generates referral QR tokens (1h client / 7d driver). Landing page validates and shows download link.
2. **Registration** — Username + 6-digit PIN (no phone/OTP). Token-gated entry.
3. **Login** — Username + PIN with 5-attempt lockout (15 min).
4. **VitoRide** — Ride booking with atomic driver acceptance (`WHERE driver_id IS NULL`).
5. **VitoSend** — Parcel delivery with same atomic acceptance.
6. **VitoMart** — Admin product CRUD, client browse/cart/order, driver delivery with photo+signature proof.
7. **Wallet & Stripe** — In-app top-up via Stripe PaymentSheet, idempotent webhook crediting.
8. **Security** — bcrypt PIN hashing, lockout, no API keys in code, haptic feedback on CTAs.

## Setup

### Prerequisites

- PHP 8.2+, Composer 2
- Flutter 3.44+ (Dart 3.12+)
- MySQL 8 (or SQLite for testing)
- Android SDK (for APK builds)

### Backend

```bash
cd drivemond-admin-new-install-3.1
composer install --no-interaction --no-scripts --ignore-platform-reqs
cp .env.example .env
php artisan key:generate
php artisan passport:keys --force
php artisan migrate
php artisan serve
```

### Flutter Apps

```bash
# User app
cd drivemond-user-app-3.1/HexaRide-User-app-release-3.1
flutter pub get
flutter run

# Driver app
cd drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1
flutter pub get
flutter run
```

### Running Tests

```bash
# Laravel (25 VITO flow tests)
cd drivemond-admin-new-install-3.1
php artisan test --filter=VitoFlowTest

# Flutter user app (21 tests)
cd drivemond-user-app-3.1/HexaRide-User-app-release-3.1
flutter test

# Flutter driver app (22 tests)
cd drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1
flutter test
```

## CI/CD

The GitHub Actions workflow (`.github/workflows/vito-ci.yml`) runs on every push/PR to `master`:

- PHPStan static analysis on VITO-specific controllers
- PHPUnit tests (25 VITO flow tests)
- Flutter analyze on both apps
- Flutter tests on both apps
- Debug APK builds (uploaded as artifacts)

## Environment Secrets (GitHub Actions)

| Secret | Purpose |
|--------|---------|
| `MAPS_API_KEY` | Google Maps API key for Flutter apps |
| `STRIPE_PUBLISHABLE_KEY` | Stripe publishable key for payment flows |

## Localization

Both apps support EN, ES, and AR translations. Translation files are in `assets/language/`. All Vito-specific strings are translated in all supported languages.
