<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\ServicePackage;
use Carbon\Carbon;

class ProrationService
{
    const DAYS_IN_MONTH = 30;

    /**
     * Calculates the prorated cost for a subscription upgrade.
     *
     * @param Subscription $currentSubscription The user's current active subscription.
     * @param ServicePackage $newPackage The package the user wants to upgrade to.
     * @return array An array containing 'final_amount', 'credit', and 'new_price'.
     */
    public function calculate(Subscription $currentSubscription, ServicePackage $newPackage): array
    {
        // 1. Tính giá trị còn lại của gói cũ
        $oldPackage = $currentSubscription->servicePackage;
        $dailyPriceOfOldPackage = $oldPackage->price / self::DAYS_IN_MONTH;

        $daysRemaining = 0;
        if ($currentSubscription->ends_at->isFuture()) {
            $daysRemaining = now()->startOfDay()->diffInDays($currentSubscription->ends_at->startOfDay());
        }

        $credit = round($dailyPriceOfOldPackage * $daysRemaining);

        // 2. Tính giá cuối cùng
        $newPackagePrice = $newPackage->price;
        $finalAmount = $newPackagePrice - $credit;

        // Đảm bảo giá cuối cùng không phải là số âm
        $finalAmount = max(0, $finalAmount);

        return [
            'final_amount' => round($finalAmount),
            'credit' => $credit,
            'new_price' => $newPackagePrice,
        ];
    }
} 