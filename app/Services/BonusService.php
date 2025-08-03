<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\DepositBonus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BonusService
{
    /**
     * Bonus tiers based on total deposits
     */
    const BONUS_TIERS = [
        ['min' => 0, 'max' => 99.99, 'percentage' => 0, 'name' => 'none'],
        ['min' => 100, 'max' => 499.99, 'percentage' => 2, 'name' => '2%'],
        ['min' => 500, 'max' => 999.99, 'percentage' => 3, 'name' => '3%'],
        ['min' => 1000, 'max' => 1499.99, 'percentage' => 4, 'name' => '4%'],
        ['min' => 1500, 'max' => PHP_FLOAT_MAX, 'percentage' => 5, 'name' => '5%'],
    ];

    /**
     * Calculate and apply bonus for a deposit
     */
    public function calculateAndApplyBonus(User $user, Transaction $transaction): ?DepositBonus
    {
        if ($transaction->status !== 'COMPLETED' || $transaction->amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $transaction) {
            // Lock user to prevent race conditions
            $user = $user->lockForUpdate();
            
            $depositAmount = $transaction->amount;
            $totalDepositsBefore = $user->total_deposits;
            $totalDepositsAfter = $totalDepositsBefore + $depositAmount;
            
            // Get bonus tier based on total deposits BEFORE this deposit
            $bonusTier = $this->getBonusTier($totalDepositsBefore);
            $bonusPercentage = $bonusTier['percentage'];
            $bonusAmount = 0;
            
            if ($bonusPercentage > 0) {
                // Bonus is percentage of THIS deposit amount
                $bonusAmount = $depositAmount * ($bonusPercentage / 100);
                
                // Add bonus to user balance
                $user->increment('balance', $bonusAmount);
                
                Log::info('Deposit bonus applied', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'deposit_amount' => $depositAmount,
                    'bonus_amount' => $bonusAmount,
                    'bonus_percentage' => $bonusPercentage,
                    'total_deposits_before' => $totalDepositsBefore,
                    'total_deposits_after' => $totalDepositsAfter
                ]);
            }
            
            // Update user total deposits
            $user->update(['total_deposits' => $totalDepositsAfter]);
            
            // Create bonus record
            $depositBonus = DepositBonus::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'deposit_amount' => $depositAmount,
                'bonus_amount' => $bonusAmount,
                'bonus_percentage' => $bonusPercentage,
                'total_deposits_before' => $totalDepositsBefore,
                'total_deposits_after' => $totalDepositsAfter,
                'bonus_tier' => $bonusTier['name'],
                'calculation_details' => json_encode([
                    'tier' => $bonusTier,
                    'calculation' => "{$depositAmount} × {$bonusPercentage}% = {$bonusAmount}",
                    'applied_at' => now()->toISOString()
                ])
            ]);
            
            return $depositBonus;
        });
    }

    /**
     * Get bonus tier for a total deposit amount
     */
    public function getBonusTier(float $totalDeposits): array
    {
        foreach (self::BONUS_TIERS as $tier) {
            if ($totalDeposits >= $tier['min'] && $totalDeposits <= $tier['max']) {
                return $tier;
            }
        }
        
        // Default to highest tier if somehow not found
        return end(self::BONUS_TIERS);
    }

    /**
     * Get next bonus tier info for user
     */
    public function getNextTierInfo(User $user): ?array
    {
        $currentTier = $this->getBonusTier($user->total_deposits);
        
        foreach (self::BONUS_TIERS as $tier) {
            if ($tier['min'] > $user->total_deposits) {
                return [
                    'current_tier' => $currentTier,
                    'next_tier' => $tier,
                    'amount_needed' => $tier['min'] - $user->total_deposits,
                    'progress_percentage' => min(100, ($user->total_deposits / $tier['min']) * 100)
                ];
            }
        }
        
        // User is at highest tier
        return [
            'current_tier' => $currentTier,
            'next_tier' => null,
            'amount_needed' => 0,
            'progress_percentage' => 100
        ];
    }

    /**
     * Preview bonus for a deposit amount
     */
    public function previewBonus(User $user, float $depositAmount): array
    {
        $currentTier = $this->getBonusTier($user->total_deposits);
        $bonusAmount = 0;
        
        if ($currentTier['percentage'] > 0) {
            $bonusAmount = $depositAmount * ($currentTier['percentage'] / 100);
        }
        
        return [
            'deposit_amount' => $depositAmount,
            'bonus_amount' => $bonusAmount,
            'bonus_percentage' => $currentTier['percentage'],
            'total_received' => $depositAmount + $bonusAmount,
            'current_tier' => $currentTier,
            'total_deposits_after' => $user->total_deposits + $depositAmount
        ];
    }

    /**
     * Get user's bonus history
     */
    public function getUserBonusHistory(User $user, int $limit = 10)
    {
        return DepositBonus::where('user_id', $user->id)
                          ->with('transaction')
                          ->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get();
    }

    /**
     * Get bonus statistics
     */
    public function getBonusStats(): array
    {
        return [
            'total_bonuses_paid' => DepositBonus::sum('bonus_amount'),
            'total_deposits_with_bonus' => DepositBonus::sum('deposit_amount'),
            'average_bonus_percentage' => DepositBonus::avg('bonus_percentage'),
            'users_with_bonuses' => DepositBonus::distinct('user_id')->count(),
            'bonuses_this_month' => DepositBonus::whereMonth('created_at', now()->month)
                                              ->whereYear('created_at', now()->year)
                                              ->sum('bonus_amount')
        ];
    }
}
