<?php

namespace App\Helpers;

use App\Services\CurrencyRateService;

class CurrencyHelper
{
    protected static float $usdToIdr = 16500;
    protected static string $locale = 'id_ID';
    protected static ?CurrencyRateService $rateService = null;

    public static function setExchangeRate(float $rate): void
    {
        self::$usdToIdr = $rate;
    }

    public static function getExchangeRate(): float
    {
        // Try to get from API if enabled
        if (config('crypto.currency_api.enabled', true)) {
            $rateService = self::getRateService();
            $apiRate = $rateService->getUSDToIDRRate();

            if ($apiRate > 0) {
                self::$usdToIdr = $apiRate;
                return $apiRate;
            }
        }

        // Fallback to static rate
        return self::$usdToIdr;
    }

    protected static function getRateService(): CurrencyRateService
    {
        if (self::$rateService === null) {
            self::$rateService = new CurrencyRateService();
        }
        return self::$rateService;
    }

    public static function formatUSD(float $amount, int $decimals = 2): string
    {
        return '$' . number_format($amount, $decimals, '.', ',');
    }

    public static function formatIDR(float $amount, int $decimals = 0): string
    {
        return 'Rp ' . number_format($amount, $decimals, ',', '.');
    }

    public static function formatUSDIDR(float $usdAmount, int $usdDecimals = 2, int $idrDecimals = 0): string
    {
        $usdFormatted = self::formatUSD($usdAmount, $usdDecimals);
        $idrAmount = $usdAmount * self::$usdToIdr;
        $idrFormatted = self::formatIDR($idrAmount, $idrDecimals);

        return "{$usdFormatted} / {$idrFormatted}";
    }

    public static function convertUSDToIDR(float $usdAmount): float
    {
        return $usdAmount * self::$usdToIdr;
    }

    public static function convertIDRToUSD(float $idrAmount): float
    {
        return $idrAmount / self::$usdToIdr;
    }

    public static function formatPriceWithCurrency(float $amount, string $currency = 'USD', int $decimals = 2): string
    {
        return match (strtoupper($currency)) {
            'IDR' => self::formatIDR($amount, $decimals),
            'USD' => self::formatUSD($amount, $decimals),
            default => self::formatUSDIDR($amount, $decimals, $decimals)
        };
    }

    public static function getCurrencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'IDR' => 'Rp',
            'USD' => '$',
            default => '$'
        };
    }

    public static function formatTradingAmount(float $amount, string $currency = 'USD'): string
    {
        $decimals = strtoupper($currency) === 'IDR' ? 0 : 2;
        return self::formatPriceWithCurrency($amount, $currency, $decimals);
    }

    public static function initializeFromConfig(): void
    {
        $config = config('crypto');
        if (isset($config['usd_to_idr'])) {
            self::setExchangeRate($config['usd_to_idr']);
        }
    }

    public static function shouldShowBothCurrencies(): bool
    {
        $config = config('crypto.currency_display.show_both_currencies', true);
        return $config;
    }

    public static function getUSDDecimals(): int
    {
        $config = config('crypto.currency_display.usd_decimals', 2);
        return $config;
    }

    public static function getIDRDecimals(): int
    {
        $config = config('crypto.currency_display.idr_decimals', 0);
        return $config;
    }

    public static function getFormatStyle(): string
    {
        $config = config('crypto.currency_display.format_style', 'USD_IDR');
        return $config;
    }

    public static function shouldAlwaysShowBoth(): bool
    {
        $config = config('crypto.currency_display.always_show_both', true);
        return $config;
    }

    public static function formatBasedOnStyle(float $usdAmount): string
    {
        // Always show both currencies if configured
        if (self::shouldAlwaysShowBoth()) {
            return self::formatUSDIDR($usdAmount, self::getUSDDecimals(), self::getIDRDecimals());
        }

        $style = self::getFormatStyle();

        return match ($style) {
            'USD_IDR' => self::formatUSDIDR($usdAmount, self::getUSDDecimals(), self::getIDRDecimals()),
            'IDR_USD' => self::formatIDRUSD($usdAmount, self::getUSDDecimals(), self::getIDRDecimals()),
            'USD_ONLY' => self::formatUSD($usdAmount, self::getUSDDecimals()),
            'IDR_ONLY' => self::formatIDR(self::convertUSDToIDR($usdAmount), self::getIDRDecimals()),
            default => self::formatUSDIDR($usdAmount, self::getUSDDecimals(), self::getIDRDecimals())
        };
    }

    public static function formatIDRUSD(float $usdAmount, int $usdDecimals = 2, int $idrDecimals = 0): string
    {
        $idrFormatted = self::formatIDR(self::convertUSDToIDR($usdAmount), $idrDecimals);
        $usdFormatted = self::formatUSD($usdAmount, $usdDecimals);

        return "{$idrFormatted} / {$usdFormatted}";
    }
}