<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpsStat extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // Only use created_at, not updated_at

    protected $fillable = [
        'vps_server_id',
        'cpu_usage_percent',
        'ram_usage_percent', 
        'disk_usage_percent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'cpu_usage_percent' => 'float',
        'ram_usage_percent' => 'float',
        'disk_usage_percent' => 'float',
    ];

    public function vpsServer(): BelongsTo
    {
        return $this->belongsTo(VpsServer::class);
    }
}
