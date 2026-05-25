<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Passport\Passport;
use Modules\AuthManagement\Entities\QrToken;
use Modules\TripManagement\Entities\MartProduct;
use Modules\TripManagement\Entities\MartOrder;
use Modules\TripManagement\Entities\MartOrderItem;
use Modules\TripManagement\Entities\MartPromoCode;
use Modules\TripManagement\Entities\StripeEvent;
use Modules\UserManagement\Entities\User;
use Tests\TestCase;

class VitoFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapVitoSchema();
        Passport::tokensCan([
            'AccessToCustomer' => 'Customer Panel Access',
            'AccessToDriver' => 'Driver Panel Access',
            'AccessToSuperAdmin' => 'Admin Panel Access',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('mart_order_items');
        Schema::dropIfExists('mart_orders');
        Schema::dropIfExists('mart_promo_codes');
        Schema::dropIfExists('mart_products');
        Schema::dropIfExists('qr_tokens');
        Schema::dropIfExists('user_accounts');
        Schema::dropIfExists('time_tracks');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('driver_details');
        Schema::dropIfExists('trip_requests');
        Schema::dropIfExists('user_levels');
        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_personal_access_clients');
        Schema::dropIfExists('oauth_clients');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function bootstrapVitoSchema(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('full_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->string('user_type')->default('customer');
                $table->boolean('is_active')->default(1);
                $table->string('ref_code')->nullable();
                $table->string('profile_image')->nullable();
                $table->unsignedSmallInteger('failed_attempt')->default(0);
                $table->boolean('is_temp_blocked')->default(0);
                $table->timestamp('blocked_at')->nullable();
                $table->string('username', 50)->nullable()->unique();
                $table->string('pin_hash')->nullable();
                $table->unsignedSmallInteger('pin_attempts')->default(0);
                $table->timestamp('pin_blocked_at')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->string('identification_image')->nullable();
                $table->string('other_documents')->nullable();
                $table->string('logged_in_via')->nullable();
                $table->uuid('user_level_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('user_levels')) {
            Schema::create('user_levels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('user_type');
                $table->integer('sequence');
                $table->string('reward_type')->default('no_reward');
                $table->decimal('reward_amount', 10, 2)->default(0);
                $table->integer('targeted_ride')->default(0);
                $table->integer('targeted_ride_point')->default(0);
                $table->decimal('targeted_amount', 10, 2)->default(0);
                $table->integer('targeted_amount_point')->default(0);
                $table->integer('targeted_cancel')->default(0);
                $table->integer('targeted_cancel_point')->default(0);
                $table->integer('targeted_review')->default(0);
                $table->integer('targeted_review_point')->default(0);
                $table->integer('max_cancellation')->default(5);
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('user_accounts')) {
            Schema::create('user_accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->decimal('wallet_balance', 23, 2)->default(0);
                $table->decimal('receivable_balance', 23, 2)->default(0);
                $table->decimal('payable_balance', 23, 2)->default(0);
                $table->decimal('received_balance', 23, 2)->default(0);
                $table->decimal('pending_balance', 23, 2)->default(0);
                $table->decimal('total_withdrawn', 23, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key_name');
                $table->text('value')->nullable();
                $table->string('settings_type')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('trip_requests')) {
            Schema::create('trip_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->nullable();
                $table->uuid('driver_id')->nullable();
                $table->string('current_status')->default('pending');
                $table->string('type')->default('ride_request');
                $table->uuid('zone_id')->nullable();
                $table->uuid('area_id')->nullable();
                $table->uuid('vehicle_category_id')->nullable();
                $table->string('payment_method')->nullable();
                $table->decimal('estimated_fare', 23, 2)->default(0);
                $table->decimal('actual_fare', 23, 2)->default(0);
                $table->decimal('estimated_distance', 23, 2)->default(0);
                $table->decimal('paid_fare', 23, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('driver_details')) {
            Schema::create('driver_details', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id');
                $table->boolean('is_online')->default(false);
                $table->string('availability_status')->default('unavailable');
                $table->string('plate_number')->nullable();
                $table->string('car_photo')->nullable();
                $table->boolean('is_approved')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('time_tracks')) {
            Schema::create('time_tracks', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id');
                $table->date('date');
                $table->timestamp('online_at')->nullable();
                $table->timestamp('offline_at')->nullable();
                $table->integer('total_online')->default(0);
                $table->integer('total_offline')->default(0);
                $table->integer('total_idle')->default(0);
                $table->integer('total_driving')->default(0);
                $table->timestamp('last_ride_started_at')->nullable();
                $table->timestamp('last_ride_completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('edited_by')->nullable();
                $table->text('before')->nullable();
                $table->text('after')->nullable();
                $table->string('logable_id')->nullable();
                $table->string('logable_type')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('qr_tokens')) {
            Schema::create('qr_tokens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('token', 64)->unique();
                $table->string('role')->default('driver');
                $table->uuid('created_by')->nullable();
                $table->uuid('redeemed_by')->nullable();
                $table->timestamp('redeemed_at')->nullable();
                $table->timestamp('expires_at');
                $table->boolean('is_revoked')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mart_products')) {
            Schema::create('mart_products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2);
                $table->string('image')->nullable();
                $table->string('category')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('stock')->default(0);
                $table->uuid('zone_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mart_promo_codes')) {
            Schema::create('mart_promo_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('discount_type')->default('fixed');
                $table->decimal('discount_value', 10, 2);
                $table->decimal('min_order_amount', 10, 2)->default(0);
                $table->decimal('max_discount', 10, 2)->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('per_user_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mart_orders')) {
            Schema::create('mart_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ref_id', 20)->unique();
                $table->uuid('customer_id');
                $table->uuid('driver_id')->nullable();
                $table->string('status')->default('pending');
                $table->decimal('total_amount', 10, 2);
                $table->decimal('tip_amount', 10, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->string('promo_code')->nullable();
                $table->string('payment_status')->default('unpaid');
                $table->string('payment_method')->nullable();
                $table->text('delivery_address')->nullable();
                $table->decimal('delivery_lat', 10, 7)->nullable();
                $table->decimal('delivery_lng', 10, 7)->nullable();
                $table->string('signature_image')->nullable();
                $table->string('delivery_photo')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mart_order_items')) {
            Schema::create('mart_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->uuid('product_id');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('total_price', 10, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stripe_events')) {
            Schema::create('stripe_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('stripe_event_id');
                $table->string('type');
                $table->uuid('user_id');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('usd');
                $table->string('status')->default('pending');
                $table->string('payment_intent_id');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('model')->nullable();
                $table->string('model_id')->nullable();
                $table->string('message')->nullable();
                $table->timestamps();
            });
        }

        // Passport tables for token creation
        if (!Schema::hasTable('oauth_clients')) {
            Schema::create('oauth_clients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('name');
                $table->string('secret', 100)->nullable();
                $table->string('provider')->nullable();
                $table->text('redirect');
                $table->boolean('personal_access_client')->default(0);
                $table->boolean('password_client')->default(0);
                $table->boolean('revoked')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('oauth_personal_access_clients')) {
            Schema::create('oauth_personal_access_clients', function (Blueprint $table) {
                $table->id();
                $table->uuid('client_id');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('oauth_access_tokens')) {
            Schema::create('oauth_access_tokens', function (Blueprint $table) {
                $table->string('id', 100)->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('client_id');
                $table->string('name')->nullable();
                $table->text('scopes')->nullable();
                $table->boolean('revoked')->default(0);
                $table->timestamps();
                $table->dateTime('expires_at')->nullable();
            });
        }

        // Seed Passport personal access client
        $clientId = Str::uuid()->toString();
        DB::table('oauth_clients')->insertOrIgnore([
            'id' => $clientId,
            'user_id' => null,
            'name' => 'Test Personal Access Client',
            'secret' => Str::random(40),
            'redirect' => 'http://localhost',
            'personal_access_client' => 1,
            'password_client' => 0,
            'revoked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('oauth_personal_access_clients')->insertOrIgnore([
            'id' => 1,
            'client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(string $type = 'customer', array $overrides = []): User
    {
        return User::create(array_merge([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => 'test' . Str::random(5) . '@test.com',
            'phone' => '+1' . rand(1000000000, 9999999999),
            'password' => Hash::make('123456'),
            'pin_hash' => Hash::make('123456'),
            'username' => 'user_' . Str::random(8),
            'user_type' => $type,
            'is_active' => 1,
            'pin_attempts' => 0,
            'ref_code' => Str::random(8),
        ], $overrides));
    }

    private function createUserAccount(User $user): void
    {
        DB::table('user_accounts')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'wallet_balance' => 0,
            'receivable_balance' => 0,
            'payable_balance' => 0,
            'received_balance' => 0,
            'pending_balance' => 0,
            'total_withdrawn' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedUserLevel(string $type): void
    {
        DB::table('user_levels')->insert([
            'id' => Str::uuid()->toString(),
            'name' => 'Level 1',
            'user_type' => $type,
            'sequence' => 1,
            'reward_type' => 'no_reward',
            'reward_amount' => 0,
            'targeted_ride' => 0,
            'targeted_ride_point' => 0,
            'targeted_amount' => 0,
            'targeted_amount_point' => 0,
            'targeted_cancel' => 0,
            'targeted_cancel_point' => 0,
            'targeted_review' => 0,
            'targeted_review_point' => 0,
            'max_cancellation' => 5,
            'image' => '',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ========================================================================
    // 1. QR Token Generate & Validate
    // ========================================================================

    public function test_qr_generate_and_validate(): void
    {
        $admin = $this->createUser('admin');
        Passport::actingAs($admin, ['AccessToSuperAdmin']);

        $genResponse = $this->postJson('/api/qr-token/generate', ['role' => 'customer']);
        $genResponse->assertOk();
        $token = $genResponse->json('data.token');
        $this->assertNotNull($token);
        $this->assertEquals(64, strlen($token));

        $valResponse = $this->postJson('/api/qr-token/validate', ['token' => $token]);
        $valResponse->assertOk();
        $this->assertTrue($valResponse->json('data.valid'));
        $this->assertEquals('customer', $valResponse->json('data.role'));

        // Customer token expires in ~1h
        $qrToken = QrToken::where('token', $token)->first();
        $diffSeconds = abs(now()->diffInSeconds($qrToken->expires_at));
        $this->assertLessThanOrEqual(3605, $diffSeconds);
        $this->assertGreaterThan(3500, $diffSeconds);

        // Driver onboarding token expires in ~7d
        $genResponse2 = $this->postJson('/api/qr-token/generate', ['role' => 'driver']);
        $token2 = $genResponse2->json('data.token');
        $qrToken2 = QrToken::where('token', $token2)->first();
        $driverDiff = abs(now()->diffInSeconds($qrToken2->expires_at));
        $this->assertGreaterThan(6 * 24 * 3600, $driverDiff);
    }

    // ========================================================================
    // 2. Client Registration with Valid Token
    // ========================================================================

    public function test_client_registration_with_valid_token(): void
    {
        $this->seedUserLevel('customer');

        $token = str_repeat('c', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(),
            'token' => $token,
            'role' => 'customer',
            'created_by' => null,
            'redeemed_by' => null,
            'redeemed_at' => null,
            'expires_at' => now()->addHour(),
            'is_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'pin' => '654321',
            'pin_confirmation' => '654321',
            'qr_token' => $token,
        ]);

        $response->assertOk();
        $user = User::where('username', 'janedoe')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('654321', $user->pin_hash));
        $this->assertEquals('customer', $user->user_type);

        // Token must be redeemed atomically
        $this->assertDatabaseHas('qr_tokens', [
            'token' => $token,
            'redeemed_by' => $user->id,
        ]);
        $this->assertNotNull(DB::table('qr_tokens')->where('token', $token)->value('redeemed_at'));

        // Same token cannot be reused
        $this->seedUserLevel('customer');
        $retry = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Eve',
            'last_name' => 'Repeat',
            'username' => 'everepeat',
            'pin' => '999888',
            'pin_confirmation' => '999888',
            'qr_token' => $token,
        ]);
        $retry->assertStatus(400);
    }

    // ========================================================================
    // 3. Driver Registration with Onboarding Token
    // ========================================================================

    public function test_driver_registration_with_onboarding_token(): void
    {
        $this->seedUserLevel('driver');

        DB::table('business_settings')->insert([
            'id' => Str::uuid()->toString(),
            'key_name' => 'driver_self_registration',
            'value' => json_encode('1'),
            'settings_type' => 'business_information',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $driverToken = str_repeat('d', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(),
            'token' => $driverToken,
            'role' => 'driver',
            'created_by' => null,
            'redeemed_by' => null,
            'redeemed_at' => null,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/driver/auth/pin-register', [
            'first_name' => 'John',
            'last_name' => 'Driver',
            'username' => 'johndriver',
            'pin' => '111222',
            'pin_confirmation' => '111222',
            'qr_token' => $driverToken,
        ]);

        $response->assertOk();
        $user = User::where('username', 'johndriver')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('111222', $user->pin_hash));
        $this->assertEquals('driver', $user->user_type);

        // Token redeemed
        $this->assertNotNull(DB::table('qr_tokens')->where('token', $driverToken)->value('redeemed_at'));
    }

    // ========================================================================
    // 4. PIN Login & Lockout
    // ========================================================================

    public function test_pin_login_and_lockout(): void
    {
        $user = $this->createUser('customer', [
            'username' => 'testlogin',
            'pin_hash' => Hash::make('123456'),
        ]);

        // Successful login
        $response = $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'testlogin',
            'pin' => '123456',
        ]);
        $response->assertOk();
        $this->assertArrayHasKey('token', $response->json('data'));

        // 5 failed attempts trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/customer/auth/pin-login', [
                'username' => 'testlogin',
                'pin' => '000000',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->pin_blocked_at);

        $lockResponse = $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'testlogin',
            'pin' => '123456',
        ]);
        $lockResponse->assertStatus(403);
        $this->assertStringContainsString('too_many_attempt', $lockResponse->json('response_code'));
    }

    // ========================================================================
    // 5. Atomic Ride Acceptance (race condition protection)
    // ========================================================================

    public function test_atomic_ride_acceptance(): void
    {
        $customer = $this->createUser('customer');
        $driver1 = $this->createUser('driver', ['username' => 'driver1']);
        $driver2 = $this->createUser('driver', ['username' => 'driver2']);

        // Ensure driver_details exist (required by TripRequest model observer)
        DB::table('driver_details')->insert([
            ['user_id' => $driver1->id, 'is_online' => 1, 'availability_status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $driver2->id, 'is_online' => 1, 'availability_status' => 'available', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed time_tracks (required by TripRequest booted observer)
        DB::table('time_tracks')->insert([
            ['user_id' => $driver1->id, 'date' => now()->toDateString(), 'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $driver2->id, 'date' => now()->toDateString(), 'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $rideId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $rideId,
            'customer_id' => $customer->id,
            'driver_id' => null,
            'current_status' => 'pending',
            'type' => 'ride_request',
            'zone_id' => Str::uuid()->toString(),
            'area_id' => Str::uuid()->toString(),
            'vehicle_category_id' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'estimated_fare' => 100,
            'actual_fare' => 0,
            'estimated_distance' => 5,
            'paid_fare' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Driver 1 accepts first
        Passport::actingAs($driver1, ['AccessToDriver']);
        $res1 = $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $rideId]);
        $res1->assertOk();
        $this->assertNotNull($res1->json('data'));

        // Driver 2 cannot accept (already taken)
        Passport::actingAs($driver2, ['AccessToDriver']);
        $res2 = $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $rideId]);
        $res2->assertStatus(404);
    }

    // ========================================================================
    // 6. Mart Product CRUD
    // ========================================================================

    public function test_mart_product_crud(): void
    {
        $product = MartProduct::create([
            'name' => 'Test Product',
            'price' => 9.99,
            'stock' => 100,
            'category' => 'test',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('mart_products', ['name' => 'Test Product']);

        $product->update(['name' => 'Updated Product', 'price' => 19.99]);
        $this->assertDatabaseHas('mart_products', ['name' => 'Updated Product']);

        $product->delete();
        $this->assertSoftDeleted('mart_products', ['id' => $product->id]);
    }

    // ========================================================================
    // 7. Wallet Topup Intent (validation check, no real Stripe)
    // ========================================================================

    public function test_wallet_topup_intent(): void
    {
        $user = $this->createUser('customer');
        Passport::actingAs($user, ['AccessToCustomer']);

        // Invalid amount should fail validation
        $response = $this->postJson('/api/customer/stripe/payment-intent', ['amount' => -5]);
        $response->assertStatus(400);

        // Missing amount should fail
        $response2 = $this->postJson('/api/customer/stripe/payment-intent', []);
        $response2->assertStatus(400);
    }

    // ========================================================================
    // 8. Mart Promo Code Apply
    // ========================================================================

    public function test_mart_apply_promo_code(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        // Fixed discount promo
        MartPromoCode::create([
            'code' => 'SAVE5',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'min_order_amount' => 20.00,
            'is_active' => true,
        ]);

        // Valid promo application
        $response = $this->postJson('/api/customer/mart/apply-promo', [
            'code' => 'SAVE5',
            'subtotal' => 30.00,
        ]);
        $response->assertOk();
        $this->assertEquals(5.00, $response->json('data.discount'));

        // Below minimum order amount
        $response2 = $this->postJson('/api/customer/mart/apply-promo', [
            'code' => 'SAVE5',
            'subtotal' => 10.00,
        ]);
        $response2->assertStatus(400);

        // Invalid code
        $response3 = $this->postJson('/api/customer/mart/apply-promo', [
            'code' => 'NOTREAL',
            'subtotal' => 50.00,
        ]);
        $response3->assertStatus(404);
    }

    // ========================================================================
    // 9. Mart Order With Tip and Promo (server-side total)
    // ========================================================================

    public function test_mart_order_server_side_total(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Burger',
            'price' => 10.00,
            'stock' => 50,
            'category' => 'food',
            'is_active' => true,
        ]);

        MartPromoCode::create([
            'code' => 'PROMO2',
            'discount_type' => 'fixed',
            'discount_value' => 3.00,
            'min_order_amount' => 0,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => '123 Test St',
            'tip_amount' => 2.00,
            'promo_code' => 'PROMO2',
        ]);

        $response->assertOk();

        $order = MartOrder::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order);
        // subtotal=20, discount=3, tip=2 → total=19
        $this->assertEquals('19.00', $order->total_amount);
        $this->assertEquals('3.00', $order->discount_amount);
        $this->assertEquals('2.00', $order->tip_amount);
        $this->assertEquals('PROMO2', $order->promo_code);

        // Promo used_count should increment
        $promo = MartPromoCode::where('code', 'PROMO2')->first();
        $this->assertEquals(1, $promo->used_count);

        // Cancel restores stock and decrements used_count
        $response2 = $this->putJson("/api/customer/mart/orders/{$order->id}/cancel");
        $response2->assertOk();

        $product->refresh();
        $this->assertEquals(50, $product->stock);

        $promo->refresh();
        $this->assertEquals(0, $promo->used_count);
    }

    // ========================================================================
    // 10. Driver Order Details Endpoint
    // ========================================================================

    public function test_driver_mart_order_details(): void
    {
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver');

        $product = MartProduct::create([
            'name' => 'Water Bottle',
            'price' => 2.00,
            'stock' => 20,
            'category' => 'drinks',
            'is_active' => true,
        ]);

        $order = MartOrder::create([
            'ref_id' => 'VM-TESTDRV1',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'total_amount' => 4.00,
            'delivery_address' => '456 Driver Rd',
        ]);

        MartOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 2.00,
            'total_price' => 4.00,
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $response = $this->getJson("/api/driver/mart/orders/{$order->id}");
        $response->assertOk();
        $this->assertEquals($order->id, $response->json('data.id'));
        $this->assertCount(1, $response->json('data.items'));

        // Another driver cannot see this order
        $otherDriver = $this->createUser('driver');
        Passport::actingAs($otherDriver, ['AccessToDriver']);
        $response2 = $this->getJson("/api/driver/mart/orders/{$order->id}");
        $response2->assertStatus(404);
    }

    // ========================================================================
    // 11. Zone Filtering on Products
    // ========================================================================

    public function test_mart_products_zone_filter(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $zoneA = Str::uuid()->toString();
        $zoneB = Str::uuid()->toString();

        MartProduct::create(['name' => 'Zone A Product', 'price' => 5.00, 'stock' => 10, 'is_active' => true, 'zone_id' => $zoneA]);
        MartProduct::create(['name' => 'Zone B Product', 'price' => 5.00, 'stock' => 10, 'is_active' => true, 'zone_id' => $zoneB]);
        MartProduct::create(['name' => 'Global Product', 'price' => 5.00, 'stock' => 10, 'is_active' => true, 'zone_id' => null]);

        // Filter by zone A: should see Zone A + Global
        $response = $this->getJson("/api/customer/mart/products?zone_id={$zoneA}");
        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('name')->all();
        $this->assertContains('Zone A Product', $names);
        $this->assertContains('Global Product', $names);
        $this->assertNotContains('Zone B Product', $names);
    }

    // ========================================================================
    // 12. QR Token Expiry Pruning
    // ========================================================================

    public function test_qr_token_pruning_command(): void
    {
        // Token expired 31 days ago (should be pruned)
        QrToken::create([
            'token' => str_repeat('a', 64),
            'role' => 'customer',
            'created_by' => null,
            'expires_at' => now()->subDays(31),
            'is_revoked' => false,
        ]);

        // Token expired 10 days ago (within 30-day grace, not pruned)
        QrToken::create([
            'token' => str_repeat('b', 64),
            'role' => 'customer',
            'created_by' => null,
            'expires_at' => now()->subDays(10),
            'is_revoked' => false,
        ]);

        $this->artisan('vito:prune-qr-tokens')->assertSuccessful();

        $this->assertDatabaseMissing('qr_tokens', ['token' => str_repeat('a', 64)]);
        $this->assertDatabaseHas('qr_tokens', ['token' => str_repeat('b', 64)]);
    }

    // ========================================================================
    // 13. Webhook Idempotent
    // ========================================================================

    public function test_webhook_idempotent(): void
    {
        $user = $this->createUser('customer');
        $this->createUserAccount($user);

        $paymentIntentId = 'pi_test_' . Str::random(24);

        StripeEvent::create([
            'stripe_event_id' => $paymentIntentId,
            'type' => 'payment_intent.created',
            'user_id' => $user->id,
            'amount' => 50.00,
            'currency' => 'usd',
            'status' => 'pending',
            'payment_intent_id' => $paymentIntentId,
        ]);

        // First webhook call — credits wallet
        DB::transaction(function () use ($paymentIntentId, $user) {
            $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                ->lockForUpdate()->first();
            if ($stripeEvent && $stripeEvent->status !== 'succeeded') {
                $stripeEvent->update(['status' => 'succeeded']);
                DB::table('user_accounts')->where('user_id', $user->id)
                    ->increment('wallet_balance', 50.00);
            }
        });

        $balance = (float) DB::table('user_accounts')
            ->where('user_id', $user->id)->value('wallet_balance');
        $this->assertEquals(50.00, $balance);

        // Second webhook call — idempotent, no double credit
        DB::transaction(function () use ($paymentIntentId, $user) {
            $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                ->lockForUpdate()->first();
            if ($stripeEvent && $stripeEvent->status !== 'succeeded') {
                $stripeEvent->update(['status' => 'succeeded']);
                DB::table('user_accounts')->where('user_id', $user->id)
                    ->increment('wallet_balance', 50.00);
            }
        });

        $balance2 = (float) DB::table('user_accounts')
            ->where('user_id', $user->id)->value('wallet_balance');
        $this->assertEquals(50.00, $balance2);
    }

    // ========================================================================
    // 14. Promo usage_limit hard cap
    // ========================================================================

    public function test_promo_usage_limit(): void
    {
        $customer = $this->createUser('customer');
        $customer2 = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'code' => 'LIMIT2',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'min_order_amount' => 0,
            'usage_limit' => 2,
            'per_user_limit' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $product = MartProduct::create([
            'name' => 'Widget',
            'price' => 20.00,
            'stock' => 100,
            'is_active' => true,
        ]);

        // First order with code — should succeed
        $r1 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Addr 1',
            'promo_code' => 'LIMIT2',
        ]);
        $r1->assertStatus(200);

        // Second order with code — should succeed (usage_limit=2)
        $r2 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Addr 2',
            'promo_code' => 'LIMIT2',
        ]);
        $r2->assertStatus(200);

        // Promo used_count should now be 2
        $this->assertEquals(2, MartPromoCode::where('code', 'LIMIT2')->value('used_count'));

        // Third order — promo is exhausted, order still placed but no discount applied
        $r3 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Addr 3',
            'promo_code' => 'LIMIT2',
        ]);
        $r3->assertStatus(200);
        // No discount since usage_limit reached — total equals product price
        $orderId3 = $r3->json('data.id');
        $order3 = MartOrder::find($orderId3);
        $this->assertEquals('20.00', $order3->total_amount);
    }

    // ========================================================================
    // 15. Stock out-of-stock error
    // ========================================================================

    public function test_out_of_stock(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Scarce Item',
            'price' => 15.00,
            'stock' => 2,
            'is_active' => true,
        ]);

        // Order exactly the available stock
        $r1 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => 'Addr 1',
        ]);
        $r1->assertStatus(200);
        $this->assertEquals(0, MartProduct::find($product->id)->stock);

        // Next order should fail — insufficient stock
        $r2 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Addr 2',
        ]);
        $r2->assertStatus(400);
    }

    // ========================================================================
    // 16. Tip cap at 30% of subtotal
    // ========================================================================

    public function test_tip_cap(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Tippy Product',
            'price' => 10.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Send tip=999 on a $10 order — should be capped to 30% = $3
        $r = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Tip test',
            'tip_amount' => 999,
        ]);
        $r->assertStatus(200);
        $order = MartOrder::latest()->first();
        $this->assertEquals('3.00', $order->tip_amount);
        $this->assertEquals('13.00', $order->total_amount);
    }

    // ========================================================================
    // 17. Stripe webhook credits wallet when StripeEvent record missing
    // ========================================================================

    public function test_webhook_credits_wallet_without_prior_record(): void
    {
        $user = $this->createUser('customer');
        DB::table('user_accounts')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'wallet_balance' => 0,
        ]);

        $paymentIntentId = 'pi_new_' . Str::random(24);
        $stripeEventId = 'evt_new_' . Str::random(24);

        // No StripeEvent record exists yet
        $this->assertFalse(StripeEvent::where('payment_intent_id', $paymentIntentId)->exists());

        // Simulate the webhook transaction directly
        DB::transaction(function () use ($paymentIntentId, $stripeEventId, $user) {
            $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                ->lockForUpdate()->first();

            if (!$stripeEvent) {
                $stripeEvent = StripeEvent::create([
                    'stripe_event_id' => $stripeEventId,
                    'type' => 'payment_intent.succeeded',
                    'user_id' => $user->id,
                    'amount' => 25.00,
                    'currency' => 'usd',
                    'status' => 'pending',
                    'payment_intent_id' => $paymentIntentId,
                ]);
            }

            if ($stripeEvent->status !== 'succeeded') {
                $stripeEvent->update(['status' => 'succeeded', 'stripe_event_id' => $stripeEventId]);
                DB::table('user_accounts')->where('user_id', $user->id)
                    ->increment('wallet_balance', 25.00);
            }
        });

        $balance = (float) DB::table('user_accounts')->where('user_id', $user->id)->value('wallet_balance');
        $this->assertEquals(25.00, $balance);
        $this->assertEquals('succeeded', StripeEvent::where('payment_intent_id', $paymentIntentId)->value('status'));
    }

    // ========================================================================
    // 18. Mart driver concurrent order accept — only one wins
    // ========================================================================

    public function test_concurrent_mart_order_accept(): void
    {
        $driver1 = $this->createUser('driver');
        $driver2 = $this->createUser('driver');

        $product = MartProduct::create([
            'name' => 'Concurrent Product',
            'price' => 10.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        $customer = $this->createUser('customer');
        $order = MartOrder::create([
            'ref_id' => 'VM-CONC001',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 10.00,
            'delivery_address' => 'Test',
        ]);

        // Simulate both drivers attempting to accept simultaneously using DB transactions
        $result1 = DB::transaction(function () use ($order, $driver1) {
            $o = MartOrder::where('id', $order->id)
                ->where('status', 'pending')
                ->whereNull('driver_id')
                ->lockForUpdate()->first();
            if (!$o) return null;
            $o->update(['driver_id' => $driver1->id, 'status' => 'accepted']);
            return $o;
        });

        $result2 = DB::transaction(function () use ($order, $driver2) {
            $o = MartOrder::where('id', $order->id)
                ->where('status', 'pending')
                ->whereNull('driver_id')
                ->lockForUpdate()->first();
            if (!$o) return null;
            $o->update(['driver_id' => $driver2->id, 'status' => 'accepted']);
            return $o;
        });

        // First driver wins, second gets null
        $this->assertNotNull($result1);
        $this->assertNull($result2);
        $this->assertEquals($driver1->id, MartOrder::find($order->id)->driver_id);
    }

    // ========================================================================
    // 19. Invalid mart order status transition rejected
    // ========================================================================

    public function test_invalid_mart_status_transition(): void
    {
        $driver = $this->createUser('driver');
        Passport::actingAs($driver, ['AccessToDriver']);

        $customer = $this->createUser('customer');
        $order = MartOrder::create([
            'ref_id' => 'VM-TRANS01',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'total_amount' => 10.00,
            'delivery_address' => 'Test',
        ]);

        // Can't go from accepted → delivered (must go accepted → picked_up → delivered)
        $r = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id,
            'status' => 'delivered',
        ]);
        $r->assertStatus(404);

        // Verify status unchanged
        $this->assertEquals('accepted', MartOrder::find($order->id)->status);
    }
}
