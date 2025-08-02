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
     * Scope for completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }
}
