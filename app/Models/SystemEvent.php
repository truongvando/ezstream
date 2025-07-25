<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated This model is deprecated. System events are now handled via Redis agent reports.
 * Use SystemEventMonitor component which reads from Redis instead.
 */
class SystemEvent extends Model
{
    use HasFactory;

    protected $table = 'system_events';

    public $timestamps = false; // We only use created_at

    protected $fillable = [
        'level',
        'type',
        'message',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($event) {
            if (empty($event->created_at)) {
                $event->created_at = now();
            }
        });
    }
}
