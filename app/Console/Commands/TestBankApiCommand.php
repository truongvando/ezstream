<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class TestBankApiCommand extends Command
{
    protected $signature = 'test:bank-api';
    protected $description = 'Test bank API connection and payment matching';

    public function handle()
    {
        $this->info('🔍 Testing Bank API Connection...');
        
        // 1. Check API endpoint setting
        $apiUrl = setting('payment_api_endpoint');
        $this->info("API Endpoint: " . ($apiUrl ?: 'NOT SET'));
        
        if (!$apiUrl) {
            $this->error('❌ Payment API endpoint chưa được config!');
            $this->info('Vào Admin > Settings để set payment_api_endpoint');
            return;
        }
        
        // 2. Check pending transactions
        $pendingTransactions = Transaction::where('status', 'PENDING')
            ->with('subscription.servicePackage')
            ->get();
            
        $this->info("Pending transactions: " . $pendingTransactions->count());
        
        if ($pendingTransactions->isEmpty()) {
            $this->warn('⚠️  Không có transaction PENDING nào để test');
            return;
        }
        
        // Show pending codes
        foreach ($pendingTransactions as $tx) {
            $this->info("- Transaction #{$tx->id}: {$tx->payment_code} ({$tx->amount} VND)");
        }
        
        // 3. Test API call
        $this->info("\n🌐 Testing API call...");
        
        try {
            $response = Http::timeout(10)->get($apiUrl);
            
            if (!$response->successful()) {
                $this->error("❌ API call failed: HTTP {$response->status()}");
                $this->error("Response: " . $response->body());
                return;
            }
            
            $data = $response->json();
            $this->info("✅ API call successful!");
            $this->info("Status: " . ($data['status'] ?? 'unknown'));
            
            if (($data['status'] ?? '') !== 'success') {
                $this->error("❌ API returned non-success status");
                $this->info("Full response: " . json_encode($data, JSON_PRETTY_PRINT));
                return;
            }
            
            $bankTransactions = $data['transactions'] ?? [];
            $this->info("Bank transactions count: " . count($bankTransactions));
            
            if (empty($bankTransactions)) {
                $this->warn('⚠️  No bank transactions returned');
                return;
            }
            
            // 4. Test matching logic
            $this->info("\n🔍 Testing payment code matching...");
            
            $matchFound = false;
            foreach ($bankTransactions as $index => $bankTx) {
                $description = strtoupper($bankTx['description'] ?? '');
                $amount = $bankTx['amount'] ?? 0;
                $txId = $bankTx['transactionID'] ?? 'unknown';
                
                $this->info("\nBank TX #{$index}: {$txId}");
                $this->info("Amount: {$amount} VND");
                $this->info("Description: {$description}");
                
                // Check against pending codes
                foreach ($pendingTransactions as $pendingTx) {
                    $code = strtoupper($pendingTx->payment_code);
                    
                    if (str_contains($description, $code)) {
                        $this->info("✅ MATCH FOUND!");
                        $this->info("Payment code: {$code}");
                        $this->info("Expected amount: {$pendingTx->amount} VND");
                        $this->info("Received amount: {$amount} VND");
                        
                        $amountDiff = abs($pendingTx->amount - $amount);
                        if ($amountDiff <= 1) {
                            $this->info("✅ Amount matches (diff: {$amountDiff})");
                        } else {
                            $this->warn("⚠️  Amount mismatch (diff: {$amountDiff})");
                        }
                        
                        $matchFound = true;
                        break;
                    }
                }
                
                if ($index >= 4) { // Limit to first 5 transactions
                    $this->info("... (showing first 5 transactions only)");
                    break;
                }
            }
            
            if (!$matchFound) {
                $this->warn('⚠️  No matches found in bank transactions');
                $this->info('Pending codes: ' . $pendingTransactions->pluck('payment_code')->join(', '));
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
        }
        
        $this->info("\n✅ Test completed!");
    }
} 