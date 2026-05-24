<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_request_id',
        'coordinates',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'coordinates' => 'string'
    ];

    protected static function newFactory()
    {
        return \Modules\TripManagement\Database\factories\TripRouteFactory::new();
    }

    public function tripRequest()
    {
        return $this->belongsTo(TripRequest::class);
    }
}
