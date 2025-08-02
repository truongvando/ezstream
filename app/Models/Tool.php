<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'sale_price',
        'image',
        'gallery',
        'features',
        'system_requirements',
        'download_url',
        'demo_url',
        'is_active',
        'is_featured',
        'sort_order',
        'license_type',
        'demo_days',
        'monthly_price',
        'yearly_price',
        'is_own_tool',
        'owner_name',
        'owner_contact',
        'commission_rate',
        'max_devices',
        'allow_transfer',
        'version',
        'changelog',
        'last_updated'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'gallery' => 'array',
        'features' => 'array',
        'changelog' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_own_tool' => 'boolean',
        'allow_transfer' => 'boolean',
        'last_updated' => 'datetime',
    ];

    /**
     * Get the final price (sale price if available, otherwise regular price)
     */
    public function getFinalPriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Check if tool is on sale
     */
    public function getIsOnSaleAttribute()
    {
        return !is_null($this->sale_price);
    }

    /**
     * Relationship with tool orders
     */
    public function toolOrders()
    {
        return $this->hasMany(ToolOrder::class);
    }

    /**
     * Relationship with licenses
     */
    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    /**
     * Scope for active tools
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured tools
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get price for specific license type
     */
    public function getPriceForLicenseType($licenseType)
    {
        return match($licenseType) {
            'FREE' => 0,
            'DEMO' => 0,
            'MONTHLY' => $this->monthly_price ?? $this->price,
            'YEARLY' => $this->yearly_price ?? ($this->price * 10), // 10x monthly as default
            'LIFETIME' => $this->final_price,
            'CONSIGNMENT' => $this->final_price,
            default => $this->final_price
        };
    }

    /**
     * Check if tool is free
     */
    public function getIsFreeAttribute()
    {
        return $this->license_type === 'FREE';
    }

    /**
     * Check if tool has demo
     */
    public function getHasDemoAttribute()
    {
        return $this->license_type === 'DEMO' || $this->demo_days > 0;
    }

    /**
     * Check if tool is consignment
     */
    public function getIsConsignmentAttribute()
    {
        return $this->license_type === 'CONSIGNMENT' || !$this->is_own_tool;
    }

    /**
     * Get available license types for this tool
     */
    public function getAvailableLicenseTypesAttribute()
    {
        $types = [];

        if ($this->license_type === 'FREE') {
            $types[] = ['type' => 'FREE', 'price' => 0, 'name' => 'Miễn phí'];
        } else {
            if ($this->has_demo) {
                $types[] = ['type' => 'DEMO', 'price' => 0, 'name' => "Demo {$this->demo_days} ngày"];
            }

            if ($this->monthly_price) {
                $types[] = ['type' => 'MONTHLY', 'price' => $this->monthly_price, 'name' => 'Hàng tháng'];
            }

            if ($this->yearly_price) {
                $types[] = ['type' => 'YEARLY', 'price' => $this->yearly_price, 'name' => 'Hàng năm'];
            }

            $types[] = ['type' => 'LIFETIME', 'price' => $this->final_price, 'name' => 'Vĩnh viễn'];
        }

        return $types;
    }

    /**
     * Scope for own tools only
     */
    public function scopeOwnTools($query)
    {
        return $query->where('is_own_tool', true);
    }

    /**
     * Scope for consignment tools
     */
    public function scopeConsignment($query)
    {
        return $query->where('is_own_tool', false);
    }
}
