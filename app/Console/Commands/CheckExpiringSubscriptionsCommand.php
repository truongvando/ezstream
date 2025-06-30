<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CheckExpiringSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:check-expiring {--days=3 : Days before expiration to send notification}';
    protected $description = 'Check for subscriptions expiring soon and send email notifications';

    public function handle()
    {
        $daysBeforeExpiry = (int) $this->option('days');
        $targetDate = now()->addDays($daysBeforeExpiry)->startOfDay();
        
        $this->info("🔍 Checking for subscriptions expiring in {$daysBeforeExpiry} days...");
        
        // Tìm subscriptions sắp hết hạn
        $expiringSubscriptions = Subscription::where('status', 'ACTIVE')
            ->whereBetween('ends_at', [
                $targetDate,
                $targetDate->copy()->endOfDay()
            ])
            ->with(['user', 'servicePackage'])
            ->get();
            
        if ($expiringSubscriptions->isEmpty()) {
            $this->info("✅ No subscriptions expiring in {$daysBeforeExpiry} days");
            return;
        }
        
        $this->info("📧 Found {$expiringSubscriptions->count()} subscriptions expiring soon");
        
        $sentCount = 0;
        $errorCount = 0;
        
        foreach ($expiringSubscriptions as $subscription) {
            try {
                $this->sendExpirationNotification($subscription, $daysBeforeExpiry);
                $sentCount++;
                
                $this->line("✅ Sent notification to {$subscription->user->email}");
                
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("❌ Failed to send to {$subscription->user->email}: " . $e->getMessage());
                
                Log::error('Failed to send expiration notification', [
                    'subscription_id' => $subscription->id,
                    'user_email' => $subscription->user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("📊 Summary: {$sentCount} sent, {$errorCount} failed");
    }
    
    protected function sendExpirationNotification(Subscription $subscription, int $daysBeforeExpiry)
    {
        $user = $subscription->user;
        $package = $subscription->servicePackage;
        
        // Kiểm tra xem đã gửi notification chưa (tránh spam)
        $notificationKey = "expiry_notification_{$subscription->id}_{$daysBeforeExpiry}d";
        if (cache()->has($notificationKey)) {
            return; // Đã gửi rồi
        }
        
        // Email data
        $emailData = [
            'user_name' => $user->name,
            'package_name' => $package->name,
            'max_streams' => $package->max_streams,
            'expires_at' => $subscription->ends_at,
            'days_remaining' => $daysBeforeExpiry,
            'renewal_url' => route('dashboard') // hoặc route cụ thể
        ];
        
        // Gửi email (cần tạo Mailable class)
        Mail::send('emails.subscription-expiring', $emailData, function ($message) use ($user, $package, $daysBeforeExpiry) {
            $message->to($user->email, $user->name)
                    ->subject("⚠️ Gói {$package->name} sắp hết hạn trong {$daysBeforeExpiry} ngày");
        });
        
        // Cache để tránh gửi lại
        cache()->put($notificationKey, true, now()->addDays(1));
        
        Log::info('Expiration notification sent', [
            'subscription_id' => $subscription->id,
            'user_email' => $user->email,
            'package_name' => $package->name,
            'days_remaining' => $daysBeforeExpiry
        ]);
    }
} 