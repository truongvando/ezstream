<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpsStat extends Model
{
    use HasFactory;

    public $timestamps = false; // We only use created_at

    protected $fillable = [
        'vps_server_id',
        'cpu_load',
        'ram_total_mb',
        'ram_used_mb',
        'disk_total_gb',
        'disk_used_gb',
    ];

    public function vpsServer(): BelongsTo
    {
        return $this->belongsTo(VpsServer::class);
    }
}
