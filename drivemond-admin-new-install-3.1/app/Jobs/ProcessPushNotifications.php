<?php

namespace App\Jobs;

use App\Events\CustomerTripRequestEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Service\Interfaces\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;

class ProcessPushNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $radius;
    protected $trip;
    protected $parcelWeight;
    public function __construct( $radius, $trip, $parcelWeight = null)
    {
        $this->radius = $radius;
        $this->trip = $trip;
        $this->parcelWeight = $parcelWeight;
    }


    public function handle(): void
    {
        $tripRequestService = app()->make(TripRequestServiceInterface::class);
        $tempTripNotificationService = app()->make(TempTripNotificationServiceInterface::class);
        $find_drivers = collect($tripRequestService->findNearestDrivers(
            latitude: (float)(explode(',', $this->trip->coordinate->pickup_coordinates ?? '0,0')[0] ?? 0),
            longitude: (float)(explode(',', $this->trip->coordinate->pickup_coordinates ?? '0,0')[1] ?? 0),
            zoneId: $this->trip->zone_id,
            radius: $this->radius,
            vehicleCategoryId: $this->trip->vehicle_category_id,
            requestType: $this->trip->type,
            rideRequestType: $this->trip->ride_request_type,
            parcelWeight: $this->parcelWeight))->take(100);
        $reverbConnected = checkReverbConnection();

        if (!empty($find_drivers)) {
            $requestType = $this->trip->type == PARCEL ? 'parcel_request' : (
            $this->trip->ride_request_type == SCHEDULED ? 'schedule_trip_request' : 'ride_request'
            );
            $push = getNotification('new_' . $requestType);
            $notification = [
                'title' => $push['title'],
                'description' => $push['description'],
                'status' => $push['status'],
                'ride_request_id' => $this->trip->id,
                'type' => $this->trip->type,
                'notification_type' => $this->trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                'action' => $push['action'],
                'replace' => [
                    'tripId' => $this->trip->ref_id,
                    'parcelId' => $this->trip->parcel_id,
                    'approximateAmount' => getCurrencyFormat($this->trip->estimated_fare),
                    'dropOffLocation' => $this->trip->coordinate->destination_address,
                    'pickUpLocation' => $this->trip->coordinate->pickup_address
                ],
            ];
            foreach ($find_drivers as $data)
            {
                if ($data?->user->fcm_token && $data?->user->is_active)
                {
                    $tempTripNotificationService->create(data: ['user_id' => $data?->user?->id, 'trip_request_id' => $this->trip->id]);
                    sendDeviceNotification(
                        fcm_token: $data->user->fcm_token,
                        title: translate(key: $notification['title'], locale: $data->user?->current_language_key),
                        description: translate(key: $notification['description'], replace: $notification['replace'], locale: $data->user?->current_language_key),
                        status: $notification['status'],
                        image: $notification['image']?? null,
                        ride_request_id: $notification['ride_request_id'] ?? null,
                        type: $notification['type'] ?? null,
                        notification_type: $notification['notification_type'] ?? null,
                        action: $notification['action'] ?? null,
                        user_id: $data->user->id ?? null,
                    );
                }

                $reverbConnected && CustomerTripRequestEvent::broadcast($data->user, $this->trip);
            }
            if (!is_null(businessConfig('server_key', NOTIFICATION_SETTINGS))) {
                sendTopicNotification(
                    'admin_notification',
                    translate('new_request_notification'),
                    translate('new_request_has_been_placed'),
                    'null',
                    $this->trip->id,
                    $this->trip->type);
            }
        }
    }
}
