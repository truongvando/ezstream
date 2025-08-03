<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MmoOrder extends Model
{
    protected $fillable = [
        'user_id',
        'mmo_service_id',
        'transaction_id',
        'order_code',
        'amount',
        'currency',
        'status',
        'customer_requirements',
        'admin_notes',
        'delivery_notes',
        'delivery_files',
        'completed_at',
        'cancelled_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'customer_requirements' => 'array',
        'delivery_files' => 'array',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mmoService(): BelongsTo
    {
        return $this->belongsTo(MmoService::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'PROCESSING');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'PENDING' => 'bg-yellow-100 text-yellow-800',
            'PROCESSING' => 'bg-blue-100 text-blue-800',
            'COMPLETED' => 'bg-green-100 text-green-800',
            'CANCELLED' => 'bg-gray-100 text-gray-800',
            'REFUNDED' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['PENDING', 'PROCESSING']);
    }

    public function canComplete(): bool
    {
        return in_array($this->status, ['PENDING', 'PROCESSING']);
    }

    public function canRefund(): bool
    {
        return $this->status === 'COMPLETED';
    }
}
