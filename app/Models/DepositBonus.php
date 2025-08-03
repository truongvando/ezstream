<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositBonus extends Model
{
    protected $fillable = [
        'user_id',
        'transaction_id',
        'deposit_amount',
        'bonus_amount',
        'bonus_percentage',
        'total_deposits_before',
        'total_deposits_after',
        'bonus_tier',
        'calculation_details'
    ];

    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'bonus_percentage' => 'decimal:2',
        'total_deposits_before' => 'decimal:2',
        'total_deposits_after' => 'decimal:2'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
