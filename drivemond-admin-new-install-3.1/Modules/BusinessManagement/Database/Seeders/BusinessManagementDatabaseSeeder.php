<?php

namespace Modules\BusinessManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class BusinessManagementDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            DB::table('business_settings')->updateOrInsert(
                ['key_name' => $row['key_name'], 'settings_type' => $row['settings_type']],
                [
                    'id'         => DB::table('business_settings')
                        ->where('key_name', $row['key_name'])
                        ->where('settings_type', $row['settings_type'])
                        ->value('id') ?? Uuid::uuid4()->toString(),
                    'value'      => is_array($row['value']) ? json_encode($row['value']) : $row['value'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function rows(): array
    {
        return [
            // ── Business Information ──────────────────────────────────────────
            ['key_name' => 'business_name',            'settings_type' => 'business_information', 'value' => 'Vito'],
            ['key_name' => 'business_address',         'settings_type' => 'business_information', 'value' => ''],
            ['key_name' => 'business_contact_phone',   'settings_type' => 'business_information', 'value' => ''],
            ['key_name' => 'business_contact_email',   'settings_type' => 'business_information', 'value' => ''],
            ['key_name' => 'business_support_phone',   'settings_type' => 'business_information', 'value' => ''],
            ['key_name' => 'business_support_email',   'settings_type' => 'business_information', 'value' => ''],
            ['key_name' => 'country_code',             'settings_type' => 'business_information', 'value' => '+1'],
            ['key_name' => 'currency_code',            'settings_type' => 'business_information', 'value' => 'USD'],
            ['key_name' => 'currency_symbol',          'settings_type' => 'business_information', 'value' => '$'],
            ['key_name' => 'currency_symbol_position', 'settings_type' => 'business_information', 'value' => 'left'],
            ['key_name' => 'currency_decimal_point',   'settings_type' => 'business_information', 'value' => '2'],
            ['key_name' => 'copyright_text',           'settings_type' => 'business_information', 'value' => '© ' . date('Y') . ' Vito. All rights reserved.'],
            ['key_name' => 'time_zone',                'settings_type' => 'business_information', 'value' => 'UTC'],
            ['key_name' => 'header_logo',              'settings_type' => 'business_information', 'value' => ''],
            ['key_name' => 'websocket_url',            'settings_type' => 'business_information', 'value' => env('APP_URL', '')],
            ['key_name' => 'websocket_port',           'settings_type' => 'business_information', 'value' => '6015'],

            // ── Core Business Settings ────────────────────────────────────────
            ['key_name' => 'bid_on_fare',                      'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'maintenance_mode',                 'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'driver_self_registration',         'settings_type' => 'business_settings', 'value' => '1'],
            ['key_name' => 'maximum_login_hit',                'settings_type' => 'business_settings', 'value' => '5'],
            ['key_name' => 'temporary_login_block_time',       'settings_type' => 'business_settings', 'value' => '60'],
            ['key_name' => 'maximum_otp_hit',                  'settings_type' => 'business_settings', 'value' => '5'],
            ['key_name' => 'otp_resend_time',                  'settings_type' => 'business_settings', 'value' => '60'],
            ['key_name' => 'customer_verification',            'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'driver_verification',              'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'sms_verification',                 'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'email_verification',               'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'firebase_otp_verification_status', 'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'required_pin_to_start_trip',       'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'add_intermediate_points',          'settings_type' => 'business_settings', 'value' => '1'],
            ['key_name' => 'search_radius',                    'settings_type' => 'business_settings', 'value' => '10000'],
            ['key_name' => 'driver_completion_radius',         'settings_type' => 'business_settings', 'value' => '1000'],
            ['key_name' => 'trip_request_active_time',         'settings_type' => 'business_settings', 'value' => '10'],
            ['key_name' => 'cash_in_hand_setup_status',        'settings_type' => 'business_settings', 'value' => '1'],
            ['key_name' => 'vat_percent',                      'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'driver_otp_confirmation_for_trip', 'settings_type' => 'business_settings', 'value' => '0'],
            ['key_name' => 'facebook_login',                   'settings_type' => 'business_settings', 'value' => ['status' => 0]],
            ['key_name' => 'google_login',                     'settings_type' => 'business_settings', 'value' => ['status' => 0]],

            // ── Trip Settings ─────────────────────────────────────────────────
            ['key_name' => 'enable_real_time_location_sharing', 'settings_type' => 'trip_settings', 'value' => '0'],
            ['key_name' => 'schedule_trip_status',              'settings_type' => 'trip_settings', 'value' => '0'],
            ['key_name' => 'minimum_schedule_book_time',        'settings_type' => 'trip_settings', 'value' => '30'],
            ['key_name' => 'minimum_schedule_book_time_type',   'settings_type' => 'trip_settings', 'value' => 'minute'],
            ['key_name' => 'advance_schedule_book_time',        'settings_type' => 'trip_settings', 'value' => '24'],
            ['key_name' => 'advance_schedule_book_time_type',   'settings_type' => 'trip_settings', 'value' => 'hour'],

            // ── Customer Settings ─────────────────────────────────────────────
            ['key_name' => 'loyalty_points',         'settings_type' => 'customer_settings', 'value' => ['status' => 0, 'points' => 0, 'equivalent_value' => 0, 'minimum_points' => 0]],
            ['key_name' => 'customer_wallet',        'settings_type' => 'customer_settings', 'value' => ['add_fund_status' => 1, 'min_deposit_limit' => 1, 'max_deposit_limit' => 10000]],
            ['key_name' => 'customer_login_options', 'settings_type' => 'customer_settings', 'value' => ['manual_login' => 1, 'otp_login' => 0]],

            // ── Review / Level Flags ──────────────────────────────────────────
            ['key_name' => 'customer_review', 'settings_type' => 'customer_review', 'value' => '1'],
            ['key_name' => 'customer_level',  'settings_type' => 'customer_level',  'value' => '0'],
            ['key_name' => 'driver_review',   'settings_type' => 'driver_review',   'value' => '1'],
            ['key_name' => 'driver_level',    'settings_type' => 'driver_level',    'value' => '0'],

            // ── Driver Settings ───────────────────────────────────────────────
            ['key_name' => 'update_vehicle',        'settings_type' => 'driver_settings', 'value' => '1'],
            ['key_name' => 'update_vehicle_status', 'settings_type' => 'driver_settings', 'value' => '1'],
            ['key_name' => 'loyalty_points',        'settings_type' => 'driver_settings', 'value' => ['status' => 0, 'points' => 0, 'equivalent_value' => 0, 'minimum_points' => 0]],

            // ── Parcel Settings ───────────────────────────────────────────────
            ['key_name' => 'parcel_weight_unit',                'settings_type' => 'parcel_settings', 'value' => 'kg'],
            ['key_name' => 'do_not_charge_customer_return_fee', 'settings_type' => 'parcel_settings', 'value' => '1'],
            ['key_name' => 'parcel_return_time_fee_status',     'settings_type' => 'parcel_settings', 'value' => '0'],
            ['key_name' => 'parcel_refund_status',              'settings_type' => 'parcel_settings', 'value' => '0'],
            ['key_name' => 'parcel_refund_validity',            'settings_type' => 'parcel_settings', 'value' => '0'],
            ['key_name' => 'parcel_refund_validity_type',       'settings_type' => 'parcel_settings', 'value' => 'day'],
            ['key_name' => 'max_parcel_weight_status',          'settings_type' => 'parcel_settings', 'value' => '0'],
            ['key_name' => 'max_parcel_weight',                 'settings_type' => 'parcel_settings', 'value' => '0'],

            // ── Pages ─────────────────────────────────────────────────────────
            ['key_name' => 'about_us',            'settings_type' => 'pages_settings', 'value' => ''],
            ['key_name' => 'privacy_policy',      'settings_type' => 'pages_settings', 'value' => ''],
            ['key_name' => 'refund_policy',       'settings_type' => 'pages_settings', 'value' => ''],
            ['key_name' => 'terms_and_conditions','settings_type' => 'pages_settings', 'value' => ''],
            ['key_name' => 'legal',               'settings_type' => 'pages_settings', 'value' => ''],

            // ── Google Maps ───────────────────────────────────────────────────
            ['key_name' => 'google_map_api', 'settings_type' => 'google_map_api', 'value' => [
                'map_api_key_server'  => '',
                'map_api_key_android' => '',
                'map_api_key_ios'     => '',
            ]],

            // ── App Versions ──────────────────────────────────────────────────
            ['key_name' => 'customer_app_version_control_for_android', 'settings_type' => 'app_version', 'value' => ['minimum_app_version' => '1.0', 'app_url' => '']],
            ['key_name' => 'customer_app_version_control_for_ios',     'settings_type' => 'app_version', 'value' => ['minimum_app_version' => '1.0', 'app_url' => '']],
            ['key_name' => 'driver_app_version_control_for_android',   'settings_type' => 'app_version', 'value' => ['minimum_app_version' => '1.0', 'app_url' => '']],
            ['key_name' => 'driver_app_version_control_for_ios',       'settings_type' => 'app_version', 'value' => ['minimum_app_version' => '1.0', 'app_url' => '']],

            // ── Safety Features (all disabled by default) ─────────────────────
            ['key_name' => 'safety_feature_status',           'settings_type' => 'safety_feature_settings', 'value' => '0'],
            ['key_name' => 'emergency_number_for_call_status', 'settings_type' => 'safety_feature_settings', 'value' => '0'],
            ['key_name' => 'emergency_govt_number_for_call',  'settings_type' => 'safety_feature_settings', 'value' => ''],
            ['key_name' => 'safety_alert_reasons_status',     'settings_type' => 'safety_feature_settings', 'value' => '0'],
            ['key_name' => 'for_trip_delay',                  'settings_type' => 'safety_feature_settings', 'value' => ['minimum_delay_time' => 30, 'time_format' => 'minute']],
            ['key_name' => 'after_trip_complete',             'settings_type' => 'safety_feature_settings', 'value' => ['safety_feature_active_status' => 0, 'set_time' => 5]],
            ['key_name' => 'after_trip_complete_time_format', 'settings_type' => 'safety_feature_settings', 'value' => 'minute'],

            // ── Notification Settings ─────────────────────────────────────────
            ['key_name' => 'push_notification_status', 'settings_type' => 'notification_settings', 'value' => '1'],
        ];
    }
}
