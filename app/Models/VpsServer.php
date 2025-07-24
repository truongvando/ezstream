<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VpsServer extends Model
{
    /** @use HasFactory<\Database\Factories\VpsServerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'ip_address',
        'ssh_user',
        'ssh_password',
        'ssh_port',
        'is_active',
        'description',
        'status',
        'status_message',
        'provision_token',
        'provisioned_at',
        'cpu_cores',
        'ram_gb',
        'disk_gb',
        'bandwidth_gb',
        'capabilities',
        'max_concurrent_streams',
        'current_streams',
        'last_seen_at',
        'last_heartbeat_at',
        'last_provisioned_at',
        'error_message',
        'webhook_configured',
        'webhook_url',
        'last_webhook_setup',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'ssh_password',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'ssh_port' => 'integer',
        'provisioned_at' => 'datetime',
        'capabilities' => 'array',
        'max_concurrent_streams' => 'integer',
        'current_streams' => 'integer',
        'last_seen_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_provisioned_at' => 'datetime',
        'webhook_configured' => 'boolean',
        'last_webhook_setup' => 'datetime',
    ];

    /**
     * Tự động mã hóa mật khẩu SSH khi thiết lập
     */
    public function setSshPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['ssh_password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Giải mã mật khẩu SSH khi truy cập
     */
    public function getSshPasswordAttribute($value)
    {
        if (!empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value; // Trả về giá trị gốc nếu không giải mã được
            }
        }
        return null;
    }

    /**
     * Get active VPS servers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all of the stats for the VpsServer.
     */
    public function stats(): HasMany
    {
        return $this->hasMany(VpsStat::class);
    }

    /**
     * Get the latest stat for the VpsServer.
     */
    public function latestStat(): HasOne
    {
        return $this->hasOne(VpsStat::class)->latestOfMany();
    }

    /**
     * Get all of the stream configurations for the VpsServer.
     */
    public function streamConfigurations(): HasMany
    {
        return $this->hasMany(StreamConfiguration::class);
    }
}
