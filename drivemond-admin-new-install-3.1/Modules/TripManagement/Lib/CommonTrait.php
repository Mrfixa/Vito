<?php

namespace Modules\TripManagement\Lib;

use Carbon\Carbon;
use Modules\FareManagement\Service\SurgePricingService;
use Modules\TripManagement\Entities\FareBidding;
use Modules\TripManagement\Entities\TripRequestFee;
use Modules\TripManagement\Entities\TripRequestTime;
use Modules\TripManagement\Service\TripRequestService;

trait CommonTrait
{
    use DiscountCalculationTrait, CouponCalculationTrait;

    public function calculateFinalFare($trip, $fare): array
    {
        $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
        // parcel start
        if ($trip->type == 'parcel') {

            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $actual_fare = $trip->actual_fare / (1 + ($vat_percent / 100));
            $parcel_payment = $actual_fare;
            $vat = round(($vat_percent * $parcel_payment) / 100, 2);
            $fee = TripRequestFee::where('trip_request_id', $trip->id)->first();
            $fee->vat_tax = $vat;
            $fee->admin_commission = (($parcel_payment * $admin_trip_commission) / 100) + $vat;
            $fee->save();

            return [
                'extra_fare_amount' => round($trip->extra_fare_amount, 2),
                'actual_fare' => round($actual_fare, 2),
                'final_fare' => round($parcel_payment + $vat, 2),
                'waiting_fee' => 0,
                'idle_fare' => 0,
                'cancellation_fee' => 0,
                'delay_fee' => 0,
                'vat' => $vat,
                'actual_distance' => $trip->estimated_distance,
            ];
        }

        $fee = TripRequestFee::query()->firstWhere('trip_request_id', $trip->id);
        $time = TripRequestTime::query()->firstWhere('trip_request_id', $trip->id);

        $bid_on_fare = FareBidding::where('trip_request_id', $trip->id)->where('is_ignored', 0)->first();
        $current_status = $trip->current_status;
        $cancellation_fee = 0;
        $waiting_fee = 0;
        $distance_in_km = 0;

        $drivingMode = $trip?->vehicleCategory?->type === 'motor_bike' ? 'TWO_WHEELER' : 'DRIVE';
        $drop_coordinate = [
            explode(',', $trip->coordinate->drop_coordinates ?? '0,0')[0],
            explode(',', $trip->coordinate->drop_coordinates ?? '0,0')[1]
        ];
        $destination_coordinate = [
            explode(',', $trip->coordinate->destination_coordinates ?? '0,0')[0],
            explode(',', $trip->coordinate->destination_coordinates ?? '0,0')[1]
        ];
        $pickup_coordinate = [
            explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[0],
            explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[1]
        ];
        $intermediate_coordinate = [];
        if ($trip->coordinate->is_reached_1) {
            if ($trip->coordinate->is_reached_2) {
                $intermediate_coordinate[1] = [
                    explode(',', $trip->coordinate->int_coordinate_2 ?? '0,0')[0],
                    explode(',', $trip->coordinate->int_coordinate_2 ?? '0,0')[1]
                ];
            }
            $intermediate_coordinate[0] = [
                explode(',', $trip->coordinate->int_coordinate_1 ?? '0,0')[0],
                explode(',', $trip->coordinate->int_coordinate_1 ?? '0,0')[1]
            ];
        }

        if ($current_status === 'cancelled') {
            $route = getRoutes($pickup_coordinate, $drop_coordinate, $intermediate_coordinate, [$drivingMode]);
            $distance_in_km = $route[0]['distance'];

            $distance_wise_fare_cancelled = $fare->base_fare_per_km * $distance_in_km;
            $actual_fare = $fare->base_fare + $distance_wise_fare_cancelled;
            if ($trip->extra_fare_fee > 0) {
                $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
                $actual_fare += $extraFare;
            }

            if ($trip->surge_percentage > 0) {
                $surgeAmount = ($actual_fare * $trip->surge_percentage) / 100;
                $actual_fare += $surgeAmount;
            }

            if ($trip->fee->cancelled_by === 'customer') {
                $cancellation_percent = $fare->cancellation_fee_percent;
                $cancellation_fee = max((($cancellation_percent * $distance_wise_fare_cancelled) / 100), $fare->min_cancellation_fee);
            }
        } elseif ($current_status == 'completed') {
            $route = getRoutes($pickup_coordinate, $drop_coordinate, $intermediate_coordinate, [$drivingMode]);
            $distance_in_km = $route[0]['distance'];

            $distance_wise_fare_completed = $fare->base_fare_per_km * $distance_in_km;
            $actual_fare = $fare->base_fare + $distance_wise_fare_completed;
            if ($trip->extra_fare_fee > 0) {
                $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
                $actual_fare += $extraFare;
            }

            if ($trip->surge_percentage > 0) {
                $surgeAmount = ($actual_fare * $trip->surge_percentage) / 100;
                $actual_fare += $surgeAmount;
            }

            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $distanceFare = $trip->rise_request_count > 0 ? $trip->actual_fare / (1 + ($vat_percent / 100)) : $actual_fare;
            $actual_fare = $bid_on_fare ? $bid_on_fare->bid_fare / (1 + ($vat_percent / 100)) : $distanceFare;
        } else {
            $actual_fare = 0;
        }


        $trip_started = Carbon::parse($trip->tripStatus->ongoing);
        $trip_ended = Carbon::parse($trip->tripStatus->$current_status);
        $actual_time = $trip_started->diffInMinutes($trip_ended);

        //        Idle time & fee calculation
        $idle_fee_buffer = (double)get_cache('idle_fee') ?? 0;
        $idle_diff = $trip->time->idle_time - $idle_fee_buffer;
        $idle_time = max($idle_diff, 0);
        $idle_fee = $idle_time * $fare->idle_fee_per_min;

        //        Delay time & fee calculation
        $delay_fee_buffer = (double)get_cache('delay_fee') ?? 0;
        $delay_diff = $actual_time - ($trip->time->estimated_time + $delay_fee_buffer + $trip->time->idle_time);
        $delay_time = max($delay_diff, 0);
        $delay_fee = $delay_time * $fare->trip_delay_fee_per_min;


        $vat_percent = (double)get_cache('vat_percent') ?? 1;
        $final_fare_without_tax = ($actual_fare + $waiting_fee + $idle_fee + $cancellation_fee + $delay_fee);
        $vat = ($final_fare_without_tax * $vat_percent) / 100;

        $fee->vat_tax = round($vat, 2);
        $fee->admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
        $fee->cancellation_fee = round($cancellation_fee, 2);
        $time->actual_time = $actual_time;
        $time->idle_time = $idle_time;
        $fee->idle_fee = round($idle_fee, 2);
        $time->delay_time = $delay_time;
        $fee->delay_fee = round($delay_fee, 2);
        $fee->save();
        $time->save();

        return [
            'extra_fare_amount' => round($extraFare ?? 0, 2),
            'actual_fare' => round($actual_fare, 2),
            'final_fare' => round($final_fare_without_tax + $vat, 2),
            'waiting_fee' => $waiting_fee,
            'idle_fare' => $idle_fee,
            'cancellation_fee' => $cancellation_fee,
            'delay_fee' => $delay_fee,
            'vat' => $vat,
            'actual_distance' => $distance_in_km
        ];
    }


    public function estimatedFare($tripRequest, $routes, $zone_id, $zone, $tripFare = null, $area_id = null, $beforeCreate = false): mixed
    {

        $surgePriceService = app()->make(SurgePricingService::class);
        if ($tripRequest['type'] == 'parcel') {
            abort_if(boolean: empty($tripFare), code: 403, message: translate('invalid_or_missing_information'));
            abort_if(boolean: empty($tripFare->fares), code: 403, message: translate('no_fares_found'));
            $user = auth('api')->user();
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $points = (int)getSession('currency_decimal_point') ?? 0;
            $extraFare = $this->checkZoneExtraFare($zone);
            $surgePrice = $surgePriceService->checkSurgePricing(zoneId: $zone->id, tripType: $tripRequest['type']);
            $extraDiscount = null;
            $distance_wise_fare = $tripFare->fares[0]->fare_per_km * $routes[0]['distance'];
            $est_fare = $tripFare->fares[0]->base_fare + $distance_wise_fare;
            $extraEstFareAmount = $surgePriceAmount = 0;
            if (!empty($extraFare)) {
                $extraEstFareAmount = ($est_fare * $extraFare['extraFareFee']) / 100;
            }
            if (!empty($surgePrice))
            {
                $surgePriceAmount = ($est_fare * $surgePrice['surge_multiplier']) / 100;
            }
            $extraEstFare = $est_fare + $extraEstFareAmount + $surgePriceAmount;
            $returnFee = ($est_fare * $tripFare->fares[0]->return_fee) / 100;
            $cancellationFee = ($est_fare * $tripFare->fares[0]->cancellation_fee) / 100;

            $discount = $this->getEstimatedDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $est_fare, beforeCreate: $beforeCreate);
            $discountEstFare = $est_fare - ($discount ? $discount['discount_amount'] : 0);
            $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $discountEstFare);

            if (!empty($extraFare) || !empty($surgePrice)) {
                $extraDiscount = $this->getEstimatedDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $extraEstFare, beforeCreate: $beforeCreate);
                $extraDiscountEstFare = $extraEstFare - ($extraDiscount ? $extraDiscount['discount_amount'] : 0);
                $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $extraDiscountEstFare);
                $extraDiscountFareVat = ($extraDiscountEstFare * $vat_percent) / 100;
                $extraDiscountEstFare += $extraDiscountFareVat;
                $extraVat = ($extraEstFare * $vat_percent) / 100;
                $extraEstFare += $extraVat;
                $extraReturnFee = ($extraEstFare * $tripFare->fares[0]->return_fee) / 100;
                $extraCancellationFee = ($extraEstFare * $tripFare->fares[0]->cancellation_fee) / 100;
            }
            $discountFareVat = ($discountEstFare * $vat_percent) / 100;
            $discountEstFare += $discountFareVat;
            $vat = ($est_fare * $vat_percent) / 100;
            $est_fare += $vat;
            $reason = '';

            if (!empty($surgePrice) && !empty($surgePrice['surge_pricing_customer_note'])) {
                $surgeReason = strtolower(str_replace('.', '', '_' . $surgePrice['surge_pricing_customer_note']));
                $reason .= $surgeReason;
            }

            if (!empty($extraFare) && !empty($extraFare['extraFareReason'])) {
                $extraReason = strtolower($extraFare['extraFareReason']);
                $reason .= ($reason ? ' and ' : '') . '_' . $extraReason;
            }

            $estimated_fare = [
                'id' => $tripFare->id,
                'zone_id' => $zone->id,
                'area_id' => $area_id,
                'base_fare' => $tripFare->base_fare,
                'base_fare_per_km' => $tripFare->base_fare_per_km,
                'fare' => $tripFare->fares,
                'estimated_distance' => (double)$routes[0]['distance'],
                'estimated_duration' => $routes[0]['duration'],
                'estimated_fare' => round($est_fare, $points),
                'discount_fare' => round($discountEstFare, $points),
                'discount_amount' => round(($discount ? $discount['discount_amount'] : 0), $points),
                'coupon_applicable' => $coupon,
                'request type' => $tripRequest['type'],
                'encoded_polyline' => $routes[0]['encoded_polyline'],
                'return_fee' => $returnFee,
                'cancellation_fee' => $cancellationFee,
                'extra_estimated_fare' => round($extraEstFare ?? 0, $points),
                'extra_discount_fare' => round($extraDiscountEstFare ?? 0, $points),
                'extra_discount_amount' => round(($extraDiscount ? $extraDiscount['discount_amount'] : 0), $points),
                'extra_return_fee' => $extraReturnFee ?? 0,
                'extra_cancellation_fee' => $extraCancellationFee ?? 0,
                'extra_fare_amount' => round(($extraEstFareAmount ?? 0), $points),
                'extra_fare_fee' => $extraFare ? $extraFare['extraFareFee'] : 0,
                'extra_fare_reason' => $reason ? translate($reason) : '',
                'surge_multiplier' => $surgePrice['surge_multiplier'] ?? 0,
            ];

        } else {
            $scheduleTripPercentage = businessConfig('schedule_trip_status')?->value && businessConfig('increase_fare')?->value && (businessConfig('increase_fare_amount')?->value > 0) ? businessConfig('increase_fare_amount')?->value : 0;
            $estimated_fare = $tripFare->map(function ($trip) use ($routes, $tripRequest, $area_id, $beforeCreate, $zone, $scheduleTripPercentage, $surgePriceService) {
                $user = auth('api')->user();
                $extraFare = $this->checkZoneExtraFare($zone);
                $surgePrice = $surgePriceService->checkSurgePricing(zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicle_category_id, scheduledAt: $tripRequest['scheduled_at']);
                $points = (int)getSession('currency_decimal_point') ?? 0;
                $vat_percent = (double)get_cache('vat_percent') ?? 1;
                $baseFarePerKm = $trip->base_fare_per_km;
                $baseFare = $trip->base_fare;
                $extraDiscount = null;
                if ($tripRequest['ride_request_type'] == 'scheduled') {
                    $baseFarePerKm = $trip->base_fare_per_km + ($trip->base_fare_per_km * $scheduleTripPercentage / 100);
                    $baseFare = $trip->base_fare + ($trip->base_fare * $scheduleTripPercentage / 100);
                }
                foreach ($routes as $route) {
                    if ($route['drive_mode'] === 'DRIVE') {
                        $distance = $route['distance'];
                        $drive_fare = $baseFarePerKm * $distance;
                        $drive_est_distance = (double)$routes[0]['distance'];
                        $drive_est_duration = $route['duration'];
                        $drive_polyline = $route['encoded_polyline'];
                    } elseif ($route['drive_mode'] === 'TWO_WHEELER') {
                        $distance = $route['distance'];
                        $bike_fare = $baseFarePerKm * $distance;
                        $bike_est_distance = (double)$routes[0]['distance'];
                        $bike_est_duration = $route['duration'];
                        $bike_polyline = $route['encoded_polyline'];
                    }
                }

                $est_fare = $trip->vehicleCategory->type === 'car' ? round(($baseFare + $drive_fare), $points) : round(($baseFare + $bike_fare), $points);
                $extraEstFareAmount = $surgePriceAmount = 0;
                if (!empty($extraFare))
                {
                    $extraEstFareAmount = ($est_fare * $extraFare['extraFareFee']) / 100;
                }

                if (!empty($surgePrice))
                {
                    $surgePriceAmount = ($est_fare * $surgePrice['surge_multiplier']) / 100;
                }
                $extraEstFare = $est_fare + $extraEstFareAmount + $surgePriceAmount;
                $discount = $this->getEstimatedDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $est_fare, beforeCreate: $beforeCreate);
                $discountEstFare = $est_fare - ($discount ? $discount['discount_amount'] : 0);
                $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $discountEstFare);

                if (!empty($extraFare) || !empty($surgePrice)) {
                    $extraDiscount = $this->getEstimatedDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $extraEstFare, beforeCreate: $beforeCreate);
                    $extraDiscountEstFare = $extraEstFare - ($extraDiscount ? $extraDiscount['discount_amount'] : 0);
                    $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $extraDiscountEstFare);
                    $extraDiscountFareVat = ($extraDiscountEstFare * $vat_percent) / 100;
                    $extraDiscountEstFare += $extraDiscountFareVat;
                    $extraVat = ($extraEstFare * $vat_percent) / 100;
                    $extraEstFare += $extraVat;
                }
                $discountFareVat = ($discountEstFare * $vat_percent) / 100;
                $discountEstFare += $discountFareVat;
                $vat = ($est_fare * $vat_percent) / 100;
                $est_fare += $vat;
                $reason = '';

                if (!empty($surgePrice) && !empty($surgePrice['surge_pricing_customer_note'])) {
                    $surgeReason = strtolower(str_replace('.', '', '_' . $surgePrice['surge_pricing_customer_note']));
                    $reason .= $surgeReason;
                }

                if (!empty($extraFare) && !empty($extraFare['extraFareReason'])) {
                    $extraReason = strtolower($extraFare['extraFareReason']);
                    $reason .= ($reason ? ' and ' : '') . '_' . $extraReason;
                }

                return [
                    "id" => $trip->id,
                    "zone_id" => $zone->id,
                    'area_id' => $area_id,
                    "vehicle_category_id" => $trip->vehicle_category_id,
                    'base_fare' => $baseFare,
                    'base_fare_per_km' => $baseFarePerKm,
                    'fare' => $trip->VehicleCategory->type === 'car' ? round($drive_fare, 2) : round($bike_fare, 2),
                    'estimated_distance' => $trip->VehicleCategory->type === 'car' ? $drive_est_distance : $bike_est_distance,
                    'estimated_duration' => $trip->VehicleCategory->type === 'car' ? $drive_est_duration : $bike_est_duration,
                    'vehicle_category_type' => $trip->VehicleCategory->type === 'car' ? 'Car' : 'Motorbike',
                    'estimated_fare' => round($est_fare, $points),
                    'discount_fare' => round($discountEstFare, $points),
                    'discount_amount' => round(($discount ? $discount['discount_amount'] : 0), $points),
                    'coupon_applicable' => $coupon,
                    'request_type' => $tripRequest['type'],
                    'ride_request_type' => $tripRequest['ride_request_type'] ?? null,
                    'encoded_polyline' => $trip->VehicleCategory->type === 'car' ? $drive_polyline : $bike_polyline,
                    'return_fee' => 0,
                    'extra_estimated_fare' => round($extraEstFare ?? 0, $points),
                    'extra_discount_fare' => round($extraDiscountEstFare ?? 0, $points),
                    'extra_discount_amount' => round(($extraDiscount ? $extraDiscount['discount_amount'] : 0), $points),
                    'extra_return_fee' => 0,
                    'extra_cancellation_fee' => 0,
                    'extra_fare_amount' => round(($extraEstFareAmount ?? 0), $points),
                    'extra_fare_fee' => $extraFare ? $extraFare['extraFareFee'] : 0,
                    'extra_fare_reason' => $reason ? translate($reason) : '',
                    'surge_multiplier' => $surgePrice['surge_multiplier'] ?? 0,
                ];
            });
        }

        return $estimated_fare;
    }

    public function checkZoneExtraFare($zone)
    {
        $extraFareFee = 0;
        $extraFareReason = "";
        if ($zone->extra_fare_status) {
            $extraFareFee = $zone->extra_fare_fee;
            $extraFareReason = $zone->extra_fare_reason;
        }
        if ($extraFareFee > 0) {
            return [
                'extraFareFee' => $extraFareFee,
                'extraFareReason' => $extraFareReason,
            ];
        }
        return [];
    }

}

