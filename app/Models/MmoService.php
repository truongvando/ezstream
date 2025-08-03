<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MmoService extends Model
{
    protected $fillable = [
        'name',
        'description',
        'detailed_description',
        'price',
        'currency',
        'category',
        'features',
        'delivery_time',
        'image_url',
        'is_active',
        'is_featured',
        'sort_order',
        'requirements',
        'notes'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'requirements' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean'
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(MmoOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    public function getFeaturesListAttribute()
    {
        return $this->features ? implode(', ', $this->features) : '';
    }

    public function getRequirementsListAttribute()
    {
        return $this->requirements ? implode(', ', $this->requirements) : '';
    }
}
