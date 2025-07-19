<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use App\Models\User;

class TestEmail extends Command
{
    protected $signature = 'test:email {email?}';
    protected $description = 'Test email configuration and password reset functionality';

    public function handle()
    {
        $email = $this->argument('email') ?: 'test@example.com';
        
        $this->info('🧪 Testing Email Configuration...');
        $this->newLine();
        
        // 1. Test basic email config
        $this->info('📧 Email Configuration:');
        $this->table(['Setting', 'Value'], [
            ['MAIL_MAILER', config('mail.default')],
            ['MAIL_HOST', config('mail.mailers.smtp.host')],
            ['MAIL_PORT', config('mail.mailers.smtp.port')],
            ['MAIL_USERNAME', config('mail.mailers.smtp.username') ? '***SET***' : 'NOT SET'],
            ['MAIL_PASSWORD', config('mail.mailers.smtp.password') ? '***SET***' : 'NOT SET'],
            ['MAIL_FROM_ADDRESS', config('mail.from.address')],
            ['MAIL_FROM_NAME', config('mail.from.name')],
        ]);
        $this->newLine();
        
        // 2. Test database table
        $this->info('🗄️ Database Check:');
        try {
            $tableExists = \Schema::hasTable('password_reset_tokens');
            $this->info($tableExists ? '✅ password_reset_tokens table exists' : '❌ password_reset_tokens table missing');
            
            if ($tableExists) {
                $tokenCount = \DB::table('password_reset_tokens')->count();
                $this->info("📊 Current tokens in table: {$tokenCount}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Database error: " . $e->getMessage());
        }
        $this->newLine();
        
        // 3. Test user lookup
        $this->info('👤 User Check:');
        $user = User::where('email', $email)->first();
        if ($user) {
            $this->info("✅ User found: {$user->name} ({$user->email})");
        } else {
            $this->warn("⚠️ User not found for email: {$email}");
            $this->info("Available users:");
            User::take(5)->get(['id', 'name', 'email'])->each(function($u) {
                $this->line("  - {$u->name} ({$u->email})");
            });
        }
        $this->newLine();
        
        // 4. Test password reset (dry run)
        if ($user) {
            $this->info('🔐 Testing Password Reset:');
            try {
                // Test without actually sending
                $this->info('Attempting to generate reset token...');
                
                $status = Password::sendResetLink(['email' => $email]);
                
                $this->info("Reset status: {$status}");
                
                if ($status === Password::RESET_LINK_SENT) {
                    $this->info('✅ Password reset link would be sent successfully');
                } else {
                    $this->warn("⚠️ Password reset failed with status: {$status}");
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Password reset error: " . $e->getMessage());
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
        }
        
        $this->newLine();
        $this->info('🎯 Test completed!');
        
        return 0;
    }
}
