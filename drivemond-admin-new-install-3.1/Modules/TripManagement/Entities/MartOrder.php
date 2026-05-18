<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Entities\User;

class MartOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'ref_id',
        'customer_id',
        'driver_id',
        'status',
        'total_amount',
        'payment_status',
        'payment_method',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'signature_image',
        'delivery_photo',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_lat' => 'decimal:7',
        'delivery_lng' => 'decimal:7',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items()
    {
        return $this->hasMany(MartOrderItem::class, 'order_id');
    }
}
