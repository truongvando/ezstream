<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tool_id',
        'license_key',
        'device_id',
        'device_name',
        'device_info',
        'is_active',
        'activated_at',
        'expires_at',
        'license_type',
        'auto_renew',
        'last_used_at'
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Relationship with user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with tool
     */
    public function tool()
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * Relationship with activations
     */
    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }

    /**
     * Generate a unique license key
     */
    public function generateKey()
    {
        return strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . 
                          Str::random(4) . '-' . Str::random(4));
    }

    /**
     * Check if license is expired
     */
    public function getIsExpiredAttribute()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if license is demo
     */
    public function getIsDemoAttribute()
    {
        return $this->license_type === 'DEMO';
    }

    /**
     * Check if license needs renewal
     */
    public function getIsNearExpiryAttribute()
    {
        if (!$this->expires_at) return false;

        return $this->expires_at->diffInDays(now()) <= 7;
    }

    /**
     * Get license status
     */
    public function getStatusAttribute()
    {
        if (!$this->is_active) return 'INACTIVE';
        if ($this->is_expired) return 'EXPIRED';
        if ($this->is_demo && $this->is_near_expiry) return 'DEMO_EXPIRING';
        if ($this->is_demo) return 'DEMO_ACTIVE';
        if ($this->is_near_expiry) return 'EXPIRING_SOON';

        return 'ACTIVE';
    }

    /**
     * Get days remaining
     */
    public function getDaysRemainingAttribute()
    {
        if (!$this->expires_at) return null;

        $days = $this->expires_at->diffInDays(now(), false);
        return $days > 0 ? $days : 0;
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if license is activated
     */
    public function getIsActivatedAttribute()
    {
        return !is_null($this->activated_at);
    }

    /**
     * Scope for active licenses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for expired licenses
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
