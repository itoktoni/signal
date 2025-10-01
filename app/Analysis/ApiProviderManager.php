<?php

namespace App\Analysis;

use App\Analysis\Providers\BinanceProvider;
use App\Analysis\Providers\CoingeckoProvider;
use App\Settings\Settings;
use Illuminate\Support\Facades\Log;

class ApiProviderManager
{
    private Settings $settings;
    private array $providers = [];

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->initializeProviders();
    }

    private function initializeProviders(): void
    {
        $availableProviders = [
            'binance' => BinanceProvider::class,
            'coingecko' => CoingeckoProvider::class,
        ];

        foreach ($availableProviders as $code => $providerClass) {
            try {
                $this->providers[$code] = new $providerClass();
                Log::info("API Provider {$code} initialized successfully");
            } catch (\Exception $e) {
                Log::warning("Failed to initialize API provider {$code}: " . $e->getMessage());
            }
        }
    }

    public function getAvailableProviders(): array
    {
        return $this->providers;
    }

    public function getProvider(string $code): ?object
    {
        return $this->providers[$code] ?? null;
    }

    public function getHistoricalData(string $symbol, string $timeframe = '1h', int $limit = 200): array
    {
        $primaryProvider = $this->getPrimaryProvider($symbol);

        try {
            return $primaryProvider->getHistoricalData($symbol, $timeframe, $limit);
        } catch (\Exception $e) {
            Log::warning("Primary provider failed for {$symbol}, trying fallback", [
                'provider' => $primaryProvider->getCode(),
                'error' => $e->getMessage()
            ]);

            // Try fallback providers
            $fallbackProviders = $this->getFallbackProviders($symbol, $primaryProvider->getCode());

            foreach ($fallbackProviders as $provider) {
                try {
                    return $provider->getHistoricalData($symbol, $timeframe, $limit);
                } catch (\Exception $fallbackError) {
                    Log::debug("Fallback provider {$provider->getCode()} also failed for {$symbol}", [
                        'error' => $fallbackError->getMessage()
                    ]);
                    continue;
                }
            }

            throw new \Exception("All providers failed for symbol {$symbol}");
        }
    }

    public function getCurrentPrice(string $symbol): float
    {
        $primaryProvider = $this->getPrimaryProvider($symbol);

        try {
            return $primaryProvider->getPrice($symbol);
        } catch (\Exception $e) {
            Log::warning("Primary provider failed for {$symbol} price, trying fallback", [
                'provider' => $primaryProvider->getCode(),
                'error' => $e->getMessage()
            ]);

            // Try fallback providers
            $fallbackProviders = $this->getFallbackProviders($symbol, $primaryProvider->getCode());

            foreach ($fallbackProviders as $provider) {
                try {
                    return $provider->getPrice($symbol);
                } catch (\Exception $fallbackError) {
                    Log::debug("Fallback provider {$provider->getCode()} also failed for {$symbol} price", [
                        'error' => $fallbackError->getMessage()
                    ]);
                    continue;
                }
            }

            throw new \Exception("All providers failed for symbol {$symbol} price");
        }
    }

    private function getPrimaryProvider(string $symbol): object
    {
        $coinMapping = config('crypto.coin_api_mapping', []);
        $primaryApi = $coinMapping['primary_api'][$symbol] ?? 'binance';

        $provider = $this->getProvider($primaryApi);
        if (!$provider) {
            throw new \Exception("Primary provider {$primaryApi} not available for symbol {$symbol}");
        }

        return $provider;
    }

    private function getFallbackProviders(string $symbol, string $excludeProvider): array
    {
        $coinMapping = config('crypto.coin_api_mapping', []);
        $fallbackApis = $coinMapping['fallback_apis'][$symbol] ?? ['binance', 'coingecko'];

        $providers = [];
        foreach ($fallbackApis as $apiCode) {
            if ($apiCode !== $excludeProvider && isset($this->providers[$apiCode])) {
                $providers[] = $this->providers[$apiCode];
            }
        }

        return $providers;
    }
}