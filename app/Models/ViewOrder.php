<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'api_service_id',
        'link',
        'quantity',
        'total_amount',
        'api_order_id',
        'status',
        'api_response'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'api_response' => 'array',
    ];

    /**
     * Relationship with user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with API service
     */
    public function apiService()
    {
        return $this->belongsTo(ApiService::class);
    }

    /**
     * Relationship with transaction
     */
    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Check if order can be refilled
     */
    public function canRefill()
    {
        // Check if service supports refill from name
        $serviceName = $this->service_id; // This should be service name or we need to store it
        return stripos($serviceName, 'refill') !== false &&
               stripos($serviceName, '[Refill: No]') === false;
    }

    /**
     * Check if order can be cancelled
     */
    public function canCancel()
    {
        // Cannot cancel if already completed, failed, or refunded
        if (in_array($this->status, ['COMPLETED', 'FAILED', 'REFUNDED', 'CANCELLED'])) {
            return false;
        }

        // Can cancel if pending or processing
        return in_array($this->status, ['PENDING', 'PROCESSING', 'PENDING_FUNDS', 'PENDING_RETRY']);
    }

    /**
     * Process refund to user balance
     */
    public function processRefund($reason = 'Order cancelled')
    {
        if ($this->status === 'REFUNDED') {
            return false; // Already refunded
        }

        try {
            // Add money back to user balance
            $this->user->increment('balance', $this->total_amount);

            // Update order status
            $this->update([
                'status' => 'REFUNDED',
                'api_response' => array_merge($this->api_response ?? [], [
                    'refund_reason' => $reason,
                    'refunded_at' => now()->toISOString(),
                    'refunded_amount' => $this->total_amount
                ])
            ]);

            // Log the refund
            \Log::info('Order refunded', [
                'order_id' => $this->id,
                'user_id' => $this->user_id,
                'amount' => $this->total_amount,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Refund failed', [
                'order_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Scope for completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }
}
