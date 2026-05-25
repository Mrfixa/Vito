<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripRequestCoordinate extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_request_id',
        'pickup_coordinates',
        'pickup_address',
        'destination_coordinates',
        'is_reached_destination',
        'destination_address',
        'intermediate_coordinates',
        'int_coordinate_1',
        'is_reached_1',
        'int_coordinate_2',
        'is_reached_2',
        'intermediate_addresses',
        'start_coordinates',
        'drop_coordinates',
        'driver_accept_coordinates',
        'customer_request_coordinates',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'pickup_coordinates' => 'string',
        'destination_coordinates' => 'string',
        'start_coordinates' => 'string',
        'drop_coordinates' => 'string',
        'driver_accept_coordinates' => 'string',
        'customer_request_coordinates' => 'string',
        'int_coordinate_1' => 'string',
        'int_coordinate_2' => 'string',
        'intermediate_coordinates' => 'array',
        'intermediate_addresses' => 'array',
        'is_reached_destination' => 'boolean',
        'is_reached_1' => 'boolean',
        'is_reached_2' => 'boolean'
    ];

    public function tripRequest()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    protected static function newFactory()
    {
        return \Modules\TripManagement\Database\factories\TripRequestCoordinateFactory::new();
    }
}
