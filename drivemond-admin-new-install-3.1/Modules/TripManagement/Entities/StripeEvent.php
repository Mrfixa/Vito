<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Entities\User;

class StripeEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'user_id',
        'amount',
        'currency',
        'status',
        'payment_intent_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
