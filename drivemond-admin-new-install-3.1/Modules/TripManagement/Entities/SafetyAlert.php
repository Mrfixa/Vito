<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gateways\Traits\HasUuid;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLastLocation;

class SafetyAlert extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'trip_request_id',
        'sent_by',
        'alert_location',
        'resolved_location',
        'number_of_alert',
        'resolved_by',
        'trip_status_when_make_alert',
        'status',
        'reason',
        'comment',
    ];

    protected $casts = [
        'reason' => 'array',
    ];

    public function sentBy() {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function solvedBy() {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function trip()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    public function lastLocation()
    {
        return $this->belongsTo(UserLastLocation::class, 'sent_by', 'user_id');
    }
}
