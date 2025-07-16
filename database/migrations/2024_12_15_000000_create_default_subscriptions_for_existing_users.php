<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\ServicePackage;
use App\Models\Subscription;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get basic package (720p)
        $basicPackage = ServicePackage::where('name', 'Gói Cơ Bản')->first();
        
        if (!$basicPackage) {
            return; // Skip if no basic package found
        }

        // Get all users without active subscriptions (excluding admins)
        $usersWithoutPackage = User::whereDoesntHave('subscriptions', function ($query) {
            $query->where('status', 'ACTIVE');
        })->where('role', '!=', 'ADMIN')->get();

        foreach ($usersWithoutPackage as $user) {
            // Create free trial subscription for 30 days
            Subscription::create([
                'user_id' => $user->id,
                'service_package_id' => $basicPackage->id,
                'status' => 'ACTIVE',
                'starts_at' => now(),
                'ends_at' => now()->addDays(30), // 30 days free trial
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove trial subscriptions
        Subscription::where('payment_transaction_id', null)
            ->where('ends_at', '>', now()->subDays(31))
            ->where('ends_at', '<', now()->addDay())
            ->delete();
    }
};
