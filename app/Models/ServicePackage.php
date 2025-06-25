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
        'max_streams',
        'storage_limit',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get active service packages
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate yearly discount percentage
     */
    public function getYearlyDiscountPercentAttribute(): float
    {
        if (!$this->price_yearly || !$this->price_monthly) {
            return 0;
        }
        
        $monthlyYearly = $this->price_monthly * 12;
        return round((($monthlyYearly - $this->price_yearly) / $monthlyYearly) * 100, 1);
    }
}
