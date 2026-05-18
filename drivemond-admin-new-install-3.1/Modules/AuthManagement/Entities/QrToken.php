<?php

namespace Modules\AuthManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Entities\User;

class QrToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'token',
        'role',
        'created_by',
        'redeemed_by',
        'redeemed_at',
        'expires_at',
        'is_revoked',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redeemer()
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function isValid(): bool
    {
        return !$this->is_revoked
            && !$this->redeemed_at
            && $this->expires_at->isFuture();
    }
}
