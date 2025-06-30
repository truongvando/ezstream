<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePackage extends Model
{
    /** @use HasFactory<\Database\Factories\ServicePackageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'max_video_width',
        'max_video_height',
        'max_streams',
        'storage_limit_gb',
        'features',
        'is_active',
        'is_popular',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
    ];

    /**
     * Get active service packages
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate yearly discount percentage (placeholder - not implemented)
     */
    public function getYearlyDiscountPercentAttribute(): float
    {
        // Placeholder - yearly pricing not implemented yet
        return 0;
    }
}
