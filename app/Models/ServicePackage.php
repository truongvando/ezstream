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

    /**
     * Get video resolution based on max_video_height
     */
    public function getVideoResolutionAttribute(): string
    {
        if (!$this->max_video_height) {
            return '1080';
        }

        return match($this->max_video_height) {
            720 => '720',
            1080 => '1080',
            1440 => '1440',
            2160 => '4K',
            default => (string) $this->max_video_height
        };
    }
}
