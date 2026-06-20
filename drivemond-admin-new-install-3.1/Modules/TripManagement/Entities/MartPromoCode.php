<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MartPromoCode extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount',
        'usage_limit',
        'per_user_limit',
        'used_count',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }

    public function computeDiscount(float $subtotal): float
    {
        if ($subtotal < $this->min_order_amount) {
            return 0.0;
        }

        // Guard against misconfigured promos: a negative discount_value would
        // otherwise inflate the order total (overcharging the customer).
        if ((float) $this->discount_value <= 0) {
            return 0.0;
        }

        $discount = $this->discount_type === 'percent'
            ? $subtotal * ($this->discount_value / 100)
            : (float) $this->discount_value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }
}
