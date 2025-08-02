<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ToolOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tool_id',
        'amount',
        'status',
        'transaction_id',
        'license_key'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
     * Relationship with transaction
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Relationship with license
     */
    public function license()
    {
        return $this->hasOne(License::class, 'license_key', 'license_key');
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
