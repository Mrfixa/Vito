<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AdminModule\Entities\ActivityLog;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\ChattingManagement\Entities\ChannelConversation;
use Modules\ChattingManagement\Entities\ChannelList;
use Modules\ParcelManagement\Entities\ParcelInformation;
use Modules\ParcelManagement\Entities\ParcelUserInfomation;
use Modules\PromotionManagement\Entities\CouponSetup;
use Modules\PromotionManagement\Entities\DiscountSetup;
use Modules\ReviewModule\Entities\Review;
use Modules\UserManagement\Entities\DriverDetail;
use Modules\UserManagement\Entities\TimeTrack;
use Modules\UserManagement\Entities\User;
use Modules\VehicleManagement\Entities\Vehicle;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\ZoneManagement\Entities\Zone;

class TripRequest extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'ref_id',
        'customer_id',
        'driver_id',
        'vehicle_category_id',
        'vehicle_id',
        'zone_id',
        'area_id',
        'estimated_fare',
        'actual_fare',
        'estimated_distance',
        'paid_fare',
        'return_fee',
        'cancellation_fee',
        'extra_fare_fee',
        'extra_fare_amount',
        'surge_percentage',
        'return_time',
        'due_amount',
        'actual_distance',
        'encoded_polyline',
        'accepted_by',
        'payment_method',
        'payment_status',
        'coupon_id',
        'coupon_amount',
        'discount_id',
        'discount_amount',
        'note',
        'pickup_note',
        'delivery_notes',
        'entrance',
        'otp',
        'rise_request_count',
        'type',
        'ride_request_type',
        'scheduled_at',
        'current_status',
        'is_notification_sent',
        'sending_notification_at',
        'trip_cancellation_reason',
        'checked',
        'tips',
        'is_paused',
        'map_screenshot',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'estimated_fare' => 'float',
        'actual_fare' => 'float',
        'return_fee' => 'float',
        'cancellation_fee' => 'float',
        'extra_fare_fee' => 'float',
        'extra_fare_amount' => 'float',
        'due_amount' => 'float',
        'estimated_time' => 'float',
        'estimated_distance' => 'float',
        'paid_fare' => 'float',
        "actual_time" => 'float',
        "actual_distance" => 'float',
        "waiting_time" => 'float',
        "idle_time" => 'float',
        "waiting_fare" => 'float',
        "idle_fare" => 'float',
        "vat_tax" => 'float',
        "additional_charge" => 'float',
        "coupon_amount" => 'float',
        "discount_amount" => 'float',
        "total_fare" => 'float',
        "is_paused" => 'boolean',
        "rise_request_count" => 'integer',
        "is_notification_sent" => 'boolean',
    ];

    protected static function newFactory()
    {
        return \Modules\TripManagement\Database\factories\TripRequestFactory::new();
    }

    public function channel(): MorphOne
    {
        return $this->morphOne(ChannelList::class, 'channelable');
    }

    public function conversations(): MorphMany
    {
        return $this->morphMany(ChannelConversation::class, 'convable');
    }

    public function fare_biddings()
    {
        return $this->hasMany(FareBidding::class, 'trip_request_id');
    }

    public function tripRoutes()
    {
        return $this->hasMany(TripRoute::class);
    }

    public function tripStatus()
    {
        return $this->hasOne(TripStatus::class, 'trip_request_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vehicleCategory()
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id')->withTrashed();
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function coupon()
    {
        return $this->belongsTo(CouponSetup::class, 'coupon_id');
    }

    public function discount()
    {
        return $this->belongsTo(DiscountSetup::class, 'discount_id');
    }

    public function customerReceivedReview()
    {
        return $this->hasOne(Review::class, 'trip_request_id', 'id')->where('received_by', $this->customer_id);
    }

    public function driverReceivedReview()
    {
        return $this->hasOne(Review::class, 'trip_request_id', 'id')->where('received_by', $this->driver_id);
    }

    public function customerReceivedReviews()
    {
        return $this->hasMany(Review::class, 'received_by', 'customer_id');
    }

    public function driverReceivedReviews()
    {
        return $this->hasMany(Review::class, 'received_by', 'driver_id');
    }

    public function ignoredRequests()
    {
        return $this->hasOne(RejectedDriverRequest::class, 'trip_request_id');
    }

    public function coordinate()
    {
        return $this->hasOne(TripRequestCoordinate::class, 'trip_request_id');
    }

    public function fee()
    {
        return $this->hasOne(TripRequestFee::class, 'trip_request_id');
    }

    public function parcelRefund()
    {
        return $this->hasOne(ParcelRefund::class, 'trip_request_id');
    }

    public function time()
    {
        return $this->hasOne(TripRequestTime::class, 'trip_request_id');
    }

    public function parcel()
    {
        return $this->hasOne(ParcelInformation::class, 'trip_request_id');
    }

    public function parcelUserInfo()
    {
        return $this->hasMany(ParcelUserInfomation::class, 'trip_request_id');
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function distance_wise_fare()
    {
        return $this->actual_fare;
    }

    public function getDiscountActualFareAttribute()
    {
        $totalFare = $this->actual_fare;

        if ($this->discount_amount > 0 || $this->coupon_amount > 0) {
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $discountReduceFare = $totalFare - ($this->discount_amount ?? 0) - ($this->coupon_amount ?? 0);
            $vat = round(($vat_percent * $discountReduceFare) / 100, 2);
            $totalFare = $discountReduceFare + $vat;
        }
        return round((double)$totalFare, 2);
    }

    public function getMapScreenshotAttribute($value)
    {
        if ($value) {
            return dynamicStorage('storage/app/public/trip/screenshots') . '/' . $value;
        }
        return null;
    }

    public function logs()
    {
        return $this->morphMany(ActivityLog::class, 'logable');
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $item->ref_id = $item->withTrashed()->count() + 100000;
        });

        static::updated(function ($item) {
            $array = [];
            foreach ($item->changes as $key => $change) {
                if (array_key_exists($key, $item->original)) {
                    $array[$key] = $item->original[$key];
                }
            }
            if (!empty($array)) {
                $log = new ActivityLog();
                $log->edited_by = auth()->user()->id ?? 'user_update';
                $log->before = $array;
                $log->after = $item->changes;
                $item->logs()->save($log);
            }
            if ($item->current_status == CANCELLED) {
                if ($item->type == PARCEL) {
                    $message = 'a_parcel_request_is_cancelled';
                } else {
                    $message = 'a_trip_request_is_cancelled';
                }
                $notification = new AdminNotification();
                $notification->model = 'trip_request';
                $notification->model_id = $item->id;
                $notification->message = $message;
                $notification->save();
            }
            if ($item->driver_id && $item->isDirty('current_status')) {
                $track = TimeTrack::query()
                    ->where(['user_id' => $item->driver_id, 'date' => date('Y-m-d')])
                    ->latest()->first();

                if (!$track) {
                    $track = TimeTrack::query()
                        ->where(['user_id' => $item->driver_id])
                        ->latest()->first();
                }
                $driver = DriverDetail::query()->firstWhere('user_id', $item->driver_id);

                if ($item->current_status == OUT_FOR_PICKUP || $item->current_status == ACCEPTED) {
                    if ($item->type == RIDE_REQUEST && $item->ride_request_type == 'regular') {
                        $driver->ride_count += 1;
                    }
                    if ($item->type == PARCEL) {
                        $driver->parcel_count += 1;
                    }
                    $driver->availability_status = 'available';
                    $driver->save();

                    $duration = Carbon::parse($track->last_ride_completed_at)->diffInMinutes();
                    $track->last_ride_started_at = now();
                    $track->total_idle += $duration;
                    $track->save();
                } elseif ($item->current_status == COMPLETED || $item->current_status == CANCELLED) {
                    $driver->availability_status = 'available';
                    $driver->save();
                    $duration = Carbon::parse($track->last_ride_started_at)->diffInMinutes();
                    $track->last_ride_completed_at = now();
                    $track->total_driving += $duration;
                    $track->save();
                }
            }
        });

        static::deleted(function ($item) {
            $log = new ActivityLog();
            $log->edited_by = auth()->user()->id;
            $log->before = $item->original;
            $item->logs()->save($log);
        });


    }


    public function safetyAlerts() {
        return $this->hasMany(SafetyAlert::class, 'trip_request_id');
    }

    public function customerSafetyAlertPending()
    {
        return $this->hasOne(SafetyAlert::class, 'trip_request_id', 'id')->where('status', PENDING)->where('sent_by', $this->customer_id);
    }

    public function driverSafetyAlertPending()
    {
        return $this->hasOne(SafetyAlert::class, 'trip_request_id', 'id')->where('status', PENDING)->where('sent_by', $this->driver_id);
    }

    public function customerSafetyAlert()
    {
        return $this->hasOne(SafetyAlert::class, 'trip_request_id', 'id')->where('sent_by', $this->customer_id);
    }

    public function driverSafetyAlert()
    {
        return $this->hasOne(SafetyAlert::class, 'trip_request_id', 'id')->where('sent_by', $this->driver_id);
    }

    public function tempNotifications()
    {
        return $this->hasMany(TempTripNotification::class, 'trip_request_id');
    }

    public function lateReturnPenaltyNotification()
    {
        return $this->hasOne(LateReturnPenaltyNotification::class, 'trip_request_id');
    }

    public function tripNavigation()
    {
        return $this->hasOne(TripNavigation::class, 'trip_request_id');
    }
}
