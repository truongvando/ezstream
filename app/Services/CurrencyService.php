<?php

namespace App\Services;

use App\Services\ExchangeRateService;

class CurrencyService
{
    private $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }

    /**
     * Format USD amount with currency symbol
     */
    public function formatUSD(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    /**
     * Format VND amount with currency symbol
     */
    public function formatVND(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' VND';
    }

    /**
     * Convert USD to VND using current exchange rate
     */
    public function convertUsdToVnd(float $usdAmount): float
    {
        return $this->exchangeRateService->convertUsdToVnd($usdAmount);
    }

    /**
     * Convert VND to USD using current exchange rate
     */
    public function convertVndToUsd(float $vndAmount): float
    {
        return $this->exchangeRateService->convertVndToUsd($vndAmount);
    }

    /**
     * Get formatted price display (USD primary, VND secondary)
     */
    public function getFormattedPriceDisplay(float $usdAmount): array
    {
        $vndAmount = $this->convertUsdToVnd($usdAmount);
        
        return [
            'usd' => $this->formatUSD($usdAmount),
            'vnd' => $this->formatVND($vndAmount),
            'usd_raw' => $usdAmount,
            'vnd_raw' => $vndAmount,
            'display' => $this->formatUSD($usdAmount) . ' (≈ ' . $this->formatVND($vndAmount) . ')'
        ];
    }

    /**
     * Validate currency code
     */
    public function isValidCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['USD', 'VND']);
    }

    /**
     * Get default currency for the system
     */
    public function getDefaultCurrency(): string
    {
        return 'USD';
    }

    /**
     * Get display currency for Vietnamese users
     */
    public function getDisplayCurrency(): string
    {
        return 'VND';
    }

    /**
     * Normalize amount to USD (convert if needed)
     */
    public function normalizeToUSD(float $amount, string $fromCurrency = 'USD'): float
    {
        if (strtoupper($fromCurrency) === 'VND') {
            return $this->convertVndToUsd($amount);
        }
        
        return $amount; // Already USD
    }

    /**
     * Get current exchange rate info
     */
    public function getExchangeRateInfo(): array
    {
        return $this->exchangeRateService->getRateInfo();
    }
}
