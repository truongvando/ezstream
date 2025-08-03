<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ScheduledOrder extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'link',
        'quantity',
        'total_amount',
        'scheduled_at',
        'is_repeat',
        'repeat_interval_hours',
        'max_repeats',
        'completed_repeats',
        'status',
        'service_data',
        'last_order_response',
        'last_executed_at',
        'next_execution_at'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'last_executed_at' => 'datetime',
        'next_execution_at' => 'datetime',
        'service_data' => 'array',
        'last_order_response' => 'array',
        'is_repeat' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeReadyToExecute($query)
    {
        return $query->where('status', 'PENDING')
                    ->where(function($q) {
                        $q->where('scheduled_at', '<=', now())
                          ->orWhere('next_execution_at', '<=', now());
                    });
    }

    public function canRepeat(): bool
    {
        return $this->is_repeat &&
               $this->completed_repeats < $this->max_repeats;
    }

    public function calculateNextExecution(): ?Carbon
    {
        if (!$this->canRepeat()) {
            return null;
        }

        return $this->last_executed_at
            ? $this->last_executed_at->addHours($this->repeat_interval_hours)
            : $this->scheduled_at->addHours($this->repeat_interval_hours);
    }

    public function markExecuted()
    {
        $this->completed_repeats++;
        $this->last_executed_at = now();

        if ($this->canRepeat()) {
            $this->next_execution_at = $this->calculateNextExecution();
        } else {
            $this->status = 'COMPLETED';
            $this->next_execution_at = null;
        }

        $this->save();
    }
}
