<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiService extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'type',
        'category',
        'rate',
        'min_quantity',
        'max_quantity',
        'refill',
        'cancel',
        'markup_percentage',
        'is_active'
    ];

    protected $casts = [
        'refill' => 'boolean',
        'cancel' => 'boolean',
        'is_active' => 'boolean',
        'rate' => 'decimal:2',
    ];

    /**
     * Get the final price with markup
     */
    public function getFinalPriceAttribute()
    {
        return $this->rate * (1 + $this->markup_percentage / 100);
    }

    /**
     * Relationship with view orders
     */
    public function viewOrders()
    {
        return $this->hasMany(ViewOrder::class);
    }

    /**
     * Scope for active services
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
