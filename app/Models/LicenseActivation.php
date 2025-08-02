<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'device_id',
        'device_name',
        'device_info',
        'ip_address',
        'user_agent',
        'activated_at'
    ];

    protected $casts = [
        'device_info' => 'array',
        'activated_at' => 'datetime',
    ];

    /**
     * Relationship with license
     */
    public function license()
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Scope for recent activations
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('activated_at', '>=', now()->subDays($days));
    }

    /**
     * Scope by device
     */
    public function scopeByDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
}
