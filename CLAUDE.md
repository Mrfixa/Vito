# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

Three-part system called **Vito**: a Laravel 12 backend, a Flutter customer app, and a Flutter driver app. All share the same API.

```
drivemond-admin-new-install-3.1/   # Laravel 12 backend
drivemond-user-app-3.1/            # Flutter customer app
drivemond-driver-app-3.1/          # Flutter driver app
landing/                            # Vanilla HTML QR token validation page
```

---

## Backend (Laravel 12)

**Working directory:** `drivemond-admin-new-install-3.1/`

### Commands

```bash
composer install --no-interaction --no-scripts --ignore-platform-reqs
cp .env.example .env && php artisan key:generate
php artisan passport:keys --force
php artisan migrate
php artisan serve

# Run all Vito tests (SQLite in-memory, no DB setup needed)
php artisan test --filter=VitoFlowTest

# Run static analysis (only on Vito controllers)
./vendor/bin/phpstan analyse --level=0 \
  Modules/AuthManagement/Http/Controllers/Api/VitoAuthController.php \
  Modules/AuthManagement/Http/Controllers/Api/QrTokenController.php \
  Modules/TripManagement/Http/Controllers/Api/Customer/VitoMartController.php \
  Modules/TripManagement/Http/Controllers/Api/Driver/VitoTripController.php \
  Modules/TripManagement/Http/Controllers/Api/Driver/VitoParcelController.php \
  Modules/TripManagement/Http/Controllers/Api/Driver/VitoMartDriverController.php \
  Modules/TripManagement/Http/Controllers/Api/Admin/VitoMartAdminApiController.php \
  Modules/Gateways/Http/Controllers/Api/VitoStripeController.php
```

### Module Architecture

Uses [`nwidart/laravel-modules`](https://nwidart.com/laravel-modules). Each module under `Modules/` is self-contained:

```
Modules/{Module}/
  Entities/          # Eloquent models
  Http/Controllers/
    Api/             # JSON API controllers (Customer/, Driver/, Admin/)
    Web/             # Admin panel controllers
  Routes/
    api.php          # Module API routes (auto-loaded)
    web.php          # Module web routes
  Service/           # Business logic layer
  Repository/        # Data access layer
  Database/
    Migrations/      # Module migrations
  Transformers/      # API Resources
  Providers/         # Module service providers
```

**Key modules:**
- `AuthManagement` — QR token gate, PIN-based auth, username registration
- `TripManagement` — Rides (VitoRide), parcels (VitoSend), mart orders (VitoMart)
- `ChattingManagement` — Real-time messaging (polymorphic: TripRequest or MartOrder)
- `Gateways` — Stripe PaymentIntent with idempotent webhooks
- `UserManagement` — Profiles, driver details
- `ZoneManagement`, `VehicleManagement`, `PromotionManagement`, etc.

### Authentication Flow

- **No email/OTP.** Users register with username + PIN (bcrypt-hashed).
- New users must scan a QR code or enter an invitation token first (`TokenGateScreen` → `POST /api/auth/qr-token/validate`).
- Tokens: 1-hour expiry for customers, 7-day for drivers.
- API auth: Laravel Passport with scopes `AccessToCustomer`, `AccessToDriver`, `AccessToSuperAdmin`.
- QR token generation/revocation: `throttle:10,1` rate limited.

### Test File

`tests/Feature/VitoFlowTest.php` — creates all needed tables in-memory per test run (no external DB). Covers QR tokens, user registration, login, rides, parcels, mart orders (with promo codes), driver acceptance, delivery proof, Stripe payment, and wallet. **Always update tearDown's `dropIfExists` list when adding new tables to the test.**

### Key Patterns

- **Server-side order totals:** The client never sends a price. Backend computes total = (Σ product.price × qty) − promo_discount + tip.
- **Atomic DB operations:** Use `DB::transaction()` with `lockForUpdate()` for promo code `used_count`, stock decrement, etc.
- **Polymorphic chat channels:** `channel_lists.channelable_type` is either `TripRequest::class` or `MartOrder::class`. Check `channelable_type` in `ChattingController` before branching.
- **Broadcast events:** `CustomerRideChatEvent`/`DriverRideChatEvent` for rides; `CustomerMartOrderChatEvent`/`DriverMartOrderChatEvent` for mart. Always wrap `::broadcast()` calls in `try/catch` and check `checkReverbConnection()` first.
- **Push notifications:** Use `sendDeviceNotification()` helper, wrapped in try/catch so failures never break the main flow.

### Adding a New API Endpoint

1. Add route in the module's `Routes/api.php` (under appropriate `auth:api` + `maintenance_mode` middleware group).
2. Add controller method in `Modules/{Module}/Http/Controllers/Api/{Role}/`.
3. If it's a new mart order status transition, add it to `VitoMartDriverController::$validTransitions`.
4. Add a test case to `VitoFlowTest.php`.

---

## Flutter Apps

Both apps share the same structure. Commands below work from either app root.

**User app:** `drivemond-user-app-3.1/HexaRide-User-app-release-3.1/`  
**Driver app:** `drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1/`

### Commands

```bash
flutter pub get
flutter analyze --no-fatal-infos
flutter test test/vito_flows_test.dart

# Build (API keys required at build time)
flutter build apk --debug \
  --dart-define=MAPS_API_KEY=<key> \
  --dart-define=STRIPE_PUBLISHABLE_KEY=<key>
```

### Architecture

**State management:** GetX (`GetxController` + `GetBuilder`). No Provider, no Riverpod.

**DI wiring:** All dependencies registered in `lib/helper/di_container.dart` using `Get.lazyPut()`. The chain is:

```
ApiClient → Repository → Service → Controller
```

When adding a new feature, register all four layers in `di_container.dart`.

**Feature structure:**

```
lib/features/{feature}/
  controllers/         # GetxController (state + actions)
  domain/
    models/            # Plain Dart models (fromJson)
    repositories/      # Interface + implementation (calls ApiClient)
    services/          # Interface + implementation (delegates to repo)
  screens/             # StatefulWidget / StatelessWidget pages
  widgets/             # Feature-local reusable widgets
```

**Navigation:** Always `Get.to(() => Screen())` or `Get.off(() => Screen())`. No named routes.

**API calls:** Use `Get.find<ApiClient>()` methods:
- `getData(url)` — GET
- `postData(url, body)` — POST/PUT with JSON
- `putData(url, body)` — PUT
- `postMultipartData(url, body, files, otherFile)` — multipart upload

### Localization

Translation keys live in `assets/language/en.json` (and `es.json`, `ar.json`). Always add a key to **all three** language files when introducing new UI strings. The test `vito_flows_test.dart` enforces parity between EN, ES, and AR key sets.

Use `.tr` extension on string literals: `'some_key'.tr`.

### Real-time (Pusher)

Both apps use `dart_pusher_channels`. Channel naming convention:

| Chat type | Customer channel | Driver channel |
|-----------|-----------------|----------------|
| Ride      | `private-customer-ride-chat.{tripId}` | `private-driver-ride-chat.{tripId}` |
| Mart order | `private-customer-mart-chat.{orderId}` | `private-driver-mart-chat.{orderId}` |

Subscribe in the controller method, bind the event, and check `id == eventData['channel_conversation']['channel']['trip_id']` (for rides) or `order_id` (for mart) before inserting into the message list.

### Mart Order Chat (User App)

- `MessageController.createMartChannel(driverId, orderId, driverName)` → navigates to `MartMessageScreen`
- `MessageController.subscribeMartMessageChannel(orderId)` — call from `MartMessageScreen.initState`
- `MessageController.sendMartMessage(channelId, orderId)` — sends `order_id` field (not `trip_id`)
- Chat button in `MartOrderTrackingScreen` is disabled until `_driverId` is non-empty (driver not yet assigned)

### Mart Order Chat (Driver App)

- `ChatController.createMartChannel(customerId, orderId, customerName)` → navigates to `MartDriverMessageScreen`
- Same subscribe/send pattern with mart-specific methods
- Chat button lives in `MartDeliveryScreen._buildCustomerInfo()` alongside the call button

---

## CI/CD

`.github/workflows/vito-ci.yml` — runs on every push/PR to `master`:
1. Laravel: PHPStan level 0 on Vito controllers + `VitoFlowTest`
2. Flutter User App: analyze + unit tests + debug APK build (artifact retained 14 days)
3. Flutter Driver App: same

`.github/workflows/build-apk.yml` — triggered on `v*` tags or manual dispatch; builds release APKs.

Required GitHub secrets: `MAPS_API_KEY`, `STRIPE_PUBLISHABLE_KEY`.

---

## Conventions

- **UUIDs everywhere:** All primary keys use `HasUuids` trait (Laravel) / UUID strings (Flutter models).
- **Soft deletes:** Most entities use `SoftDeletes`. Use `->withTrashed()` when needed.
- **Mart order statuses:** `pending` → `accepted` → `picked_up` → `delivered` (or `cancelled` from `pending` only).
- **No client-sent totals:** Strip `total` / `amount` from any order creation request body; always recompute server-side.
- **Branch:** All work goes to `claude/analyze-mart-qr-code-FySPn`.
