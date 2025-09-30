<?php

namespace App\Analysis\Providers;

use App\Analysis\Contract\MarketDataInterface;

class ProviderFactory
{
    /**
     * Cache for discovered provider classes
     */
    private static array $providerClasses = [];

    /**
     * Get all available provider classes
     */
    private static function getProviderClasses(): array
    {
        return [
            'binance' => [
                'class' => 'App\\Analysis\\Providers\\BinanceProvider',
                'name' => 'Binance',
                'code' => 'binance'
            ],
            'coingecko' => [
                'class' => 'App\\Analysis\\Providers\\CoingeckoProvider',
                'name' => 'CoinGecko',
                'code' => 'coingecko'
            ]
        ];
    }

    /**
     * Get list of available providers for select dropdown
     */
    public static function getAvailableProviders(): array
    {
        $classes = self::getProviderClasses();
        $providers = [];

        foreach ($classes as $code => $info) {
            $providers[$code] = $info['name'];
        }

        return $providers;
    }

    /**
     * Create provider instance by provider code
     */
    public static function createProvider(string $providerCode): MarketDataInterface
    {
        $classes = self::getProviderClasses();

        if (!isset($classes[$providerCode])) {
            throw new \Exception("Provider '{$providerCode}' not found");
        }

        $className = $classes[$providerCode]['class'];

        // Create instance
        return new $className();
    }
}