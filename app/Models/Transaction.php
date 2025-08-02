<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'payment_code',
        'amount',
        'currency',
        'payment_gateway',
        'gateway_transaction_id',
        'status',
        'description',
        'tool_order_id',
        'view_order_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function servicePackage(): HasOneThrough
    {
        return $this->hasOneThrough(
            ServicePackage::class,
            Subscription::class,
            'id', // Foreign key on subscriptions table...
            'id', // Foreign key on service_packages table...
            'subscription_id', // Local key on transactions table...
            'service_package_id' // Local key on subscriptions table...
        );
    }

    public function toolOrder(): BelongsTo
    {
        return $this->belongsTo(ToolOrder::class);
    }

    public function viewOrder(): BelongsTo
    {
        return $this->belongsTo(ViewOrder::class);
    }
}
